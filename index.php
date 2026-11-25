<?php
/**
 * index.php
 * Production-Grade Customer Booking Portal - FutsalHub Arena
 * Inspired by Rapi Barbershop (rapi-barber.vercel.app)
 * Real Database Concurrency, 5-Minute Locks, DP 50%, Dual Dark/Light Theme System.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingEngine.php';

$engine = new BookingEngine();
$courts = $engine->getCourts();
$today = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+6 days'));

// Data 7 Hari ke Depan (1 Tanggal 1 Button)
$daysAhead = [];
$namaHariIndo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$namaBulanIndo = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

for ($i = 0; $i < 7; $i++) {
    $time = strtotime("+{$i} days");
    $dVal = date('Y-m-d', $time);
    $dEng = date('l', $time);
    $dIndo = $namaHariIndo[$dEng] ?? $dEng;
    $dNum = date('j', $time);
    $mNum = (int)date('n', $time);
    $mIndo = $namaBulanIndo[$mNum] ?? date('M', $time);

    $daysAhead[] = [
        'date' => $dVal,
        'dayLabel' => ($i === 0) ? 'Hari Ini' : (($i === 1) ? 'Besok' : $dIndo),
        'dateLabel' => "{$dNum} {$mIndo}",
        'isToday' => ($i === 0)
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(VENUE_NAME) ?> - Sewa Lapangan Futsal Standar Turnamen</title>
  
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
        <div class="brand-logo-circle">
          ⚽
        </div>
        <div class="brand-meta">
          <span class="brand-name"><?= htmlspecialchars(VENUE_NAME) ?></span>
          <span class="brand-tagline">Est. Sports House • Turnamen</span>
        </div>
      </a>

      <!-- Navigation Links -->
      <nav class="nav-menu">
        <a href="#top" class="nav-link-item active" data-nav="top">Beranda</a>
        <a href="#keunggulan" class="nav-link-item" data-nav="keunggulan">Keunggulan</a>
        <a href="#lapangan" class="nav-link-item" data-nav="lapangan">Jenis Lapangan</a>
        <a href="#bookingFlowSection" class="nav-link-item" data-nav="bookingFlowSection">Jadwal &amp; Booking</a>
        <a href="riwayat.php" class="nav-link-item" data-nav="riwayat">🔍 Cek Booking</a>
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
          <!-- Sun Icon (Tampil saat mode gelap) -->
          <svg id="themeIconSun" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none; color: #f59e0b;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <!-- Moon Icon (Tampil saat mode terang) -->
          <svg id="themeIconMoon" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: block; color: #8c6218;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
          </svg>
        </button>
      </div>

    </div>

    <!-- Mobile Drawer Navigation -->
    <div id="mobileDrawer" class="mobile-drawer">
      <a href="#top" class="nav-link-item active" data-nav="top" onclick="toggleMobileMenu()">Beranda</a>
      <a href="#keunggulan" class="nav-link-item" data-nav="keunggulan" onclick="toggleMobileMenu()">Keunggulan</a>
      <a href="#lapangan" class="nav-link-item" data-nav="lapangan" onclick="toggleMobileMenu()">Jenis Lapangan</a>
      <a href="#bookingFlowSection" class="nav-link-item" data-nav="bookingFlowSection" onclick="toggleMobileMenu()">Jadwal &amp; Booking</a>
      <a href="riwayat.php" class="nav-link-item" data-nav="riwayat">🔍 Cek Booking</a>
    </div>
  </header>

  <!-- HERO SECTION (Cinematic Editorial Full Bleed ala Rapi Barber) -->
  <section class="hero-editorial" id="top">
    <div class="hero-bg-overlay">
      <img 
        src="https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=2000&q=80" 
        alt="Arena Futsal Standar Turnamen Internasional" 
        class="hero-bg-img"
      >
      <div class="hero-gradient-overlay"></div>
    </div>

    <div class="hero-content-wrapper">
      <div class="hero-badge-pill">
        ⚽ Arena Futsal Standar Turnamen • Reservasi Real-Time
      </div>

      <h1 class="hero-title-editorial">
        Arena Futsal Standar Turnamen,<br>
        Pilih Jadwal &amp; <em class="hero-italic">Booking Langsung.</em>
      </h1>

      <div class="hero-bottom-row">
        <p class="hero-desc-text">
          <?= htmlspecialchars(VENUE_NAME) ?> menyediakan fasilitas lapangan futsal berstandar turnamen dengan pilihan lantai <strong>Vinyl Interlock</strong> dan <strong>Rumput Sintetis 50mm</strong>. Cek jadwal ketersediaan secara langsung, kunci slot otomatis 5 menit, dan nikmati kemudahan pembayaran DP secara aman.
        </p>

        <div class="hero-cta-buttons">
          <a href="#bookingFlowSection" class="btn-gold">
            ⚽ Booking Lapangan Sekarang
          </a>
        </div>
      </div>
    </div>

    <!-- Marquee Ticker Tape (Rapi Barbershop Signature Ribbon) -->
    <div class="marquee-bar" aria-hidden="true">
      <div class="marquee-track">
        <span>Jenis Lapangan Pilihan <i class="gold-dot">●</i></span>
        <span>Lantai Vinyl Turnamen <i class="gold-dot">●</i></span>
        <span>Rumput Sintetis 50mm <i class="gold-dot">●</i></span>
        <span>LED Stadium 800 Lux <i class="gold-dot">●</i></span>
        <span>Kunci Slot 5 Menit <i class="gold-dot">●</i></span>
        <span>Reservasi Tanpa Antre <i class="gold-dot">●</i></span>
        <span>Uang Muka DP 50% <i class="gold-dot">●</i></span>
        <span>Konfirmasi WhatsApp Instan <i class="gold-dot">●</i></span>
        <span>Sesi Siang &amp; Sesi Malam <i class="gold-dot">●</i></span>

        <span>Jenis Lapangan Pilihan <i class="gold-dot">●</i></span>
        <span>Lantai Vinyl Turnamen <i class="gold-dot">●</i></span>
        <span>Rumput Sintetis 50mm <i class="gold-dot">●</i></span>
        <span>LED Stadium 800 Lux <i class="gold-dot">●</i></span>
        <span>Kunci Slot 5 Menit <i class="gold-dot">●</i></span>
        <span>Reservasi Tanpa Antre <i class="gold-dot">●</i></span>
        <span>Uang Muka DP 50% <i class="gold-dot">●</i></span>
        <span>Konfirmasi WhatsApp Instan <i class="gold-dot">●</i></span>
        <span>Sesi Siang &amp; Sesi Malam <i class="gold-dot">●</i></span>
      </div>
    </div>
  </section>

  <!-- Main Container -->
  <main class="container">

    <!-- SECTION KEUNGGULAN (Standar Kualitas 01 - 04 ala Rapi Barber) -->
    <section id="keunggulan" class="section-keunggulan">
      <div>
        <span class="section-tag">Standar Kualitas</span>
        <h2 class="section-heading-serif">
          Kenapa mempercayakan pertandingan timmu di <?= htmlspecialchars(VENUE_NAME) ?>?
        </h2>
        <p class="section-desc-subtle">
          Setiap detail arena dirancang sesuai spesifikasi kompetisi resmi untuk menghadirkan kenyamanan, keselamatan, dan atmosfer tanding maksimal.
        </p>
      </div>

      <div class="keunggulan-grid">
        <!-- 01 -->
        <div class="keunggulan-card">
          <span class="keunggulan-number">01</span>
          <h3 class="keunggulan-title">Pilihan Jenis Lapangan</h3>
          <p class="keunggulan-desc">
            Pilihan Lapangan Vinyl Interlock anti-slip standar turnamen untuk operan taktis cepat, serta Rumput Sintetis Monofilament 50mm yang empuk meredam sendi lutut.
          </p>
          <div class="keunggulan-tag-bottom">
            Standar Uji Kompetisi
          </div>
        </div>

        <!-- 02 -->
        <div class="keunggulan-card">
          <span class="keunggulan-number">02</span>
          <h3 class="keunggulan-title">LED Stadium 800 Lux</h3>
          <p class="keunggulan-desc">
            Pencahayaan lampu sport stadium 800 Lux merata ke seluruh sudut lapangan tanpa bayangan gelap atau silau mata, siap untuk tanding malam hari hingga 24:00 WIB.
          </p>
          <div class="keunggulan-tag-bottom">
            Visibilitas Maksimal Turnamen
          </div>
        </div>

        <!-- 03 -->
        <div class="keunggulan-card">
          <span class="keunggulan-number">03</span>
          <h3 class="keunggulan-title">Fasilitas Pemain Lengkap</h3>
          <p class="keunggulan-desc">
            Kamar bilas shower air bersih, toilet higienis, loker barang aman, musholla nyaman, kantin minuman isotonik dingin, serta area parkir luas ber-CCTV 24 jam.
          </p>
          <div class="keunggulan-tag-bottom">
            Shower, Musholla &amp; Parkir CCTV
          </div>
        </div>

        <!-- 04 -->
        <div class="keunggulan-card">
          <span class="keunggulan-number">04</span>
          <h3 class="keunggulan-title">Smart Hold 5 Menit</h3>
          <p class="keunggulan-desc">
            Sistem penguncian slot otomatis 5 menit berbasis server concurrency lock. Anti double-booking, bayar DP 50% online fleksibel, dan konfirmasi tiket instan ke WhatsApp.
          </p>
          <div class="keunggulan-tag-bottom">
            Sistem Anti Double-Booking
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION JENIS LAPANGAN (Katalog Layanan / Pricelist ala Rapi Barber) -->
    <section id="lapangan" class="section-courts-catalog">
      <div>
        <span class="section-tag">Arena Futsal Kami</span>
        <h2 class="section-heading-serif">Pilihan Jenis Lapangan Standar Internasional</h2>
        <p class="section-desc-subtle">
          Pilih jenis permukaan lapangan yang sesuai dengan gaya permainan dan strategi tim Anda hari ini.
        </p>
      </div>

      <div class="courts-catalog-grid">
        
        <!-- Court 1: Vinyl Indoor -->
        <div class="court-catalog-card">
          <div>
            <div class="court-photo-container">
              <img 
                src="https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80" 
                alt="Lapangan 1 Vinyl Indoor Standar Turnamen" 
                class="court-photo-img"
              >
              <span class="court-chip-category">Tipe 1 • Vinyl Indoor</span>
              <span class="court-chip-price">Mulai Rp 120.000 / jam</span>
            </div>

            <div class="court-body-content">
              <h3 class="court-title-serif">Lapangan 1 (Vinyl Indoor Turnamen)</h3>
              <div class="court-rate-highlight">
                Rp 120.000 <span class="court-rate-session-info">(Siang 08:00–15:00)</span> / Rp 160.000 <span class="court-rate-session-info">(Malam)</span>
              </div>
              <p class="court-specs-text">
                Lantai <strong>Interlocking Polypropylene Anti-Slip</strong> standar kompetisi nasional. Cengkeraman sepatu karet sangat mantap untuk akselerasi sprint cepat, pivot, dan passing akurat.
              </p>
              <div class="court-features-list">
                <div>✓ <strong>Struktur:</strong> Full indoor beratap baja ringan, bebas hujan &amp; terik panas matahari.</div>
                <div>✓ <strong>Pencahayaan:</strong> 12 Unit LED Sport Stadium 800 Lux merata bebas silau.</div>
                <div>✓ <strong>Scoreboard:</strong> Papan skor digital elektronik &amp; sound system nirkabel.</div>
              </div>
            </div>
          </div>

          <div class="court-action-footer">
            <button type="button" class="btn-gold" style="width: 100%; justify-content: center;" onclick="selectCourtDirectly(1)">
              Pilih Lapangan 1 &amp; Cek Jadwal →
            </button>
          </div>
        </div>

        <!-- Court 2: Rumput Sintetis -->
        <div class="court-catalog-card">
          <div>
            <div class="court-photo-container">
              <img 
                src="https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=800&q=80" 
                alt="Lapangan 2 Rumput Sintetis Monofilament 50mm" 
                class="court-photo-img"
              >
              <span class="court-chip-category">Tipe 2 • Rumput Sintetis</span>
              <span class="court-chip-price">Mulai Rp 130.000 / jam</span>
            </div>

            <div class="court-body-content">
              <h3 class="court-title-serif">Lapangan 2 (Rumput Sintetis Premium)</h3>
              <div class="court-rate-highlight">
                Rp 130.000 <span class="court-rate-session-info">(Siang 08:00–15:00)</span> / Rp 170.000 <span class="court-rate-session-info">(Malam)</span>
              </div>
              <p class="court-specs-text">
                Permukaan <strong>Rumput Sintetis Monofilament 50mm</strong> dengan infill pasir silika kering dan <em>rubber granules</em> halus. Memberikan pantulan bola stabil dan aman meredam benturan saat sliding.
              </p>
              <div class="court-features-list">
                <div>✓ <strong>Karakteristik:</strong> Semi-outdoor kanopi sirkulasi udara segar alami.</div>
                <div>✓ <strong>Pengaman:</strong> Jaring keliling double-mesh rapat setinggi 6 meter.</div>
                <div>✓ <strong>Tribun:</strong> Area duduk tribun penonton tepi lapangan kapasitas 50 orang.</div>
              </div>
            </div>
          </div>

          <div class="court-action-footer">
            <button type="button" class="btn-gold" style="width: 100%; justify-content: center;" onclick="selectCourtDirectly(2)">
              Pilih Lapangan 2 &amp; Cek Jadwal →
            </button>
          </div>
        </div>

      </div>
    </section>

    <!-- SECTION ALUR & JADWAL RESERVASI INTERAKTIF -->
    <section id="bookingFlowSection" class="booking-section-wrapper">
      
      <div style="margin-bottom: 1.5rem;">
        <span class="section-tag">Jadwal &amp; Ketersediaan</span>
        <h2 class="section-heading-serif">Alur Reservasi Lapangan Mandiri</h2>
        <p class="section-desc-subtle">
          Ikuti 3 langkah mudah: Pilih Jenis Lapangan → Tentukan Tanggal Main → Klik Jam Main untuk Kunci Slot 5 Menit.
        </p>
      </div>

      <!-- Step Badges Navigator (Orange berpindah dinamis mengikuti langkah aktif) -->
      <div class="steps-nav-bar">
        <div class="step-indicator-pill active" id="stepPill1" onclick="jumpToStep(1)" style="cursor: pointer;" title="Klik untuk ke Langkah 1: Pilih Lapangan">
          <span class="step-circle-num">1</span>
          <span>Pilih Lapangan</span>
        </div>
        <span style="color: var(--text-muted);">→</span>
        <div class="step-indicator-pill" id="stepPill2" onclick="jumpToStep(2)" style="cursor: pointer;" title="Klik untuk ke Langkah 2: Pilih Tanggal">
          <span class="step-circle-num">2</span>
          <span>Pilih Tanggal</span>
        </div>
        <span style="color: var(--text-muted);">→</span>
        <div class="step-indicator-pill" id="stepPill3" onclick="jumpToStep(3)" style="cursor: pointer;" title="Klik untuk ke Langkah 3: Kunci Jam">
          <span class="step-circle-num">3</span>
          <span>Kunci Jam (5 Menit)</span>
        </div>
        <span style="color: var(--text-muted);">→</span>
        <div class="step-indicator-pill" id="stepPill4">
          <span class="step-circle-num">4</span>
          <span>Pembayaran DP 50%</span>
        </div>
        <span style="color: var(--text-muted);">→</span>
        <div class="step-indicator-pill" id="stepPill5">
          <span class="step-circle-num">5</span>
          <span>Tiket &amp; Riwayat</span>
        </div>
      </div>

      <!-- STEP 1: INTERACTIVE 2-COURT SELECTOR -->
      <div id="stepSectionCourt" style="margin-bottom: 1.5rem;" onclick="updateStepPills(1)">
        <label class="control-label" style="margin-bottom: 0.6rem; display: block;">
          Langkah 1: Klik untuk Memilih Salah Satu Jenis Lapangan
        </label>
        
        <div class="court-selection-row">
          
          <!-- Card Court 1 -->
          <div id="cardCourt1" class="court-toggle-card active" onclick="selectCourtDirectly(1)">
            <div class="court-toggle-header">
              <div>
                <span class="section-tag" style="margin-bottom: 0.2rem; font-size: 0.7rem;">Lantai Vinyl</span>
                <h4 style="font-family: var(--font-serif); font-size: 1.15rem; font-weight: 600; color: var(--text-main);">
                  Lapangan 1 (Vinyl Indoor)
                </h4>
              </div>
              <div class="court-check-circle">✓</div>
            </div>
            <div style="font-size: 0.825rem; color: var(--text-muted); line-height: 1.5;">
              Lantai interlocking anti-slip standar turnamen, empuk meredam lutut &amp; nyaman kontrol bola.
            </div>
            <div style="margin-top: 0.4rem; padding-top: 0.6rem; border-top: 1px dashed var(--border); font-size: 0.8rem; color: var(--gold-text); font-weight: 700;">
              Siang: Rp 120.000 / jam • Malam: Rp 160.000 / jam
            </div>
          </div>

          <!-- Card Court 2 -->
          <div id="cardCourt2" class="court-toggle-card" onclick="selectCourtDirectly(2)">
            <div class="court-toggle-header">
              <div>
                <span class="section-tag" style="margin-bottom: 0.2rem; font-size: 0.7rem;">Rumput Sintetis</span>
                <h4 style="font-family: var(--font-serif); font-size: 1.15rem; font-weight: 600; color: var(--text-main);">
                  Lapangan 2 (Rumput Sintetis)
                </h4>
              </div>
              <div class="court-check-circle">✓</div>
            </div>
            <div style="font-size: 0.825rem; color: var(--text-muted); line-height: 1.5;">
              Rumput monofilament 50mm + rubber granules, pantulan bola stabil &amp; aman saat sliding tackle.
            </div>
            <div style="margin-top: 0.4rem; padding-top: 0.6rem; border-top: 1px dashed var(--border); font-size: 0.8rem; color: var(--gold-text); font-weight: 700;">
              Siang: Rp 130.000 / jam • Malam: Rp 170.000 / jam
            </div>
          </div>

        </div>

        <!-- Hidden select for form synchronization -->
        <select id="courtSelect" style="display: none;" onchange="syncCourtCards()">
          <?php foreach ($courts as $c): ?>
            <option value="<?= $c['id'] ?>" data-offpeak="<?= $c['off_peak_rate'] ?>" data-peak="<?= $c['peak_rate'] ?>" data-desc="<?= htmlspecialchars($c['description'] ?? '') ?>">
              <?= htmlspecialchars($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- STEP 2: DATE SELECTOR BUTTONS (1 TANGGAL 1 BUTTON - 7 HARI KE DEPAN) -->
      <div id="stepSectionDate" class="date-selector-container" onclick="updateStepPills(2)">
        <label class="control-label" style="margin-bottom: 0.6rem; display: block;">
          Langkah 2: Pilih Tanggal Main (7 Hari ke Depan)
        </label>
        
        <div class="date-buttons-grid">
          <?php foreach ($daysAhead as $d): ?>
            <button 
              type="button" 
              class="date-select-btn <?= $d['isToday'] ? 'active' : '' ?>" 
              data-date="<?= $d['date'] ?>"
              onclick="selectDateDirectly('<?= $d['date'] ?>', this)"
            >
              <span class="date-btn-day"><?= htmlspecialchars($d['dayLabel']) ?></span>
              <span class="date-btn-num"><?= htmlspecialchars($d['dateLabel']) ?></span>
              <span class="date-btn-avail" id="avail-<?= $d['date'] ?>">16 Slot</span>
            </button>
          <?php endforeach; ?>
        </div>

        <!-- Hidden input date for script synchronization -->
        <input type="hidden" id="datePicker" value="<?= $today ?>">
      </div>

      <!-- Active Court Banner -->
      <div id="courtInfo" class="active-court-banner"></div>

      <!-- STEP 3: KETERSEDIAAN JAM MAIN (08:00 - 24:00 SESI SIANG & MALAM) -->
      <div id="stepSectionSlot" onclick="updateStepPills(3)">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
          <div>
            <label class="control-label" style="display: block;">
              Langkah 3: Pilih Jam Main (Sesi Siang &amp; Malam)
            </label>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Sistem Reservasi 1 Jam / Slot</span>
          </div>
          <span style="font-size: 0.825rem; color: var(--gold-text); font-weight: 700;">
            💡 Klik slot hijau untuk mengunci jadwal selama 5 menit
          </span>
        </div>

        <div id="slotsContainerDynamic">
          <div style="text-align: center; padding: 2.5rem; color: var(--text-muted); background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border);">
            Memuat jadwal slot lapangan dari server...
          </div>
        </div>
      </div>

    </section>

  </main>

  <!-- MODAL 1: CHECKOUT & KUNCI 5 MENIT (LANGKAH 4: ISI IDENTITAS & SYARAT DP 50%) -->
  <div id="checkoutModal" class="modal-overlay">
    <div class="modal-box">
      
      <!-- Sticky Modal Header with Visible 'X' Button -->
      <div class="modal-header">
        <h3>🔒 Kunci &amp; Reservasi Jadwal</h3>
        <button type="button" class="modal-close-btn" onclick="cancelCurrentLock()" title="Tutup / Batalkan Kunci">✕</button>
      </div>

      <div class="modal-body">
        
        <!-- Step Navigation Pill -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-subtle); border-radius: var(--radius-md); padding: 0.5rem 0.85rem; margin-bottom: 1rem; font-size: 0.8rem; border: 1px solid var(--border);">
          <span style="font-weight: 700; color: var(--gold-text);">Langkah 3 dari 5</span>
          <span style="color: var(--text-muted);">Kunci Slot 5 Menit &amp; Data Pemesan</span>
        </div>

        <!-- Countdown Banner Sinkron Server -->
        <div class="lock-banner">
          <div>
            <strong style="color: var(--text-main); font-size: 0.85rem;">Slot Sedang Anda Kunci Eksklusif!</strong><br>
            <span style="font-size: 0.775rem; color: var(--text-secondary);">Selesaikan DP 50% sebelum waktu habis:</span>
          </div>
          <div id="lockTimerDisplay" class="lock-timer-display">05:00</div>
        </div>

        <!-- Detail Jadwal Terpilih -->
        <div style="font-size: 0.875rem; margin-bottom: 1rem; background: var(--bg-subtle); padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
          <div style="margin-bottom: 0.25rem;"><strong>Lapangan:</strong> <span id="modalCourtName" style="color: var(--gold-text); font-weight: 700;">-</span></div>
          <div><strong>Waktu Main:</strong> <span id="modalDateTime" style="font-weight: 800; font-variant-numeric: tabular-nums; color: var(--gold-text);">-</span></div>
        </div>

        <!-- Rincian Biaya & Syarat DP 50% -->
        <div class="bill-card">
          <div class="bill-row">
            <span>Total Tarif Sewa Lapangan:</span>
            <strong id="billTotal" style="font-family: var(--font-mono);">Rp 0</strong>
          </div>
          <div class="bill-row dp">
            <span>Wajib Uang Muka (DP 50% Online):</span>
            <strong id="billDP" style="font-family: var(--font-mono); font-size: 1.1rem; color: var(--gold-text);">Rp 0</strong>
          </div>
          <div class="bill-row" style="color: var(--text-muted); font-size: 0.825rem;">
            <span>Sisa 50% Dilunasi di Kasir:</span>
            <strong id="billRemaining" style="font-family: var(--font-mono);">Rp 0</strong>
          </div>
        </div>

        <!-- Form Identitas Pemesan -->
        <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.25rem;">
          <div class="control-group">
            <label class="control-label" for="custName">Nama Pemesan / Nama Tim <span style="color: var(--danger-red);">*</span></label>
            <input type="text" id="custName" class="text-input" placeholder="Contoh: Ferry (Spartan FC)" required>
          </div>
          <div class="control-group">
            <label class="control-label" for="custPhone">Nomor WhatsApp Aktif <span style="color: var(--danger-red);">*</span></label>
            <input type="tel" id="custPhone" class="text-input" placeholder="Contoh: 081234567890" required>
          </div>
        </div>

        <!-- Action Button -->
        <div>
          <button type="button" class="btn-gold" onclick="openPaymentModal()" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 0.95rem;">
            Lanjutkan ke Pembayaran DP 50% →
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- MODAL 2: PEMBAYARAN GATEWAY ONLINE DP 50% (QRIS INSTANT) -->
  <div id="paymentModal" class="modal-overlay">
    <div class="modal-box">
      <div class="modal-header">
        <h3>💳 Pembayaran DP 50% via QRIS</h3>
        <button type="button" class="modal-close-btn" onclick="closeModal('paymentModal')" title="Tutup">✕</button>
      </div>
      <div class="modal-body">
        
        <!-- Step Navigation Pill -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-subtle); border-radius: var(--radius-md); padding: 0.5rem 0.85rem; margin-bottom: 1rem; font-size: 0.8rem; border: 1px solid var(--border);">
          <span style="font-weight: 700; color: var(--gold-text);">Langkah 4 dari 5</span>
          <span style="color: var(--text-muted);">Pembayaran Uang Muka (DP 50%) Online</span>
        </div>

        <!-- Payment Amount Banner -->
        <div style="background: var(--gold-badge-bg); border: 1.5px solid var(--gold-border); border-radius: var(--radius-md); padding: 1rem; text-align: center; margin-bottom: 1.25rem;">
          <div style="font-size: 0.8rem; color: var(--gold-text); font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em;">Tagihan Uang Muka (DP 50%)</div>
          <div id="payAmountText" style="font-family: var(--font-mono); font-size: 1.85rem; font-weight: 800; color: var(--gold-text); margin-top: 0.25rem;">Rp 0</div>
          <div style="font-size: 0.775rem; color: var(--text-muted); margin-top: 0.25rem;">Sisa 50% dilunasi langsung di meja kasir saat kedatangan</div>
        </div>

        <!-- QRIS Payment Card Only -->
        <div style="text-align: center; background: #ffffff; padding: 1.5rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 1rem; box-shadow: 0 4px 14px rgba(0,0,0,0.04);">
          <div style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.85rem; background: #fff1f2; border: 1px solid #fecdd3; padding: 0.25rem 0.75rem; border-radius: 999px;">
            <span style="background: #e11d48; color: #ffffff; font-weight: 900; font-size: 0.75rem; letter-spacing: 0.05em; padding: 0.15rem 0.45rem; border-radius: 4px;">QRIS</span>
            <span style="font-size: 0.8rem; font-weight: 700; color: #881337;">Standar Pembayaran Nasional</span>
          </div>

          <div style="display: flex; justify-content: center; margin: 0.5rem 0;">
            <div id="qrisContainer" style="display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: var(--radius-md); box-shadow: 0 2px 8px rgba(0,0,0,0.06);"></div>
          </div>

          <div style="font-size: 0.775rem; color: #64748b; margin-top: 0.75rem; line-height: 1.45;">
            Pindai via BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA, ShopeePay, LinkAja &amp; Semua Aplikasi Bank/E-Wallet
          </div>
        </div>

        <!-- Action Button (Hanya Konfirmasi Pembayaran DP 50%, Tombol Kembali Dihapus) -->
        <div style="margin-top: 1rem;">
          <button type="button" id="btnPayConfirm" class="btn-gold" style="width: 100%; justify-content: center; padding: 0.85rem;" onclick="confirmPaymentOnline()">
            ✓ Konfirmasi Pembayaran DP 50%
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- MODAL 3: KODE TIKET MUNCUL (LANGKAH 5: TICKET REVEAL SUCCESS) -->
  <div id="ticketSuccessModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 500px;">
      <div class="modal-header">
        <h3 style="color: var(--accent-green-text);">🎉 Booking Berhasil Dikonfirmasi!</h3>
        <button type="button" class="modal-close-btn" onclick="finishAndReload()" title="Tutup">✕</button>
      </div>
      <div class="modal-body">
        
        <!-- Step Navigation Pill -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--accent-green-bg); border-radius: var(--radius-md); padding: 0.5rem 0.85rem; margin-bottom: 1rem; font-size: 0.8rem; border: 1px solid var(--accent-green-border);">
          <span style="font-weight: 700; color: var(--accent-green-text);">Langkah 5 dari 5: Selesai</span>
          <span style="color: var(--accent-green-text);">E-Tiket Diterbitkan &amp; Tersimpan di Sistem</span>
        </div>

        <!-- Booking Code Card (Rapih Satu Baris, Tanpa Tombol Salin, Tanpa Tulisan Hijau) -->
        <div style="background: var(--gold-badge-bg); border: 1.5px dashed var(--gold-border); border-radius: var(--radius-md); padding: 1.25rem 1rem; text-align: center; margin-bottom: 1.15rem;">
          <div style="font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--gold-text); font-weight: 800;">
            Kode Booking Resmi Anda
          </div>
          
          <div style="margin: 0.65rem 0; overflow-x: auto; padding: 0.25rem 0;">
            <div id="revealedTicketCode" style="font-family: var(--font-mono); font-size: 1.55rem; font-weight: 800; color: var(--gold-text); letter-spacing: 0.08em; white-space: nowrap; word-break: keep-all; display: inline-block;">-</div>
          </div>
        </div>

        <!-- Booking Details (Dirapikan & Terstruktur) -->
        <div style="background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.15rem; margin-bottom: 1rem; font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.65rem;">
          
          <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            <span style="color: var(--text-muted); font-size: 0.8rem;">Nama Pemesan:</span>
            <strong id="revealedName" style="color: var(--text-main); font-size: 0.9rem;">-</strong>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            <span style="color: var(--text-muted); font-size: 0.8rem;">Lapangan &amp; Waktu:</span>
            <strong id="revealedSchedule" style="color: var(--text-main); font-size: 0.85rem; text-align: right;">-</strong>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            <span style="color: var(--text-muted); font-size: 0.8rem;">Metode Uang Muka (DP 50%):</span>
            <span style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--accent-green-bg); color: var(--accent-green-text); font-weight: 700; font-size: 0.775rem; padding: 0.2rem 0.55rem; border-radius: 999px; border: 1px solid var(--accent-green-border);">
              <span>📱 QRIS Terverifikasi</span>
            </span>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.2rem;">
            <div>
              <div style="color: var(--text-muted); font-size: 0.8rem;">Sisa Pelunasan di Kasir:</div>
              <div style="font-size: 0.725rem; color: var(--text-muted);">Dibayar saat tiba di arena</div>
            </div>
            <strong id="revealedRemaining" style="color: var(--warning-amber-text); font-family: var(--font-mono); font-size: 1.15rem; font-weight: 800;">Rp 0</strong>
          </div>

        </div>

        <!-- Catatan Ramah Bagi Pengguna (Sebagai pengganti 4 tombol yang telah dihapus) -->
        <div style="text-align: center; color: var(--text-muted); font-size: 0.775rem; line-height: 1.5; padding: 0.65rem 0.85rem; background: var(--bg-card); border-radius: var(--radius-sm); border: 1px dashed var(--border);">
          💡 Silakan catat atau tangkap layar (screenshot) kode booking ini untuk ditunjukkan kepada kasir saat kedatangan di arena.
        </div>

      </div>
    </div>
  </div>

  <!-- TOAST NOTIFICATION CONTAINER -->
  <div id="toastContainer" class="toast-container"></div>

  <!-- FOOTER (Rapi Barbershop Dark Editorial Aesthetic) -->
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-col">
        <h3>⚽ <?= htmlspecialchars(VENUE_NAME) ?></h3>
        <p>
          Arena olahraga futsal berstandar kompetisi nasional dengan 2 pilihan tipe lapangan: Vinyl Interlock &amp; Rumput Sintetis Monofilament 50mm. Dilengkapi sistem pemesanan online real-time mandiri, anti double-booking.
        </p>
        <div style="margin-top: 1.25rem;">
          <a href="#bookingFlowSection" class="btn-gold">
            ⚽ Reservasi Sekarang
          </a>
        </div>
      </div>
      <div class="footer-col">
        <h3>📍 Lokasi &amp; Jam Operasional</h3>
        <p>
          <?= htmlspecialchars(VENUE_LOCATION) ?><br>
          Daerah Istimewa Yogyakarta, Indonesia<br><br>
          <strong>Jam Buka:</strong> 08:00 - 24:00 WIB (Setiap Hari)<br>
          <strong>Total Slot:</strong> 16 Jam Operasional / Hari
        </p>
      </div>
      <div class="footer-col">
        <h3>📞 Layanan &amp; Konfirmasi Admin</h3>
        <p>
          Hubungi kami jika ada pertanyaan seputar sewa lapangan, jadwal tanding, atau konfirmasi e-tiket:<br><br>
          WhatsApp Admin: <strong><?= htmlspecialchars(ADMIN_WHATSAPP_DISPLAY) ?></strong><br>
          Status Sistem: <span style="color: var(--accent-green); font-weight: 700;">● Online (Siap Menerima Reservasi)</span>
        </p>
      </div>
    </div>
    <div class="footer-bottom">
      <div>&copy; <?= date('Y') ?> <?= htmlspecialchars(VENUE_NAME) ?>. Hak Cipta Dilindungi Undang-Undang.</div>
      <div>Sistem Reservasi Futsal Mandiri • Est. Sports House</div>
    </div>
  </footer>

  <!-- SCRIPTS -->
  <script src="js/qrcode.min.js"></script>
  <script>
    // State Klien & Token
    let clientToken = localStorage.getItem('futsal_client_token');
    if (!clientToken) {
      clientToken = 'usr_' + Math.random().toString(36).substring(2, 10);
      localStorage.setItem('futsal_client_token', clientToken);
    }

    let currentLock = null;
    let countdownTimer = null;
    let lastGeneratedBookingCode = '';
    let currentPaymentMethod = 'QRIS';

    // Theme Management (Dark / Light Switcher)
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

    // Dynamic Active Nav Bar & Scroll-Spy
    let isManualNavClick = false;
    const navSections = ['top', 'keunggulan', 'lapangan', 'bookingFlowSection'];

    function setActiveNav(targetNav) {
      document.querySelectorAll('.site-header .nav-link-item').forEach(link => {
        if (link.getAttribute('data-nav') === targetNav) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      });
    }

    function updateActiveNavOnScroll() {
      if (isManualNavClick) return;
      
      const scrollPos = window.scrollY + 140; // Offset for sticky topbar

      // Jika sudah dekat bagian paling bawah dokumen, sorot jadwal booking
      if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 60)) {
        setActiveNav('bookingFlowSection');
        return;
      }

      let currentSection = 'top';
      for (let i = 0; i < navSections.length; i++) {
        const el = document.getElementById(navSections[i]);
        if (el) {
          const top = el.offsetTop;
          if (scrollPos >= top) {
            currentSection = navSections[i];
          }
        }
      }
      setActiveNav(currentSection);
    }

    function initNavScrollSpy() {
      // Pasang click listener pada setiap tautan navigasi internal #
      document.querySelectorAll('.site-header a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
          const targetNav = this.getAttribute('data-nav');
          if (targetNav) {
            setActiveNav(targetNav);
            isManualNavClick = true;
            setTimeout(() => { isManualNavClick = false; }, 850);
          }
        });
      });

      // Scroll listener dengan requestAnimationFrame untuk responsivitas halus
      window.addEventListener('scroll', () => {
        requestAnimationFrame(updateActiveNavOnScroll);
      }, { passive: true });

      // Inisialisasi awal saat halaman dibuka
      updateActiveNavOnScroll();
    }

    document.addEventListener('DOMContentLoaded', () => {
      // Sync initial theme icons
      const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
      syncThemeIcons(currentTheme);

      initNavScrollSpy();
      initStepScrollSpy();
      syncCourtCards();
      loadSchedule();
      loadDaysAvailability();

      // Polling jadwal setiap 4 detik untuk update multi-user
      setInterval(loadSchedule, 4000);
      setInterval(loadDaysAvailability, 8000);

      // Event listener: Tutup modal bila klik di luar modal-box atau tekan tombol Escape
      document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
          if (e.target === overlay) {
            if (overlay.id === 'checkoutModal') {
              cancelCurrentLock();
            } else {
              overlay.classList.remove('active');
            }
          }
        });
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          const activeModals = document.querySelectorAll('.modal-overlay.active');
          activeModals.forEach(modal => {
            if (modal.id === 'checkoutModal') {
              cancelCurrentLock();
            } else {
              modal.classList.remove('active');
            }
          });
        }
      });
    });

    function showToast(message, type = 'info') {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      toast.innerHTML = message;
      container.appendChild(toast);
      setTimeout(() => toast.remove(), 4000);
    }

    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) modal.classList.remove('active');
    }

    // Navigasi Loncat ke Langkah yang Diinginkan
    function jumpToStep(step) {
      updateStepPills(step);
      if (step === 1) {
        const el = document.getElementById('stepSectionCourt');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else if (step === 2) {
        const el = document.getElementById('stepSectionDate');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else if (step === 3) {
        const el = document.getElementById('stepSectionSlot');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    // Pemilihan 2 Tipe Lapangan
    function selectCourtDirectly(courtId) {
      const select = document.getElementById('courtSelect');
      select.value = courtId;
      syncCourtCards();
      loadSchedule();
      loadDaysAvailability();

      // Pindahkan orange aktif ke Langkah 1: Pilih Lapangan
      updateStepPills(1);
    }

    // Pemilihan Tanggal 7 Hari ke Depan
    function selectDateDirectly(dateStr, btnElement) {
      document.getElementById('datePicker').value = dateStr;
      document.querySelectorAll('.date-select-btn').forEach(b => b.classList.remove('active'));
      if (btnElement) {
        btnElement.classList.add('active');
      }
      loadSchedule();

      // Pindahkan orange aktif ke Langkah 2: Pilih Tanggal
      updateStepPills(2);
    }

    function syncCourtCards() {
      const select = document.getElementById('courtSelect');
      const val = parseInt(select.value);

      const card1 = document.getElementById('cardCourt1');
      const card2 = document.getElementById('cardCourt2');

      if (card1 && card2) {
        if (val === 1) {
          card1.classList.add('active');
          card2.classList.remove('active');
        } else {
          card2.classList.add('active');
          card1.classList.remove('active');
        }
      }
      updateCourtInfo();
    }

    function updateCourtInfo() {
      const select = document.getElementById('courtSelect');
      const opt = select.options[select.selectedIndex];
      if (!opt) return;

      const desc = opt.getAttribute('data-desc');
      const offPeak = parseInt(opt.getAttribute('data-offpeak')).toLocaleString('id-ID');
      const peak = parseInt(opt.getAttribute('data-peak')).toLocaleString('id-ID');

      document.getElementById('courtInfo').innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <div>
            <div style="font-family: var(--font-serif); font-size: 1.1rem; font-weight: 600; color: var(--gold-text);">🏟️ ${opt.text}</div>
            <div style="font-size: 0.825rem; color: var(--text-muted); margin-top: 2px;">${desc}</div>
          </div>
          <div style="font-size: 0.825rem; background: var(--bg-subtle); padding: 0.4rem 0.85rem; border-radius: var(--radius-full); border: 1px solid var(--border); font-weight: 600;">
            Tarif: Siang (08:00 - 15:00) <strong style="color: var(--gold-text);">Rp ${offPeak}</strong> | Malam (15:00 - 24:00) <strong style="color: var(--gold-text);">Rp ${peak}</strong>
          </div>
        </div>
      `;
    }

    // Memuat Ketersediaan Slot 7 Hari ke Depan
    async function loadDaysAvailability() {
      const courtId = document.getElementById('courtSelect').value;
      const dateButtons = document.querySelectorAll('.date-select-btn');
      const dates = Array.from(dateButtons).map(b => b.getAttribute('data-date')).filter(Boolean);
      if (dates.length === 0) return;

      try {
        const res = await fetch(`api.php?action=get_days_summary&court_id=${courtId}&dates=${dates.join(',')}`);
        const result = await res.json();
        if (result.success && result.data) {
          for (const d of dates) {
            const el = document.getElementById(`avail-${d}`);
            if (el && result.data[d]) {
              const av = result.data[d].available;
              el.innerText = `${av} Slot`;
              if (av === 0) {
                el.innerText = 'Penuh';
                el.style.color = 'var(--danger-red-text)';
                el.style.background = 'var(--danger-red-bg)';
                el.style.borderColor = 'var(--danger-red-border)';
              } else {
                el.style.color = 'var(--accent-green-text)';
                el.style.background = 'var(--accent-green-bg)';
                el.style.borderColor = 'var(--accent-green-border)';
              }
            }
          }
        }
      } catch (e) {
        console.warn("Gagal memuat ketersediaan slot:", e);
      }
    }

    async function loadSchedule() {
      const courtId = document.getElementById('courtSelect').value;
      const date = document.getElementById('datePicker').value;

      try {
        const res = await fetch(`api.php?action=get_schedule&court_id=${courtId}&date=${date}`);
        const result = await res.json();
        if (result.success) {
          renderSlots(result.data.slots, courtId, date);
        }
      } catch (e) {
        console.error("Gagal memuat jadwal:", e);
      }
    }

    // Render Slot Berdasarkan Sesi Siang & Sesi Malam
    function renderSlots(slots, courtId, date) {
      const container = document.getElementById('slotsContainerDynamic');
      container.innerHTML = `
        <!-- Sesi Siang -->
        <div class="session-group">
          <div class="session-header-bar">
            <div class="session-title">☀️ Sesi Siang (08:00 - 15:00)</div>
            <div class="session-rate-badge">Tarif Reguler Siang</div>
          </div>
          <div class="slots-grid" id="gridDaySlots"></div>
        </div>

        <!-- Sesi Malam -->
        <div class="session-group">
          <div class="session-header-bar session-night">
            <div class="session-title">🌙 Sesi Malam (15:00 - 24:00)</div>
            <div class="session-rate-badge">Tarif Malam + Lampu LED Stadium</div>
          </div>
          <div class="slots-grid" id="gridNightSlots"></div>
        </div>
      `;

      const gridDay = document.getElementById('gridDaySlots');
      const gridNight = document.getElementById('gridNightSlots');

      slots.forEach(slot => {
        const card = createSlotElement(slot, courtId, date);
        if (slot.hour < 15) {
          gridDay.appendChild(card);
        } else {
          gridNight.appendChild(card);
        }
      });
    }

    function createSlotElement(slot, courtId, date) {
      const card = document.createElement('div');
      card.className = `slot-card ${slot.status.toLowerCase()}`;

      if (slot.status === 'AVAILABLE') {
        card.innerHTML = `
          <div class="slot-time">🕒 ${slot.timeLabel}</div>
          <div class="slot-price">Rp ${slot.price.toLocaleString('id-ID')}</div>
          <div class="slot-status-text" style="color: var(--accent-green-text);">● Tersedia (Klik)</div>
        `;
        card.onclick = () => requestLock(courtId, date, slot.hour);
      } else if (slot.status === 'LOCKED') {
        const isMine = (currentLock && currentLock.hour === slot.hour && currentLock.courtId === parseInt(courtId));
        card.innerHTML = `
          <div class="slot-time">🕒 ${slot.timeLabel}</div>
          <div class="slot-status-text" style="color: var(--warning-amber-text);">
            ${isMine ? '🔒 Sedang Anda Kunci' : '⏳ Diproses Tamu Lain'}
          </div>
          <div class="slot-countdown">${slot.lock.remainingSeconds}s</div>
        `;
        if (isMine) {
          card.style.borderColor = 'var(--gold-primary)';
          card.onclick = () => openCheckoutModal(currentLock);
        } else {
          card.onclick = () => showToast(`⚠️ Slot jam ${slot.timeLabel} sedang dikunci oleh pengunjung lain selama 5 menit.`, 'warning');
        }
      } else if (slot.status === 'BOOKED') {
        card.innerHTML = `
          <div class="slot-time">🕒 ${slot.timeLabel}</div>
          <div class="slot-price" style="text-decoration: line-through; opacity: 0.6;">Rp ${slot.price.toLocaleString('id-ID')}</div>
          <div class="slot-status-text" style="color: var(--danger-red-text);">✖ Terisi (${slot.booking.customer})</div>
        `;
        card.onclick = () => showToast(`⛔ Slot jam ${slot.timeLabel} telah dibooking oleh ${slot.booking.customer}.`, 'error');
      }

      return card;
    }

    // Mengunci Slot (TTL 5 Menit di MySQL)
    async function requestLock(courtId, date, hour) {
      try {
        const fd = new FormData();
        fd.append('court_id', courtId);
        fd.append('date', date);
        fd.append('hour', hour);
        fd.append('token', clientToken);

        const res = await fetch('api.php?action=acquire_lock', { method: 'POST', body: fd });
        const data = await res.json();

        if (res.status === 200 && data.success) {
          currentLock = data.lock;
          showToast(`🔒 Berhasil mengunci slot jam ${currentLock.timeLabel} selama 5 menit!`, 'success');
          openCheckoutModal(currentLock);
          loadSchedule();
          loadDaysAvailability();
        } else {
          showToast(`⚠️ ${data.message}`, 'error');
          loadSchedule();
        }
      } catch (e) {
        showToast('Terjadi kesalahan jaringan.', 'error');
      }
    }

    function updateStepPills(activeStep) {
      for (let i = 1; i <= 5; i++) {
        const pill = document.getElementById('stepPill' + i);
        if (pill) {
          if (i === activeStep) {
            pill.classList.add('active');
          } else {
            pill.classList.remove('active');
          }
        }
      }
    }

    // ScrollSpy untuk memindahkan orange step secara dinamis saat scroll
    function initStepScrollSpy() {
      const s1 = document.getElementById('stepSectionCourt');
      const s2 = document.getElementById('stepSectionDate');
      const s3 = document.getElementById('stepSectionSlot');
      if (!s1 || !s2 || !s3) return;

      let ticking = false;
      window.addEventListener('scroll', () => {
        if (!ticking) {
          window.requestAnimationFrame(() => {
            // Jangan ubah step pill jika modal aktif sedang terbuka
            if (document.querySelector('.modal-overlay.active')) {
              ticking = false;
              return;
            }

            const threshold = window.innerHeight * 0.42;
            const r1 = s1.getBoundingClientRect();
            const r2 = s2.getBoundingClientRect();
            const r3 = s3.getBoundingClientRect();

            if (r3.top <= threshold) {
              updateStepPills(3);
            } else if (r2.top <= threshold) {
              updateStepPills(2);
            } else if (r1.top <= threshold + 120) {
              updateStepPills(1);
            }
            ticking = false;
          });
          ticking = true;
        }
      }, { passive: true });
    }

    function openCheckoutModal(lock) {
      document.getElementById('modalCourtName').innerText = lock.courtName;
      document.getElementById('modalDateTime').innerText = `${lock.date} | Jam ${lock.timeLabel}`;
      document.getElementById('billTotal').innerText = `Rp ${lock.totalPrice.toLocaleString('id-ID')}`;
      document.getElementById('billDP').innerText = `Rp ${lock.dpAmount.toLocaleString('id-ID')}`;
      document.getElementById('billRemaining').innerText = `Rp ${lock.remainingAmount.toLocaleString('id-ID')}`;

      updateStepPills(4);

      // Hitung mundur sinkron server
      startLockCountdown(lock.remainingSeconds);
      document.getElementById('checkoutModal').classList.add('active');
    }

    function startLockCountdown(seconds) {
      clearInterval(countdownTimer);
      let remain = seconds;
      const display = document.getElementById('lockTimerDisplay');

      function update() {
        const m = Math.floor(remain / 60);
        const s = remain % 60;
        display.innerText = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        if (remain <= 0) {
          clearInterval(countdownTimer);
          closeModal('checkoutModal');
          closeModal('paymentModal');
          updateStepPills(3);
          showToast('Waktu kunci 5 menit telah habis! Slot dilepas kembali ke publik.', 'warning');
          currentLock = null;
          loadSchedule();
          loadDaysAvailability();
        }
        remain--;
      }
      update();
      countdownTimer = setInterval(update, 1000);
    }

    async function cancelCurrentLock() {
      if (currentLock) {
        const fd = new FormData();
        fd.append('court_id', currentLock.courtId);
        fd.append('date', currentLock.date);
        fd.append('hour', currentLock.hour);
        fd.append('token', clientToken);
        await fetch('api.php?action=release_lock', { method: 'POST', body: fd });
        currentLock = null;
      }
      clearInterval(countdownTimer);
      closeModal('checkoutModal');
      closeModal('paymentModal');
      updateStepPills(3);
      showToast('Kunci slot dibatalkan.', 'info');
      loadSchedule();
      loadDaysAvailability();
    }

    function openPaymentModal() {
      const name = document.getElementById('custName').value.trim();
      const phone = document.getElementById('custPhone').value.trim();

      if (!name || !phone) {
        showToast('Nama Pemesan dan Nomor WhatsApp wajib diisi sebelum lanjut!', 'warning');
        document.getElementById('custName').focus();
        return;
      }

      // Update Step Indicators to Step 4 (Pembayaran DP 50%)
      updateStepPills(4);

      closeModal('checkoutModal');
      document.getElementById('payAmountText').innerText = `Rp ${currentLock.dpAmount.toLocaleString('id-ID')}`;

      // Set default payment method to QRIS
      currentPaymentMethod = 'QRIS';

      // Generate Dynamic QRIS visual barcode
      try {
        const qrisBox = document.getElementById('qrisContainer');
        qrisBox.innerHTML = '';
        const qrisPayload = `00020101021226680016ID.CO.QRIS.WWW01189360099881234567895204581253033605802ID5914FUTSALHUB6007YOGYA6304${currentLock.hour}${currentLock.dpAmount}`;
        if (typeof renderQRCode === 'function') {
          renderQRCode('qrisContainer', qrisPayload, 175);
        } else if (typeof QRCode === 'function') {
          new QRCode(qrisBox, {
            text: qrisPayload,
            width: 175,
            height: 175
          });
        }
      } catch (err) {
        console.warn('QR Code render fallback:', err);
      }

      document.getElementById('paymentModal').classList.add('active');
    }

    // Konfirmasi Pembayaran DP 50% & Buka E-Tiket Seketika
    async function confirmPaymentOnline() {
      const name = document.getElementById('custName').value.trim();
      const phone = document.getElementById('custPhone').value.trim();
      const btn = document.getElementById('btnPayConfirm');
      btn.disabled = true;
      btn.innerText = 'Memverifikasi Pembayaran DP 50%...';

      try {
        const payload = {
          court_id: currentLock.courtId,
          date: currentLock.date,
          hour: currentLock.hour,
          customer_name: name,
          phone_number: phone,
          token: clientToken,
          payment_method: 'QRIS'
        };

        const res = await fetch('api.php?action=confirm_dp', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.success) {
          showToast('✅ Pembayaran DP 50% Berhasil Dikonfirmasi!', 'success');
          clearInterval(countdownTimer);
          
          lastGeneratedBookingCode = data.data.bookingCode;

          // Update Step Indicators to Step 5 (Tiket Selesai)
          updateStepPills(5);

          // Tutup Modal Pembayaran
          closeModal('paymentModal');

          // Tampilkan Modal Kode Tiket Muncul (Langkah 5)
          const codeEl = document.getElementById('revealedTicketCode');
          if (codeEl) codeEl.innerText = data.data.bookingCode;

          const nameEl = document.getElementById('revealedName');
          if (nameEl) nameEl.innerText = `${name} (${phone})`;

          const schedEl = document.getElementById('revealedSchedule');
          if (schedEl) schedEl.innerText = `${currentLock.courtName} • ${currentLock.date} Jam ${currentLock.timeLabel}`;

          const remEl = document.getElementById('revealedRemaining');
          if (remEl) remEl.innerText = `Rp ${currentLock.remainingAmount.toLocaleString('id-ID')}`;

          const btnTicket = document.getElementById('btnOpenDigitalTicket');
          if (btnTicket) btnTicket.href = `tiket.php?code=${data.data.bookingCode}`;

          const btnHist = document.getElementById('btnViewHistory');
          if (btnHist) btnHist.href = `riwayat.php?q=${encodeURIComponent(data.data.bookingCode)}`;

          const btnWA = document.getElementById('btnWhatsAppConfirm');
          if (btnWA && data.data.whatsappUrl) btnWA.href = data.data.whatsappUrl;

          document.getElementById('ticketSuccessModal').classList.add('active');

          // Reset lock state & reload schedule
          currentLock = null;
          loadSchedule();
          loadDaysAvailability();
        } else {
          showToast(`❌ Gagal: ${data.message}`, 'error');
          btn.disabled = false;
          btn.innerText = '✓ Konfirmasi Pembayaran DP 50%';
        }
      } catch (e) {
        showToast('Terjadi kesalahan jaringan.', 'error');
        btn.disabled = false;
        btn.innerText = '✓ Konfirmasi Pembayaran DP 50%';
      }
    }

    function copyTicketCode() {
      const code = document.getElementById('revealedTicketCode').innerText;
      if (code && code !== '-') {
        navigator.clipboard.writeText(code).then(() => {
          showToast(`📋 Kode booking ${code} berhasil disalin ke clipboard!`, 'success');
        }).catch(() => {
          showToast(`Kode booking: ${code}`, 'info');
        });
      }
    }

    function finishAndReload() {
      closeModal('ticketSuccessModal');
      document.getElementById('custName').value = '';
      document.getElementById('custPhone').value = '';
      loadSchedule();
      loadDaysAvailability();
    }
  </script>
</body>
</html>