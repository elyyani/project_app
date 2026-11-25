<?php
/**
 * Setup & Database Migration Tool
 * FutsalHub Arena - Single-Venue Distributed System
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$isCli = (php_sapi_name() === 'cli');

function output(string $msg, string $type = 'info'): void {
    global $isCli;
    if ($isCli) {
        $prefix = match($type) {
            'success' => "[SUCCESS] ",
            'error'   => "[ERROR] ",
            default   => "[INFO] "
        };
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = match($type) {
            'success' => '#059669',
            'error'   => '#dc2626',
            default   => '#1e3a8a'
        };
        echo "<div style='color: {$color}; font-family: monospace; margin: 4px 0;'>&bull; " . htmlspecialchars($msg) . "</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>Setup FutsalHub Arena</title>";
    echo "<body style='font-family: sans-serif; background: #f8fafc; padding: 2rem;'>";
    echo "<div style='max-width: 650px; margin: auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>";
    echo "<h2>🛠️ Setup Database: " . htmlspecialchars(VENUE_NAME) . " (" . htmlspecialchars(VENUE_BADGE) . ")</h2>";
}

try {
    // 1. Koneksi ke MySQL Server
    $dsnServer = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
    $pdoServer = new PDO($dsnServer, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    output("Terhubung ke MySQL Server (" . DB_HOST . ":" . DB_PORT . ")");

    // 2. Buat database jika belum ada
    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    output("Database `" . DB_NAME . "` siap digunakan.", "success");

    // 3. Koneksi ke database target
    $dsnDb = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $db = new PDO($dsnDb, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Reset skema lama jika ada
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("DROP TABLE IF EXISTS payment_transactions, slot_locks, bookings, system_logs, courts;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    output("Skema tabel diperbarui secara bersih.");

    // 4. Tabel courts
    $db->exec("
        CREATE TABLE IF NOT EXISTS courts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            description TEXT NULL,
            off_peak_rate DECIMAL(10, 2) NOT NULL,
            peak_rate DECIMAL(10, 2) NOT NULL,
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB;
    ");
    output("Tabel `courts` (Master Lapangan) diverifikasi.");

    // 5. Tabel slot_locks (Mekanisme Distributed Lock 5 Menit)
    $db->exec("
        CREATE TABLE IF NOT EXISTS slot_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            court_id INT NOT NULL,
            booking_date DATE NOT NULL,
            slot_hour INT NOT NULL,
            locked_by VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_slot_lock (court_id, booking_date, slot_hour),
            INDEX idx_lock_expires (expires_at),
            FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    output("Tabel `slot_locks` (Distributed Concurrency Lock 5 Menit) diverifikasi.");

    // 6. Tabel bookings (Transaksi Booking)
    $db->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_code VARCHAR(32) UNIQUE NOT NULL,
            court_id INT NOT NULL,
            customer_name VARCHAR(100) NOT NULL,
            phone_number VARCHAR(30) NOT NULL,
            booking_date DATE NOT NULL,
            slot_hour INT NOT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            dp_amount DECIMAL(10, 2) NOT NULL,
            remaining_amount DECIMAL(10, 2) NOT NULL,
            status ENUM('DP_PAID', 'SETTLED', 'CANCELLED') NOT NULL DEFAULT 'DP_PAID',
            source ENUM('ONLINE', 'WALKIN') NOT NULL DEFAULT 'ONLINE',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_slot_booking (court_id, booking_date, slot_hour, status),
            INDEX idx_booking_date (booking_date),
            FOREIGN KEY (court_id) REFERENCES courts(id)
        ) ENGINE=InnoDB;
    ");
    output("Tabel `bookings` (Transaksi Pemesanan) diverifikasi.");

    // 7. Tabel payment_transactions
    $db->exec("
        CREATE TABLE IF NOT EXISTS payment_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            transaction_ref VARCHAR(64) UNIQUE NOT NULL,
            payment_type ENUM('DP_ONLINE', 'SETTLEMENT_CASHIER', 'WALKIN_FULL') NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    output("Tabel `payment_transactions` (Audit Finansial) diverifikasi.");

    // 8. Tabel system_logs (Audit Bus Sistem Terdistribusi)
    $db->exec("
        CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            node_name VARCHAR(50) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            reference_code VARCHAR(64) NULL,
            message TEXT NOT NULL,
            logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    output("Tabel `system_logs` (Distributed Event Bus) diverifikasi.");

    // 9. Seeding Data Lapangan
    $countCourts = (int)$db->query("SELECT COUNT(*) FROM courts")->fetchColumn();
    if ($countCourts === 0) {
        $stmtSeed = $db->prepare("
            INSERT INTO courts (id, name, type, description, off_peak_rate, peak_rate) VALUES
            (1, 'Lapangan 1 (Vinyl Indoor)', 'Vinyl', 'Lantai vinyl berkualitas standar turnamen, nyaman untuk kontrol bola.', 120000.00, 160000.00),
            (2, 'Lapangan 2 (Rumput Sintetis)', 'Rumput Sintetis', 'Rumput sintetis halus dengan peredam kejut rubber, cocok untuk permainan cepat.', 130000.00, 170000.00)
        ");
        $stmtSeed->execute();
        output("Data master Lapangan 1 & Lapangan 2 berhasil ditambahkan.", "success");
    } else {
        output("Data master lapangan telah siap ({$countCourts} lapangan).");
    }

    output("Migrasi Database Selesai & Siap Digunakan!", "success");

    if (!$isCli) {
        echo "<hr style='margin: 1.5rem 0; border: none; border-top: 1px solid #e2e8f0;'>";
        echo "<div style='display: flex; gap: 0.75rem;'>";
        echo "<a href='index.php' style='display: inline-block; background: #1e3a8a; color: white; padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold;'>📅 Buka Booking</a>";
        echo "<a href='kasir.php' style='display: inline-block; background: #0284c7; color: white; padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold;'>🖥️ Buka Kasir POS</a>";
        echo "<a href='tiket.html' style='display: inline-block; background: #10b981; color: white; padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold;'>🎫 Buka E-Tiket</a>";
        echo "</div>";
    }

} catch (Exception $e) {
    output("Gagal setup database: " . $e->getMessage(), "error");
}

if (!$isCli) {
    echo "</div></body></html>";
}
