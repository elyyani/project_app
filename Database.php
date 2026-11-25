<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Mematikan prepared statement emulation untuk keamanan query & locking sejati di InnoDB
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    // Timeout lock transaksi dalam detik
                    PDO::ATTR_TIMEOUT            => 5,
                ]);
            } catch (PDOException $e) {
                // Tampilkan pesan bersahabat jika database belum dibuat
                throw new RuntimeException(
                    "Gagal terkoneksi ke database [" . DB_NAME . "]. " .
                    "Pastikan MySQL berjalan dan jalankan 'setup.php' terlebih dahulu. Error: " . $e->getMessage()
                );
            }
        }

        return self::$instance;
    }
}
