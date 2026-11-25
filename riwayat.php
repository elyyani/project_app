<?php
/**
 * riwayat.php
 * Cek Status Booking Pelanggan - FutsalHub Arena
 * Inspired by Rapi Barbershop (rapi-barber.vercel.app)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingEngine.php';

$engine = new BookingEngine();
$search = trim((string)($_GET['q'] ?? ''));
$history = $engine->getBookingHistory($search);

// Filter metrics calculation
$countAll = count($history);
$countConfirmed = 0;
$countSettled = 0;
$countCourt1 = 0;
$countCourt2 = 0;
$todayDate = date('Y-m-d');
$countToday = 0;
$countUpcoming = 0;

$daysMap = ['Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu'];
$monthsMap = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

foreach ($history as $h) {
    if ($h['status'] === 'SETTLED') $countSettled++;
    if ($h['status'] === 'CONFIRMED' || $h['status'] === 'DP_PAID') $countConfirmed++;
    if ((int)$h['court_id'] === 1) $countCourt1++;
    if ((int)$h['court_id'] === 2) $countCourt2++;
    if ($h['booking_date'] === $todayDate) $countToday++;
    if ($h['booking_date'] >= $todayDate) $countUpcoming++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cek Status Booking - <?= htmlspecialchars(VENUE_NAME) ?></title>
  
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
</head>
<body>

  <!-- Floating Sticky Glassmorphic Header (Rapi Barber Style) -->
  <header class="site-header">
    <div class="header-inner">
      
      <!-- Brand Logo -->
      <a href="index.php" class="brand-link" aria-label="Beranda FutsalHub Arena">
        <div class="brand-logo-circle">⚽</div>
        <div class="brand-meta">
          <span class="brand-name"><?= htmlspecialchars(VENUE_NAME) ?></span>
          <span class="brand-tagline">Est. Sports House • Turnamen</span>
        </div>
      </a>

      <!-- Navigation Links -->
      <nav class="nav-menu">
        <a href="index.php#top" class="nav-link-item">Beranda</a>
        <a href="index.php#keunggulan" class="nav-link-item">Keunggulan</a>
        <a href="index.php#lapangan" class="nav-link-item">Jenis Lapangan</a>
        <a href="index.php#bookingFlowSection" class="nav-link-item">Jadwal &amp; Booking</a>
        <a href="riwayat.php" class="nav-link-item active">🔍 Cek Booking</a>
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
      <a href="riwayat.php" class="nav-link-item active">🔍 Cek Booking</a>
    </div>
  </header>

  <!-- Main Container -->
  <main class="container" style="margin-top: 2.5rem; margin-bottom: 4rem;">

    <div style="margin-bottom: 2rem;">
      <span class="section-tag">Pelacakan Jadwal</span>
      <h1 class="section-heading-serif">🔍 Cek Status Booking Lapangan</h1>
      <p class="section-desc-subtle">
        Masukkan Kode Booking resmi (contoh: FUT-...) atau nomor WhatsApp Anda untuk melihat rincian jadwal, uang muka DP 50%, dan membuka e-tiket digital.
      </p>
    </div>

    <!-- Filter & Search Control Bar (ProMax Neumorphic Soft UI) -->
    <div class="filter-bar-card">
      
      <!-- Search Input Row -->
      <form method="GET" action="riwayat.php" onsubmit="event.preventDefault(); applyFilters();" style="display: flex; gap: 0.75rem; width: 100%; flex-wrap: wrap; align-items: center;">
        <div style="position: relative; flex: 1; min-width: 260px;">
          <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1rem; color: var(--text-muted); pointer-events: none;">🔍</span>
          <input 
            type="text" 
            id="filterSearchInput"
            name="q"
            class="text-input" 
            placeholder="Cari Kode Booking (FUT-...), Nama Pemesan, atau Nomor WhatsApp..." 
            value="<?= htmlspecialchars($search) ?>"
            oninput="handleSearchInput(this.value)"
            style="width: 100%; height: 46px; padding-left: 2.75rem; font-size: 0.9rem;"
          >
        </div>
        <button type="submit" class="btn-gold" style="height: 46px; padding: 0 1.5rem; font-size: 0.9rem;">
          🔍 Cari Jadwal
        </button>
        <?php if ($search !== ''): ?>
          <a href="riwayat.php" class="btn-outline-gold" style="height: 46px; padding: 0 1.25rem;">
            Tampilkan Semua
          </a>
        <?php endif; ?>
      </form>

      <!-- Filter Bar Dropdowns (Clean ProMax Dropdowns) -->
      <div class="filter-dropdowns-row">
        
        <!-- Dropdown 1: Status Pemesanan -->
        <div class="filter-select-group">
          <label for="filterStatusSelect" class="filter-select-label">
            <span>Status Reservasi:</span>
          </label>
          <div class="custom-select-wrapper">
            <select id="filterStatusSelect" class="filter-dropdown-select" onchange="handleDropdownFilterChange()">
              <option value="ALL">Semua Status (<?= $countAll ?>)</option>
              <option value="DP_PAID">⏳ DP 50% Aktif (<?= $countConfirmed ?>)</option>
              <option value="SETTLED">✔ Lunas (<?= $countSettled ?>)</option>
            </select>
          </div>
        </div>

        <!-- Dropdown 2: Jenis Lapangan -->
        <div class="filter-select-group">
          <label for="filterCourtSelect" class="filter-select-label">
            <span>Jenis Lapangan:</span>
          </label>
          <div class="custom-select-wrapper">
            <select id="filterCourtSelect" class="filter-dropdown-select" onchange="handleDropdownFilterChange()">
              <option value="ALL">Semua Lapangan (<?= $countAll ?>)</option>
              <option value="1">⚽ Lapangan 1 - Vinyl (<?= $countCourt1 ?>)</option>
              <option value="2">🌱 Lapangan 2 - Rumput Sintetis (<?= $countCourt2 ?>)</option>
            </select>
          </div>
        </div>

        <!-- Dropdown 3: Jadwal / Waktu -->
        <div class="filter-select-group">
          <label for="filterDateSelect" class="filter-select-label">
            <span>Waktu / Jadwal:</span>
          </label>
          <div class="custom-select-wrapper">
            <select id="filterDateSelect" class="filter-dropdown-select" onchange="handleDropdownFilterChange()">
              <option value="ALL">Semua Jadwal Tanggal</option>
              <option value="TODAY">📅 Hari Ini (<?= $countToday ?>)</option>
              <option value="UPCOMING">🚀 Jadwal Mendatang (<?= $countUpcoming ?>)</option>
            </select>
          </div>
        </div>

      </div>

      <!-- Filter Meta Info Bar -->
      <div class="filter-meta-bar">
        <div>
          Menampilkan <strong id="visibleCountBadge" style="color: var(--gold-text); font-family: var(--font-mono); font-size: 1.05rem;"><?= $countAll ?></strong> dari <strong><?= $countAll ?></strong> reservasi
        </div>
        <div>
          <button type="button" id="btnResetFilter" class="btn-outline-gold" onclick="resetAllFilters()" style="display: none; padding: 0.35rem 0.85rem; font-size: 0.8rem;">
            ✕ Reset Filter
          </button>
        </div>
      </div>

    </div>

    <!-- Results Section -->
    <section>
      <?php if (empty($history)): ?>
        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 3.5rem 1.5rem; text-align: center; box-shadow: var(--shadow-sm);">
          <div style="font-size: 3rem; margin-bottom: 0.75rem;">🔍</div>
          <h3 style="font-family: var(--font-serif); font-size: 1.4rem; color: var(--text-main); margin-bottom: 0.5rem;">Data Booking Tidak Ditemukan</h3>
          <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 1.75rem auto; font-size: 0.9rem; line-height: 1.6;">
            <?= $search !== '' 
              ? "Tidak ada jadwal booking yang cocok dengan kata kunci '<strong>" . htmlspecialchars($search) . "</strong>'. Pastikan nomor WhatsApp atau kode booking yang Anda masukkan sudah benar." 
              : "Belum ada pesanan yang tercatat dalam sistem. Silakan lakukan reservasi lapangan terlebih dahulu." ?>
          </p>
          <a href="index.php#bookingFlowSection" class="btn-gold">
            📅 Lihat Jadwal &amp; Booking Sekarang
          </a>
        </div>
      <?php else: ?>
        <div id="bookingCardsContainer" style="display: flex; flex-direction: column; gap: 1.25rem;">
          <?php foreach ($history as $item): 
            $startH = sprintf('%02d:00', $item['slot_hour']);
            $endH   = sprintf('%02d:00', $item['slot_hour'] + 1);
            $timeLabel = "{$startH} - {$endH}";
            $isSettled = ($item['status'] === 'SETTLED');
            $waUrl = buildWhatsAppBookingUrl(
                $item['booking_code'],
                $item['customer_name'],
                $item['court_name'],
                $item['booking_date'],
                $timeLabel,
                (float)$item['dp_amount']
            );
            $ts = strtotime($item['booking_date']);
            $dayName = $daysMap[date('D', $ts)] ?? date('D', $ts);
            $dNum = date('d', $ts);
            $mStr = $monthsMap[date('m', $ts)] ?? date('M', $ts);
            $yNum = date('Y', $ts);
            $formattedDate = "{$dayName}, {$dNum} {$mStr} {$yNum}";
            $searchBlob = strtolower($item['booking_code'] . ' ' . $item['customer_name'] . ' ' . $item['phone_number'] . ' ' . $item['court_name'] . ' ' . $item['booking_date'] . ' ' . $timeLabel . ' ' . $formattedDate);
          ?>
            <div class="history-card"
                 data-status="<?= $item['status'] ?>"
                 data-court-id="<?= $item['court_id'] ?>"
                 data-date="<?= $item['booking_date'] ?>"
                 data-search="<?= htmlspecialchars($searchBlob) ?>">
              
              <!-- Header Card: Booking Code & Status Pill -->
              <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; border-bottom: 1px dashed var(--border); padding-bottom: 0.85rem;">
                <div>
                  <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.25rem;">
                    <span style="font-family: var(--font-mono); font-size: 1.25rem; font-weight: 800; color: var(--gold-text); letter-spacing: 0.04em;"><?= htmlspecialchars($item['booking_code']) ?></span>
                    <button type="button" class="btn-outline-gold" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($item['booking_code']) ?>'); alert('Kode booking <?= htmlspecialchars($item['booking_code']) ?> berhasil disalin!');" style="padding: 2px 8px; font-size: 0.75rem;">
                      Salin
                    </button>
                  </div>
                  <div style="font-size: 0.8rem; color: var(--text-muted);">Dibuat: <?= htmlspecialchars($item['created_at']) ?></div>
                </div>
                <div>
                  <?php if ($isSettled): ?>
                    <span class="status-badge-settled">✔ Lunas (100%)</span>
                  <?php else: ?>
                    <span class="status-badge-dp">⏳ DP 50% Aktif</span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Standout Schedule Highlight Box (Font Jam Rapi & Elegan) -->
              <div class="schedule-highlight-box">
                <div class="time-slot-badge" title="Jam Main">
                  <span>⏰</span>
                  <span class="time-digit"><?= $timeLabel ?></span>
                  <span class="time-tz">WIB</span>
                </div>
                <div class="date-highlight-badge" title="Tanggal Main">
                  <span>📅</span>
                  <span><?= htmlspecialchars($formattedDate) ?></span>
                </div>
                <div class="court-type-pill <?= (int)$item['court_id'] === 1 ? 'vinyl' : 'grass' ?>" title="Jenis Lapangan">
                  <span><?= (int)$item['court_id'] === 1 ? '⚽' : '🌱' ?></span>
                  <span><?= htmlspecialchars($item['court_name']) ?></span>
                </div>
              </div>

              <!-- Metadata Grid -->
              <div class="ticket-meta-grid" style="margin-bottom: 1.25rem;">
                <div class="ticket-meta-item">
                  <span class="ticket-meta-label">Nama Pemesan:</span>
                  <span class="ticket-meta-value"><?= htmlspecialchars($item['customer_name']) ?></span>
                </div>
                <div class="ticket-meta-item">
                  <span class="ticket-meta-label">Nomor WhatsApp:</span>
                  <span class="ticket-meta-value" style="font-family: var(--font-mono); font-size: 0.9rem;"><?= htmlspecialchars($item['phone_number']) ?></span>
                </div>
                <div class="ticket-meta-item">
                  <span class="ticket-meta-label">Uang Muka DP 50%:</span>
                  <span class="ticket-meta-value" style="color: var(--accent-green-text); font-family: var(--font-mono);">
                    ✓ Rp <?= number_format((float)$item['dp_amount'], 0, ',', '.') ?><?= !empty($item['payment_method']) ? ' (' . htmlspecialchars((string)$item['payment_method']) . ')' : '' ?>
                  </span>
                </div>
                <div class="ticket-meta-item">
                  <span class="ticket-meta-label">Sisa Pelunasan di Kasir:</span>
                  <span class="ticket-meta-value" style="color: var(--warning-amber-text); font-family: var(--font-mono); font-size: 1.05rem;">
                    <?= $isSettled ? 'Rp 0 (Sudah Lunas)' : 'Rp ' . number_format((float)$item['remaining_amount'], 0, ',', '.') ?>
                  </span>
                </div>
              </div>

              <!-- Action Links -->
              <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="btn-wa-action" style="padding: 0.6rem 1.25rem; font-size: 0.875rem;">
                  💬 Konfirmasi WA Admin
                </a>
                <a href="tiket.php?code=<?= urlencode($item['booking_code']) ?>" class="btn-gold" style="padding: 0.6rem 1.25rem; font-size: 0.875rem; text-decoration: none;">
                  🎫 Buka E-Tiket Resmi
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Zero Results from Filtering Placeholder -->
        <div id="noFilterResults" style="display: none; background: var(--bg-card); border: 1px dashed var(--border); border-radius: var(--radius-xl); padding: 3rem 1.5rem; text-align: center; box-shadow: var(--shadow-sm); margin-top: 1rem;">
          <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🔍</div>
          <h3 style="font-family: var(--font-serif); font-size: 1.25rem; color: var(--text-main); margin-bottom: 0.5rem;">Tidak Ada Jadwal yang Cocok</h3>
          <p style="color: var(--text-muted); max-width: 420px; margin: 0 auto 1.25rem auto; font-size: 0.875rem; line-height: 1.5;">
            Tidak ditemukan data reservasi yang sesuai dengan kombinasi filter yang Anda pilih.
          </p>
          <button type="button" class="btn-outline-gold" onclick="resetAllFilters()">
            ✕ Reset Semua Filter
          </button>
        </div>
      <?php endif; ?>
    </section>

  </main>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="footer-bottom">
      <div>&copy; <?= date('Y') ?> <?= htmlspecialchars(VENUE_NAME) ?>. Hak Cipta Dilindungi Undang-Undang.</div>
      <div>Sistem Reservasi Futsal Mandiri • Est. Sports House</div>
    </div>
  </footer>

  <script>
    // Filter State Management (ProMax Realtime Dropdown Filtering)
    let activeFilters = {
      status: 'ALL',
      court: 'ALL',
      date: 'ALL',
      search: ''
    };

    function handleDropdownFilterChange() {
      const statusSelect = document.getElementById('filterStatusSelect');
      const courtSelect = document.getElementById('filterCourtSelect');
      const dateSelect = document.getElementById('filterDateSelect');

      if (statusSelect) activeFilters.status = statusSelect.value;
      if (courtSelect) activeFilters.court = courtSelect.value;
      if (dateSelect) activeFilters.date = dateSelect.value;

      applyFilters();
    }

    function handleSearchInput(val) {
      activeFilters.search = val.trim().toLowerCase();
      applyFilters();
    }

    function resetAllFilters() {
      activeFilters = { status: 'ALL', court: 'ALL', date: 'ALL', search: '' };
      
      const statusSelect = document.getElementById('filterStatusSelect');
      const courtSelect = document.getElementById('filterCourtSelect');
      const dateSelect = document.getElementById('filterDateSelect');
      const searchInput = document.getElementById('filterSearchInput');

      if (statusSelect) statusSelect.value = 'ALL';
      if (courtSelect) courtSelect.value = 'ALL';
      if (dateSelect) dateSelect.value = 'ALL';
      if (searchInput) searchInput.value = '';

      applyFilters();
    }

    function applyFilters() {
      const cards = document.querySelectorAll('.history-card');
      const todayStr = '<?= date("Y-m-d") ?>';
      let visibleCount = 0;

      cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status');
        const cardCourtId = card.getAttribute('data-court-id');
        const cardDate = card.getAttribute('data-date');
        const cardSearchText = card.getAttribute('data-search') || '';

        // Status match
        let matchStatus = (activeFilters.status === 'ALL') || 
                          (cardStatus === activeFilters.status) || 
                          (activeFilters.status === 'DP_PAID' && (cardStatus === 'DP_PAID' || cardStatus === 'CONFIRMED'));

        // Court match
        let matchCourt = (activeFilters.court === 'ALL') || (cardCourtId === activeFilters.court);

        // Date match
        let matchDate = true;
        if (activeFilters.date === 'TODAY') {
          matchDate = (cardDate === todayStr);
        } else if (activeFilters.date === 'UPCOMING') {
          matchDate = (cardDate >= todayStr);
        }

        // Search text match
        let matchSearch = true;
        if (activeFilters.search) {
          matchSearch = cardSearchText.includes(activeFilters.search);
        }

        if (matchStatus && matchCourt && matchDate && matchSearch) {
          card.style.display = 'block';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });

      const countBadge = document.getElementById('visibleCountBadge');
      if (countBadge) countBadge.innerText = visibleCount;

      const noResultsBox = document.getElementById('noFilterResults');
      if (noResultsBox) {
        if (visibleCount === 0 && cards.length > 0) {
          noResultsBox.style.display = 'block';
        } else {
          noResultsBox.style.display = 'none';
        }
      }

      const isFiltered = (activeFilters.status !== 'ALL' || activeFilters.court !== 'ALL' || activeFilters.date !== 'ALL' || activeFilters.search !== '');
      const resetBtn = document.getElementById('btnResetFilter');
      if (resetBtn) resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }

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
    });
  </script>
</body>
</html>