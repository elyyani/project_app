<?php
/**
 * tiket.php
 * Production-Grade Digital E-Ticket - FutsalHub Arena
 * Inspired by Rapi Barbershop (rapi-barber.vercel.app)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingEngine.php';

$engine = new BookingEngine();
$code = trim((string)($_GET['code'] ?? ''));
$ticket = $code ? $engine->getTicketByCode($code) : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Tiket Digital - <?= htmlspecialchars(VENUE_NAME) ?></title>
  
  <!-- Script Inisialisasi Tema (Mencegah Flash/FOUC - Rapi Barber Style) -->
  <script>
    (function() {
      try {
        var savedTheme = localStorage.getItem('futsal_theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var activeTheme = savedTheme || (prefersDark ? 'dark' : 'light');
        if (activeTheme === 'dark') {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }
        document.documentElement.setAttribute('data-theme', activeTheme);
        document.documentElement.style.colorScheme = activeTheme;
      } catch (e) {}
    })();
  </script>

  <!-- Google Fonts: Fraunces, Plus Jakarta Sans, JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..700;1,9..144,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="css/style.css">
  <style>
    @media print {
      .site-header, .site-footer, .no-print {
        display: none !important;
      }
      body {
        background: #fff !important;
        color: #000 !important;
      }
      .ticket-wrapper {
        border: 2px solid #000 !important;
        box-shadow: none !important;
        margin: 0 !important;
        max-width: 100% !important;
      }
      .ticket-header {
        background: #f0f0f0 !important;
        color: #000 !important;
      }
      .ticket-header h2 {
        color: #000 !important;
      }
    }
  </style>
</head>
<body>

  <!-- Floating Sticky Glassmorphic Header (Rapi Barber Style) -->
  <header class="site-header no-print">
    <div class="header-inner">
      <a href="index.php" class="brand-link" aria-label="Beranda FutsalHub Arena">
        <div class="brand-logo-circle">⚽</div>
        <div class="brand-meta">
          <span class="brand-name"><?= htmlspecialchars(VENUE_NAME) ?></span>
          <span class="brand-tagline">Est. Sports House • Turnamen</span>
        </div>
      </a>

      <nav class="nav-menu">
        <a href="index.php#top" class="nav-link-item">Beranda</a>
        <a href="index.php#keunggulan" class="nav-link-item">Keunggulan</a>
        <a href="index.php#lapangan" class="nav-link-item">Jenis Lapangan</a>
        <a href="index.php#bookingFlowSection" class="nav-link-item">Jadwal &amp; Booking</a>
        <a href="riwayat.php" class="nav-link-item">🔍 Cek Booking</a>
      </nav>

      <!-- Action Buttons: Theme Toggle di Pojok Kanan -->
      <div class="header-actions">
        <!-- Mobile Menu Button (Muncul di layar HP) -->
        <button type="button" class="mobile-menu-btn" onclick="toggleMobileMenu()" title="Buka Menu" aria-label="Buka Menu">
          <svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <!-- Theme Toggle Switcher (Pojok Kanan) -->
        <button type="button" id="themeToggleBtn" class="theme-toggle-btn" onclick="toggleTheme()" title="Ganti Tema (Gelap / Terang)">
          <svg id="themeIconSun" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none; color: #f59e0b;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <svg id="themeIconMoon" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: block; color: #8c6218;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Drawer Navigation -->
    <div id="mobileDrawer" class="mobile-drawer">
      <a href="index.php#top" class="nav-link-item">Beranda</a>
      <a href="index.php#keunggulan" class="nav-link-item">Keunggulan</a>
      <a href="index.php#lapangan" class="nav-link-item">Jenis Lapangan</a>
      <a href="index.php#bookingFlowSection" class="nav-link-item">Jadwal &amp; Booking</a>
      <a href="riwayat.php" class="nav-link-item">🔍 Cek Booking</a>
    </div>
  </header>

  <!-- Main Container -->
  <main class="container">

    <div id="ticketContainer">
      <?php if (!$ticket): ?>
        <div class="ticket-wrapper" style="text-align: center; padding: 3.5rem 1.5rem;">
          <div style="font-size: 3rem; margin-bottom: 0.75rem;">🎫</div>
          <h2 style="font-family: var(--font-serif); color: var(--danger-red-text); margin-bottom: 0.5rem;">Tiket Tidak Ditemukan</h2>
          <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;">
            <?= $code ? "Kode reservasi <strong>" . htmlspecialchars($code) . "</strong> tidak terdaftar dalam basis data server kami." : "Silakan masukkan kode booking Anda untuk membuka tiket digital." ?>
          </p>
          <div style="max-width: 380px; margin: auto; display: flex; gap: 0.5rem;">
            <input type="text" id="manualTicketInput" class="text-input" placeholder="Masukkan Kode (Contoh: FUT-...)">
            <button class="btn-gold" onclick="searchManualTicket()" style="white-space: nowrap;">Buka Tiket</button>
          </div>
          <div style="margin-top: 1.5rem;">
            <a href="index.php" class="btn-outline-gold">← Kembali ke Beranda</a>
          </div>
        </div>
      <?php else: 
        $isSettled = ($ticket['status'] === 'SETTLED');
        $startH = sprintf('%02d:00', $ticket['slot_hour']);
        $endH   = sprintf('%02d:00', $ticket['slot_hour'] + 1);
        $timeLabel = "{$startH} - {$endH}";
        $total  = (float)$ticket['total_price'];
        $dp     = (float)$ticket['dp_amount'];
        $rem    = (float)$ticket['remaining_amount'];
        $waUrl  = buildWhatsAppBookingUrl(
            $ticket['booking_code'],
            $ticket['customer_name'],
            $ticket['court_name'],
            $ticket['booking_date'],
            $timeLabel,
            $dp
        );
      ?>
        <div class="ticket-wrapper">
          
          <!-- Ticket Header -->
          <div class="ticket-header">
            <h2>E-TIKET RESERVASI RESMI</h2>
            <p><?= htmlspecialchars(VENUE_NAME) ?> • Est. Sports House</p>
          </div>

          <!-- Ticket Body -->
          <div class="ticket-body">
            
            <!-- QR Code Box -->
            <div class="ticket-qr-container">
              <div id="qrcode" style="display: inline-block;"></div>
              <div style="margin-top: 0.75rem; font-family: var(--font-mono); font-size: 1.35rem; font-weight: 800; color: #110f0d; letter-spacing: 0.05em;">
                <?= htmlspecialchars($ticket['booking_code']) ?>
              </div>
              <div style="font-size: 0.75rem; color: #524b3e; margin-top: 2px;">
                Tunjukkan QR Code ini kepada petugas kasir saat kedatangan di arena
              </div>
            </div>

            <!-- Booking Metadata Grid -->
            <div class="ticket-meta-grid">
              <div class="ticket-meta-item">
                <span class="ticket-meta-label">Nama Pemesan / Tim:</span>
                <span class="ticket-meta-value"><?= htmlspecialchars($ticket['customer_name']) ?></span>
              </div>
              <div class="ticket-meta-item">
                <span class="ticket-meta-label">Nomor WhatsApp:</span>
                <span class="ticket-meta-value"><?= htmlspecialchars($ticket['phone_number']) ?></span>
              </div>
              <div class="ticket-meta-item">
                <span class="ticket-meta-label">Tipe Lapangan:</span>
                <span class="ticket-meta-value" style="color: var(--gold-text);"><?= htmlspecialchars($ticket['court_name']) ?></span>
              </div>
              <div class="ticket-meta-item">
                <span class="ticket-meta-label">Jadwal Pertandingan:</span>
                <span class="ticket-meta-value">
                  📅 <?= htmlspecialchars($ticket['booking_date']) ?> &bull; <strong style="color: var(--gold-text); font-variant-numeric: tabular-nums;">⏰ <?= $timeLabel ?> WIB</strong>
                </span>
              </div>
              <div class="ticket-meta-item">
                <span class="ticket-meta-label">Total Biaya Sewa:</span>
                <span class="ticket-meta-value" style="font-family: var(--font-mono);">Rp <?= number_format($total, 0, ',', '.') ?></span>
              </div>
              <div class="ticket-meta-item">
                <span class="ticket-meta-label">Status Uang Muka (DP 50%):</span>
                <span class="ticket-meta-value" style="color: var(--accent-green-text); font-family: var(--font-mono);">
                  ✓ Rp <?= number_format($dp, 0, ',', '.') ?><?= !empty($ticket['payment_method']) ? ' (' . htmlspecialchars((string)$ticket['payment_method']) . ')' : '' ?>
                </span>
              </div>
              <div class="ticket-meta-item" style="grid-column: span 2; border-top: 1px dashed var(--border); padding-top: 0.65rem;">
                <span class="ticket-meta-label">Sisa Pelunasan di Kasir:</span>
                <span class="ticket-meta-value" style="font-size: 1.15rem; color: var(--warning-amber-text); font-family: var(--font-mono);">
                  <?= $isSettled ? 'Rp 0 (Sudah Lunas)' : 'Rp ' . number_format($rem, 0, ',', '.') ?>
                </span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="no-print" style="display: flex; flex-direction: column; gap: 0.65rem;">
              <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="btn-whatsapp-action">
                📲 Konfirmasi Jadwal via WhatsApp ke Admin
              </a>
              <div style="display: flex; gap: 0.65rem;">
                <button type="button" class="btn-gold" style="flex: 1; justify-content: center;" onclick="window.print()">
                  🖨️ Cetak E-Tiket PDF
                </button>
                <a href="index.php" class="btn-outline-gold" style="flex: 1; justify-content: center; text-decoration: none;">
                  ← Kembali ke Beranda
                </a>
              </div>
            </div>

          </div>
        </div>
      <?php endif; ?>
    </div>

  </main>

  <footer class="site-footer no-print">
    <div class="footer-bottom">
      <div>&copy; <?= date('Y') ?> <?= htmlspecialchars(VENUE_NAME) ?>. Hak Cipta Dilindungi Undang-Undang.</div>
      <div>Sistem Reservasi Futsal Mandiri • Est. Sports House</div>
    </div>
  </footer>

  <script src="js/qrcode.min.js"></script>
  <script>
    function toggleTheme() {
      const isDark = document.documentElement.classList.contains('dark');
      const targetTheme = isDark ? 'light' : 'dark';
      
      if (targetTheme === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
      document.documentElement.setAttribute('data-theme', targetTheme);
      document.documentElement.style.colorScheme = targetTheme;
      localStorage.setItem('futsal_theme', targetTheme);
      syncThemeIcons(targetTheme);
    }

    function syncThemeIcons(theme) {
      const sun = document.getElementById('themeIconSun');
      const moon = document.getElementById('themeIconMoon');
      if (theme === 'dark') {
        if (sun) sun.style.display = 'block';
        if (moon) moon.style.display = 'none';
      } else {
        if (sun) sun.style.display = 'none';
        if (moon) moon.style.display = 'block';
      }
    }

    function toggleMobileMenu() {
      const d = document.getElementById('mobileDrawer');
      if (d) d.classList.toggle('open');
    }

    document.addEventListener('DOMContentLoaded', () => {
      const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
      syncThemeIcons(currentTheme);

      <?php if ($ticket): ?>
        new QRCode(document.getElementById("qrcode"), {
          text: "<?= addslashes($ticket['booking_code']) ?>",
          width: 170,
          height: 170,
          colorDark: "#110f0d",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });
      <?php endif; ?>
    });

    function searchManualTicket() {
      const code = document.getElementById('manualTicketInput').value.trim();
      if (code) {
        window.location.href = `tiket.php?code=${encodeURIComponent(code)}`;
      }
    }
  </script>
</body>
</html>