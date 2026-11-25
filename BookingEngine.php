<?php
/**
 * BookingEngine.php
 * Core Domain Service - FutsalHub Arena Platform
 * Menangani logika transaksi, concurrency row-locking 5 menit, dan audit log finansial.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

class BookingEngine {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
        // Bersihkan lock kedaluwarsa secara otomatis
        $this->cleanExpiredLocks();
    }

    /**
     * Membersihkan lock yang masa berlakunya (5 menit) telah lewat.
     */
    public function cleanExpiredLocks(): int {
        $stmt = $this->db->prepare("DELETE FROM slot_locks WHERE expires_at <= NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Mengambil daftar master lapangan.
     */
    public function getCourts(): array {
        $stmt = $this->db->query("SELECT * FROM courts WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Mengambil status 16 slot harian (08:00 - 24:00) langsung dari database.
     */
    public function getDaySchedule(int $courtId, string $date): array {
        $stmtCourt = $this->db->prepare("SELECT * FROM courts WHERE id = ?");
        $stmtCourt->execute([$courtId]);
        $court = $stmtCourt->fetch();
        if (!$court) {
            throw new InvalidArgumentException("Lapangan dengan ID {$courtId} tidak ditemukan.");
        }

        // Ambil semua booking aktif
        $stmtBookings = $this->db->prepare("
            SELECT booking_code, slot_hour, customer_name, total_price, dp_amount, remaining_amount, status
            FROM bookings
            WHERE court_id = ? AND booking_date = ? AND status != 'CANCELLED'
        ");
        $stmtBookings->execute([$courtId, $date]);
        $bookings = [];
        foreach ($stmtBookings->fetchAll() as $b) {
            $bookings[(int)$b['slot_hour']] = $b;
        }

        // Ambil semua lock aktif
        $stmtLocks = $this->db->prepare("
            SELECT slot_hour, locked_by, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining_seconds
            FROM slot_locks
            WHERE court_id = ? AND booking_date = ? AND expires_at > NOW()
        ");
        $stmtLocks->execute([$courtId, $date]);
        $locks = [];
        foreach ($stmtLocks->fetchAll() as $l) {
            $locks[(int)$l['slot_hour']] = $l;
        }

        $slots = [];
        for ($h = OPERATING_HOUR_START; $h < OPERATING_HOUR_END; $h++) {
            $isPeak = ($h >= PEAK_HOUR_START);
            $rate = $isPeak ? (float)$court['peak_rate'] : (float)$court['off_peak_rate'];

            if (isset($bookings[$h])) {
                $b = $bookings[$h];
                $slots[] = [
                    'hour' => $h,
                    'timeLabel' => sprintf('%02d:00 - %02d:00', $h, $h + 1),
                    'status' => 'BOOKED',
                    'price' => (float)$b['total_price'],
                    'booking' => [
                        'code' => $b['booking_code'],
                        'customer' => $b['customer_name'],
                        'status' => $b['status']
                    ]
                ];
            } elseif (isset($locks[$h])) {
                $l = $locks[$h];
                $slots[] = [
                    'hour' => $h,
                    'timeLabel' => sprintf('%02d:00 - %02d:00', $h, $h + 1),
                    'status' => 'LOCKED',
                    'price' => $rate,
                    'lock' => [
                        'lockedBy' => $l['locked_by'],
                        'remainingSeconds' => max(0, (int)$l['remaining_seconds']),
                        'slotKey' => "{$courtId}_{$date}_{$h}"
                    ]
                ];
            } else {
                $slots[] = [
                    'hour' => $h,
                    'timeLabel' => sprintf('%02d:00 - %02d:00', $h, $h + 1),
                    'status' => 'AVAILABLE',
                    'price' => $rate,
                    'isPeak' => $isPeak
                ];
            }
        }

        return [
            'court' => $court,
            'date' => $date,
            'slots' => $slots
        ];
    }

    /**
     * Mengambil ringkasan ketersediaan slot untuk daftar tanggal (untuk indikator button tanggal).
     */
    public function getDaysSlotSummary(int $courtId, array $dates): array {
        $result = [];
        $totalDailySlots = OPERATING_HOUR_END - OPERATING_HOUR_START;
        
        foreach ($dates as $date) {
            // Hitung booking aktif
            $stmtB = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE court_id = ? AND booking_date = ? AND status != 'CANCELLED'");
            $stmtB->execute([$courtId, $date]);
            $bookedCount = (int)$stmtB->fetchColumn();

            // Hitung lock aktif
            $stmtL = $this->db->prepare("SELECT COUNT(*) FROM slot_locks WHERE court_id = ? AND booking_date = ? AND expires_at > NOW()");
            $stmtL->execute([$courtId, $date]);
            $lockedCount = (int)$stmtL->fetchColumn();

            $avail = max(0, $totalDailySlots - $bookedCount - $lockedCount);
            $result[$date] = [
                'total' => $totalDailySlots,
                'available' => $avail,
                'booked' => $bookedCount,
                'locked' => $lockedCount
            ];
        }
        return $result;
    }

    /**
     * Mengunci slot selama 5 menit secara atomik (Pessimistic Concurrency Lock).
     */
    public function acquireLock(int $courtId, string $date, int $hour, string $clientToken): array {
        if ($hour < OPERATING_HOUR_START || $hour >= OPERATING_HOUR_END) {
            throw new InvalidArgumentException("Jam di luar rentang operasional.");
        }

        $this->db->beginTransaction();
        try {
            // 1. Cek apakah sudah dibooking
            $stmtCheckBook = $this->db->prepare("
                SELECT id, customer_name FROM bookings 
                WHERE court_id = ? AND booking_date = ? AND slot_hour = ? AND status != 'CANCELLED'
                FOR UPDATE
            ");
            $stmtCheckBook->execute([$courtId, $date, $hour]);
            if ($existingBook = $stmtCheckBook->fetch()) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'statusCode' => 409,
                    'message' => "Slot ini telah selesai dipesan oleh {$existingBook['customer_name']}."
                ];
            }

            // 2. Cek apakah sedang dikunci user lain
            $stmtCheckLock = $this->db->prepare("
                SELECT locked_by, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining_seconds 
                FROM slot_locks 
                WHERE court_id = ? AND booking_date = ? AND slot_hour = ? AND expires_at > NOW()
                FOR UPDATE
            ");
            $stmtCheckLock->execute([$courtId, $date, $hour]);
            $currentLock = $stmtCheckLock->fetch();

            if ($currentLock && $currentLock['locked_by'] !== $clientToken) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'statusCode' => 409,
                    'message' => "RACE CONDITION: Slot sedang dikunci oleh pengguna lain! Sisa waktu kunci: {$currentLock['remaining_seconds']} detik."
                ];
            }

            // 3. Pasang / Perpanjang Lock 5 Menit (TTL 300 detik)
            $expiresAt = date('Y-m-d H:i:s', time() + SLOT_LOCK_TTL_SECONDS);
            $stmtSetLock = $this->db->prepare("
                INSERT INTO slot_locks (court_id, booking_date, slot_hour, locked_by, expires_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE locked_by = VALUES(locked_by), expires_at = VALUES(expires_at)
            ");
            $stmtSetLock->execute([$courtId, $date, $hour, $clientToken, $expiresAt]);

            // Ambil tarif
            $stmtCourt = $this->db->prepare("SELECT * FROM courts WHERE id = ?");
            $stmtCourt->execute([$courtId]);
            $court = $stmtCourt->fetch();
            $rate = ($hour >= PEAK_HOUR_START) ? (float)$court['peak_rate'] : (float)$court['off_peak_rate'];

            $this->db->commit();

            return [
                'success' => true,
                'statusCode' => 200,
                'lock' => [
                    'courtId' => $courtId,
                    'courtName' => $court['name'],
                    'date' => $date,
                    'hour' => $hour,
                    'timeLabel' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
                    'totalPrice' => $rate,
                    'dpAmount' => $rate * MIN_DP_PERCENTAGE,
                    'remainingAmount' => $rate * (1 - MIN_DP_PERCENTAGE),
                    'lockedBy' => $clientToken,
                    'remainingSeconds' => SLOT_LOCK_TTL_SECONDS
                ]
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Melepaskan kunci slot.
     */
    public function releaseLock(int $courtId, string $date, int $hour, string $clientToken): bool {
        $stmt = $this->db->prepare("
            DELETE FROM slot_locks 
            WHERE court_id = ? AND booking_date = ? AND slot_hour = ? 
              AND (locked_by = ? OR expires_at <= NOW())
        ");
        $stmt->execute([$courtId, $date, $hour, $clientToken]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Konfirmasi pembayaran DP 50% via online gateway.
     */
    public function confirmOnlineDP(int $courtId, string $date, int $hour, string $customer, string $phone, string $clientToken, string $paymentMethod = 'QRIS'): array {
        $this->db->beginTransaction();
        try {
            // Verifikasi bahwa slot masih dikunci oleh user bersangkutan
            $stmtLock = $this->db->prepare("
                SELECT id FROM slot_locks 
                WHERE court_id = ? AND booking_date = ? AND slot_hour = ? 
                  AND locked_by = ? AND expires_at > NOW()
                FOR UPDATE
            ");
            $stmtLock->execute([$courtId, $date, $hour, $clientToken]);
            if (!$stmtLock->fetch()) {
                throw new RuntimeException("Batas waktu kunci 5 menit telah habis atau slot telah dilepas!");
            }

            // Ambil data court & tarif
            $stmtCourt = $this->db->prepare("SELECT * FROM courts WHERE id = ?");
            $stmtCourt->execute([$courtId]);
            $court = $stmtCourt->fetch();

            $totalPrice = ($hour >= PEAK_HOUR_START) ? (float)$court['peak_rate'] : (float)$court['off_peak_rate'];
            $dpAmount   = $totalPrice * MIN_DP_PERCENTAGE;
            $remAmount  = $totalPrice - $dpAmount;

            $bookingCode = sprintf('FUT-%s-%02d-%04d', str_replace('-', '', $date), $hour, rand(1000, 9999));

            // Simpan booking
            $stmtIns = $this->db->prepare("
                INSERT INTO bookings (
                    booking_code, court_id, customer_name, phone_number,
                    booking_date, slot_hour, total_price, dp_amount,
                    remaining_amount, status, source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'DP_PAID', 'ONLINE')
            ");
            $stmtIns->execute([
                $bookingCode, $courtId, $customer, $phone,
                $date, $hour, $totalPrice, $dpAmount, $remAmount
            ]);
            $bookingId = (int)$this->db->lastInsertId();

            // Simpan transaksi DP
            $trxRef = 'PAY-DP-' . date('YmdHis') . '-' . rand(100, 999);
            $stmtPay = $this->db->prepare("
                INSERT INTO payment_transactions (booking_id, transaction_ref, payment_type, amount, payment_method)
                VALUES (?, ?, 'DP_ONLINE', ?, ?)
            ");
            $stmtPay->execute([$bookingId, $trxRef, $dpAmount, $paymentMethod]);

            // Hapus lock karena transaksi selesai
            $stmtDel = $this->db->prepare("DELETE FROM slot_locks WHERE court_id = ? AND booking_date = ? AND slot_hour = ?");
            $stmtDel->execute([$courtId, $date, $hour]);

            // Log event
            $stmtLog = $this->db->prepare("
                INSERT INTO system_logs (node_name, event_type, reference_code, message)
                VALUES ('Node A (Booking)', 'DP_CONFIRMED', ?, ?)
            ");
            $stmtLog->execute([
                $bookingCode,
                "Booking {$bookingCode} berhasil dibuat untuk '{$customer}'. DP Rp " . number_format($dpAmount) . " ({$paymentMethod})."
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'bookingCode' => $bookingCode,
                'bookingId' => $bookingId,
                'customerName' => $customer,
                'phoneNumber' => $phone,
                'courtId' => $courtId,
                'courtName' => $court['name'],
                'date' => $date,
                'hour' => $hour,
                'timeLabel' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
                'totalPrice' => $totalPrice,
                'paidDP' => $dpAmount,
                'remaining' => $remAmount,
                'paymentMethod' => $paymentMethod
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Pelunasan sisa tagihan 50% di meja kasir.
     */
    public function settleAtCashier(string $bookingCode, string $method): array {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM bookings WHERE booking_code = ? FOR UPDATE");
            $stmt->execute([$bookingCode]);
            $b = $stmt->fetch();
            if (!$b) {
                throw new RuntimeException("Kode booking {$bookingCode} tidak ditemukan.");
            }

            if ($b['status'] === 'SETTLED') {
                throw new RuntimeException("Booking ini sudah lunas sebelumnya.");
            }

            $rem = (float)$b['remaining_amount'];
            $trxRef = 'PAY-SETTLE-' . date('YmdHis') . '-' . rand(100, 999);

            $stmtPay = $this->db->prepare("
                INSERT INTO payment_transactions (booking_id, transaction_ref, payment_type, amount, payment_method)
                VALUES (?, ?, 'SETTLEMENT_CASHIER', ?, ?)
            ");
            $stmtPay->execute([$b['id'], $trxRef, $rem, $method]);

            $stmtUpd = $this->db->prepare("UPDATE bookings SET status = 'SETTLED', remaining_amount = 0 WHERE id = ?");
            $stmtUpd->execute([$b['id']]);

            $stmtLog = $this->db->prepare("
                INSERT INTO system_logs (node_name, event_type, reference_code, message)
                VALUES ('Node B (Kasir POS)', 'SETTLEMENT_PAID', ?, ?)
            ");
            $stmtLog->execute([
                $bookingCode,
                "Pelunasan kasir Rp " . number_format($rem) . " ({$method}) diterima untuk {$bookingCode}."
            ]);

            $this->db->commit();
            return [
                'success' => true,
                'bookingCode' => $bookingCode,
                'amountPaid' => $rem,
                'status' => 'SETTLED'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Pendaftaran reservasi walk-in langsung di meja kasir.
     */
    public function createWalkIn(int $courtId, string $date, int $hour, string $customer, string $phone, bool $isFull): array {
        $this->db->beginTransaction();
        try {
            $stmtCheck = $this->db->prepare("
                SELECT id FROM bookings 
                WHERE court_id = ? AND booking_date = ? AND slot_hour = ? AND status != 'CANCELLED'
                FOR UPDATE
            ");
            $stmtCheck->execute([$courtId, $date, $hour]);
            if ($stmtCheck->fetch()) {
                throw new RuntimeException("Slot jam tersebut sudah terisi!");
            }

            $stmtCourt = $this->db->prepare("SELECT * FROM courts WHERE id = ?");
            $stmtCourt->execute([$courtId]);
            $court = $stmtCourt->fetch();

            $rate = ($hour >= PEAK_HOUR_START) ? (float)$court['peak_rate'] : (float)$court['off_peak_rate'];
            $paidAmt = $isFull ? $rate : ($rate * MIN_DP_PERCENTAGE);
            $remAmt  = $isFull ? 0.0 : ($rate * (1 - MIN_DP_PERCENTAGE));
            $status  = $isFull ? 'SETTLED' : 'DP_PAID';

            $bookingCode = sprintf('FUT-WLK-%s-%02d-%04d', str_replace('-', '', $date), $hour, rand(1000, 9999));

            $stmtIns = $this->db->prepare("
                INSERT INTO bookings (
                    booking_code, court_id, customer_name, phone_number,
                    booking_date, slot_hour, total_price, dp_amount,
                    remaining_amount, status, source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'WALKIN')
            ");
            $stmtIns->execute([
                $bookingCode, $courtId, $customer . ' (Walk-In)', $phone,
                $date, $hour, $rate, $paidAmt, $remAmt, $status
            ]);
            $bookingId = (int)$this->db->lastInsertId();

            $trxRef = 'PAY-WLK-' . date('YmdHis') . '-' . rand(100, 999);
            $payType = $isFull ? 'WALKIN_FULL' : 'DP_ONLINE';
            $stmtPay = $this->db->prepare("
                INSERT INTO payment_transactions (booking_id, transaction_ref, payment_type, amount, payment_method)
                VALUES (?, ?, ?, ?, 'CASH')
            ");
            $stmtPay->execute([$bookingId, $trxRef, $payType, $paidAmt]);

            // Hapus lock jika ada
            $this->db->prepare("DELETE FROM slot_locks WHERE court_id = ? AND booking_date = ? AND slot_hour = ?")->execute([$courtId, $date, $hour]);

            $this->db->commit();
            return [
                'success' => true,
                'bookingCode' => $bookingCode,
                'status' => $status
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mengambil detail e-tiket berdasarkan kode booking.
     */
    public function getTicketByCode(string $code): ?array {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name as court_name, c.type as court_type,
                   (SELECT pt.payment_method FROM payment_transactions pt WHERE pt.booking_id = b.id ORDER BY pt.id ASC LIMIT 1) AS payment_method
            FROM bookings b
            JOIN courts c ON b.court_id = c.id
            WHERE b.booking_code = ?
        ");
        $stmt->execute([$code]);
        $ticket = $stmt->fetch();
        return $ticket ?: null;
    }

    /**
     * Mengambil daftar riwayat pemesanan pelanggan (berdasarkan pencarian nama/HP/kode atau 20 data terbaru).
     */
    public function getBookingHistory(string $search = ''): array {
        $search = trim($search);
        if ($search !== '') {
            $stmt = $this->db->prepare("
                SELECT b.*, c.name as court_name, c.type as court_type,
                       (SELECT pt.payment_method FROM payment_transactions pt WHERE pt.booking_id = b.id ORDER BY pt.id ASC LIMIT 1) AS payment_method
                FROM bookings b
                JOIN courts c ON b.court_id = c.id
                WHERE b.booking_code LIKE ? OR b.phone_number LIKE ? OR b.customer_name LIKE ?
                ORDER BY b.booking_date DESC, b.slot_hour DESC
                LIMIT 50
            ");
            $like = "%{$search}%";
            $stmt->execute([$like, $like, $like]);
        } else {
            $stmt = $this->db->query("
                SELECT b.*, c.name as court_name, c.type as court_type,
                       (SELECT pt.payment_method FROM payment_transactions pt WHERE pt.booking_id = b.id ORDER BY pt.id ASC LIMIT 1) AS payment_method
                FROM bookings b
                JOIN courts c ON b.court_id = c.id
                ORDER BY b.booking_date DESC, b.slot_hour DESC
                LIMIT 30
            ");
        }
        return $stmt->fetchAll();
    }

    /**
     * Mengambil rekapitulasi operasional dan kasir untuk tanggal tertentu.
     */
    public function getCashierOverview(string $date): array {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name as court_name, c.type as court_type
            FROM bookings b
            JOIN courts c ON b.court_id = c.id
            WHERE b.booking_date = ?
            ORDER BY b.slot_hour ASC, b.id ASC
        ");
        $stmt->execute([$date]);
        $bookings = $stmt->fetchAll();

        $totalDP = 0.0;
        $totalSettled = 0.0;
        foreach ($bookings as $b) {
            if ($b['status'] !== 'CANCELLED') {
                $totalDP += (float)$b['dp_amount'];
                if ($b['status'] === 'SETTLED') {
                    $totalSettled += (float)$b['remaining_amount'];
                }
            }
        }

        return [
            'date' => $date,
            'totalBookings' => count($bookings),
            'dpOnline' => $totalDP,
            'cashierPaid' => $totalSettled,
            'totalOmzet' => ($totalDP + $totalSettled),
            'bookings' => $bookings
        ];
    }
}
