<?php
/**
 * Konfigurasi Sistem FutsalHub Arena (Single-Venue)
 */

declare(strict_types=1);

// Konfigurasi Database (MySQL / MariaDB)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'futsal_distributed');
define('DB_USER', 'root');
define('DB_PASS', '');

// Informasi Venue
define('VENUE_NAME', 'FutsalHub Arena');
define('VENUE_BADGE', 'Single-Venue');
define('VENUE_LOCATION', 'Jl. Ring Road Selatan No. 88, Cabang Utama');

// Konfigurasi Jam Operasional & Sesi Waktu (08:00 - 24:00)
define('OPERATING_HOUR_START', 8);
define('OPERATING_HOUR_END', 24); // Selesai jam 24:00 (slot terakhir jam 23:00)
define('PEAK_HOUR_START', 15);    // Sesi Siang (08:00-15:00) & Sesi Malam (15:00-24:00)

// Konfigurasi Sistem Terdistribusi
define('SLOT_LOCK_TTL_SECONDS', 300); // Kunci Slot 5 Menit (300 detik)
define('MIN_DP_PERCENTAGE', 0.50);    // Uang Muka DP 50%

// Kontak WhatsApp Admin Resmi (Gaya Inos Futsal)
define('ADMIN_WHATSAPP_PHONE', '6281234567890');
define('ADMIN_WHATSAPP_DISPLAY', '+62 812-3456-7890');

// Rekening Bank Resmi untuk Pembayaran Transfer
define('BANK_ACCOUNTS', [
    'BCA' => [
        'name' => 'Bank Central Asia (BCA)',
        'number' => '8930182741',
        'holder' => 'FutsalHub Arena'
    ],
    'MANDIRI' => [
        'name' => 'Bank Mandiri',
        'number' => '1370018274190',
        'holder' => 'FutsalHub Arena'
    ]
]);

define('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set(APP_TIMEZONE);

/**
 * Generator URL Chat WhatsApp Otomatis dengan Format Pesan Konfirmasi Booking
 */
function buildWhatsAppBookingUrl(
    string $bookingCode,
    string $customerName,
    string $courtName,
    string $date,
    string $timeLabel,
    float $dpAmount
): string {
    $dpFormatted = 'Rp ' . number_format($dpAmount, 0, ',', '.');
    $text = "Halo Admin " . VENUE_NAME . ",\n\n"
          . "Saya ingin konfirmasi booking lapangan via website:\n"
          . "• *Kode Booking:* {$bookingCode}\n"
          . "• *Nama Pemesan:* {$customerName}\n"
          . "• *Lapangan:* {$courtName}\n"
          . "• *Jadwal:* {$date} | Jam {$timeLabel}\n"
          . "• *Tagihan DP 50%:* {$dpFormatted}\n\n"
          . "Mohon diverifikasi agar slot jadwal kami aktif. Terima kasih!";
    
    return "https://api.whatsapp.com/send?phone=" . ADMIN_WHATSAPP_PHONE . "&text=" . rawurlencode($text);
}
