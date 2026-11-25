<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class BookingService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Mengambil daftar lapangan aktif di cabang ini.
     */
    public function getActiveCourts(): array {
        $stmt = $this->db->query("SELECT * FROM courts WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * CREATE: Reservasi Baru dengan Atomic Conflict Check & Idempotency Key.
     */
    public function createBooking(array $payload): array {
        $courtId     = (int) $payload['court_id'];
        $date        = $payload['booking_date'];
        $startTime   = $payload['start_time']; // Format "HH:MM:SS"
        $duration    = (int) $payload['duration_hours'];
        $customer    = trim($payload['customer_name'] ?? '');
        $team        = trim($payload['team_name'] ?? '');
        $phone       = trim($payload['phone_number'] ?? '');
        $idempotency = trim($payload['idempotency_key'] ?? bin2hex(random_bytes(16)));

        if ($customer === '' || $team === '' || $phone === '') {
            throw new InvalidArgumentException("Nama pemesan, nama tim, dan nomor WhatsApp wajib diisi.");
        }

        if ($duration < 1 || $duration > 6) {
            throw new InvalidArgumentException("Durasi sewa minimal 1 jam dan maksimal 6 jam.");
        }

        // Kalkulasi waktu selesai
        $startDt = new DateTime("{$date} {$startTime}");
        $endDt   = (clone $startDt)->modify("+{$duration} hours");
        $endTime = $endDt->format('H:i:s');

        // Buka Transaksi Database ACID
        $this->db->beginTransaction();

        try {
            // 1. Idempotency Check (Mencegah double click / network duplicate)
            $stmtCheckKey = $this->db->prepare("SELECT booking_code, total_price, status FROM bookings WHERE idempotency_key = ?");
            $stmtCheckKey->execute([$idempotency]);
            if ($existing = $stmtCheckKey->fetch()) {
                $this->db->rollBack();
                return [
                    'is_duplicate' => true,
                    'booking_code' => $existing['booking_code'],
                    'total_price'  => (float) $existing['total_price'],
                    'status'       => $existing['status']
                ];
            }

            // 2. Kunci baris jadwal yang bentrok (Pessimistic Locking dengan SELECT ... FOR UPDATE)
            // Logika Overlap Terdistribusi: (StartExisting < EndBaru) AND (EndExisting > StartBaru)
            $stmtConflict = $this->db->prepare("
                SELECT booking_code, team_name, start_time, end_time 
                FROM bookings 
                WHERE court_id = ? 
                  AND booking_date = ? 
                  AND status NOT IN ('CANCELLED', 'EXPIRED')
                  AND (start_time < ? AND end_time > ?)
                FOR UPDATE
            ");
            $stmtConflict->execute([$courtId, $date, $endTime, $startTime]);
            $conflict = $stmtConflict->fetch();

            if ($conflict) {
                $this->db->rollBack();
                $jamMulai = substr($conflict['start_time'], 0, 5);
                $jamSelesai = substr($conflict['end_time'], 0, 5);
                throw new RuntimeException(
                    "Jadwal bentrok! Lapangan sudah terisi oleh tim '{$conflict['team_name']}' pada jam {$jamMulai} - {$jamSelesai}."
                );
            }

            // 3. Ambil data tarif lapangan
            $stmtCourt = $this->db->prepare("SELECT name, hourly_rate FROM courts WHERE id = ? AND is_active = 1");
            $stmtCourt->execute([$courtId]);
            $court = $stmtCourt->fetch();
            if (!$court) {
                throw new RuntimeException("Lapangan yang dipilih tidak ditemukan atau nonaktif.");
            }

            $totalPrice  = (float) $court['hourly_rate'] * $duration;
            $bookingCode = 'FUT-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -5));

            // 4. Simpan ke tabel bookings (Status awal: PENDING pembayaran DP)
            $stmtInsert = $this->db->prepare("
                INSERT INTO bookings (
                    booking_code, idempotency_key, court_id, customer_name,
                    team_name, phone_number, booking_date, start_time,
                    duration_hours, end_time, total_price, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
            ");
            $stmtInsert->execute([
                $bookingCode, $idempotency, $courtId, $customer,
                $team, $phone, $date, $startTime,
                $duration, $endTime, $totalPrice
            ]);

            $bookingId = (int) $this->db->lastInsertId();

            // 5. Catat ke System Audit Log (Node A)
            $stmtLog = $this->db->prepare("
                INSERT INTO system_logs (node_name, event_type, reference_code, message)
                VALUES ('Node A (Booking Engine)', 'BOOKING_CREATED', ?, ?)
            ");
            $stmtLog->execute([
                $bookingCode,
                "Reservasi {$bookingCode} berhasil dibuat untuk tim '{$team}' di {$court['name']} ({$date} {$startTime}-{$endTime}). Total: Rp " . number_format($totalPrice)
            ]);

            $this->db->commit();

            return [
                'is_duplicate' => false,
                'booking_id'   => $bookingId,
                'booking_code' => $bookingCode,
                'court_name'   => $court['name'],
                'total_price'  => $totalPrice,
                'min_dp'       => $totalPrice * MIN_DP_PERCENTAGE,
                'status'       => 'PENDING'
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * READ: Mengambil seluruh jadwal untuk tanggal tertentu beserta status pembayarannya.
     */
    public function getSchedulesByDate(string $date): array {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name AS court_name, c.type AS court_type,
                   COALESCE((SELECT SUM(amount) FROM payment_transactions WHERE booking_id = b.id), 0) AS total_paid
            FROM bookings b
            JOIN courts c ON b.court_id = c.id
            WHERE b.booking_date = ?
            ORDER BY b.start_time ASC
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    /**
     * DELETE / VOID: Membatalkan booking (Soft Delete via status CANCELLED).
     */
    public function cancelBooking(string $bookingCode, string $reason = 'Permintaan Pengguna / Batal'): bool {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("SELECT id, status, team_name FROM bookings WHERE booking_code = ? FOR UPDATE");
            $stmt->execute([$bookingCode]);
            $b = $stmt->fetch();

            if (!$b) {
                throw new RuntimeException("Kode booking {$bookingCode} tidak ditemukan.");
            }

            if ($b['status'] === 'CANCELLED') {
                $this->db->rollBack();
                return true;
            }

            if ($b['status'] === 'SETTLED') {
                throw new RuntimeException("Booking yang sudah LUNAS tidak dapat dibatalkan secara langsung.");
            }

            // Ubah status jadi CANCELLED
            $stmtUpd = $this->db->prepare("UPDATE bookings SET status = 'CANCELLED' WHERE id = ?");
            $stmtUpd->execute([$b['id']]);

            // Catat log
            $stmtLog = $this->db->prepare("
                INSERT INTO system_logs (node_name, event_type, reference_code, message)
                VALUES ('Node A (Booking Engine)', 'BOOKING_CANCELLED', ?, ?)
            ");
            $stmtLog->execute([
                $bookingCode,
                "Booking {$bookingCode} untuk tim '{$b['team_name']}' dibatalkan. Alasan: {$reason}."
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ambil 10 log aktivitas sistem terdistribusi terbaru.
     */
    public function getRecentSystemLogs(int $limit = 10): array {
        $stmt = $this->db->prepare("SELECT * FROM system_logs ORDER BY logged_at DESC, id DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
