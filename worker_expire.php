<?php
/**
 * Bertugas mencari booking berstatus 'PENDING' yang melewati batas waktu
 * (misal: 15 menit tanpa DP), lalu mengubah statusnya menjadi 'EXPIRED'
 * agar slot jadwal kembali terbuka bagi pelanggan lain.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

$isCli = (php_sapi_name() === 'cli');

class ExpirationWorker {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function run(int $timeoutMinutes = BOOKING_TIMEOUT_MINUTES): array {
        $this->db->beginTransaction();
        $expiredList = [];

        try {
            // Ambil dan kunci semua booking PENDING yang sudah melewati batas waktu
            $stmt = $this->db->prepare("
                SELECT id, booking_code, team_name, created_at 
                FROM bookings 
                WHERE status = 'PENDING' 
                  AND created_at <= (NOW() - INTERVAL ? MINUTE)
                FOR UPDATE
            ");
            $stmt->execute([$timeoutMinutes]);
            $pendingStale = $stmt->fetchAll();

            $stmtExpire = $this->db->prepare("UPDATE bookings SET status = 'EXPIRED' WHERE id = ?");
            $stmtLog = $this->db->prepare("
                INSERT INTO system_logs (node_name, event_type, reference_code, message)
                VALUES ('Node C (Janitor Worker)', 'BOOKING_EXPIRED', ?, ?)
            ");

            foreach ($pendingStale as $item) {
                $stmtExpire->execute([$item['id']]);
                
                $msg = sprintf(
                    "Worker membatalkan otomatis reservasi %s (Tim: %s) karena tidak membayar DP melebihi %d menit.",
                    $item['booking_code'],
                    $item['team_name'],
                    $timeoutMinutes
                );
                $stmtLog->execute([$item['booking_code'], $msg]);

                $expiredList[] = $item['booking_code'];
            }

            $this->db->commit();
            return [
                'success' => true,
                'count'   => count($expiredList),
                'expired_codes' => $expiredList
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}

// Eksekusi jika file dipanggil langsung
$worker = new ExpirationWorker();
try {
    $result = $worker->run();
    if ($isCli) {
        echo "[NODE C WORKER] Selesai dijalankan. {$result['count']} booking kedaluwarsa dibatalkan.\n";
        foreach ($result['expired_codes'] as $code) {
            echo " - {$code} -> EXPIRED (Slot dibuka kembali)\n";
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode($result);
    }
} catch (Exception $e) {
    if ($isCli) {
        echo "[NODE C ERROR] " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
