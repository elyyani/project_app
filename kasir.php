<?php
/**
 * kasir.php
 * Production-Grade Cashier POS Dashboard - FutsalHub Arena
 * Real-time Database Settlement, Walk-In Booking, and Financial Overview.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingEngine.php';

$engine = new BookingEngine();
$today = date('Y-m-d');
$courts = $engine->getCourts();
$searchQuery = trim((string)($_GET['search'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FutsalHub - Dasbor Kasir & POS Lapangan</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <!-- Navigation Bar -->
  <header class="navbar">
    <a href="index.php" class="nav-brand">
      <span>⚽ FutsalHub Arena</span>
      <span class="nav-badge">POS Kasir & Lapangan</span>
    </a>
    <nav class="nav-links">
      <a href="index.php" class="nav-btn">📅 Jadwal & Booking</a>
      <a href="kasir.php" class="nav-btn active">🖥️ Dasbor Kasir POS</a>
      <a href="tiket.php" class="nav-btn">🎫 E-Tiket</a>
    </nav>
  </header>

  <!-- Main Container -->
  <main class="container">

    <!-- Page Header & Filter -->
    <section class="page-header">
      <div class="page-title">
        <h1>Dasbor Kasir & Operasional Lapangan</h1>
        <p>Validasi e-tiket QR pelanggan, catat pelunasan sisa 50%, dan kelola pemesanan walk-in di tempat.</p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <div class="control-group">
          <label for="kasirDateFilter" class="control-label">Pilih Tanggal Operasional</label>
          <input type="date" id="kasirDateFilter" class="date-input" value="<?= $today ?>" onchange="loadCashierData()">
        </div>
      </div>
    </section>

    <!-- Cashier Revenue & Shift Stats -->
    <section class="cashier-stats">
      <div class="stat-card">
        <div class="stat-label">Total Booking Hari Ini</div>
        <div id="statTotalBookings" class="stat-val">0</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Penerimaan DP Online (50%)</div>
        <div id="statDPOnline" class="stat-val" style="color: #0284c7;">Rp 0</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Pelunasan Kasir (Tunai/QRIS)</div>
        <div id="statCashierPaid" class="stat-val" style="color: #059669;">Rp 0</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Kas / Omzet Masuk</div>
        <div id="statTotalOmzet" class="stat-val" style="color: var(--primary);">Rp 0</div>
      </div>
    </section>

    <!-- Cashier Interactive Grid -->
    <div class="cashier-grid">

      <!-- Left Column: Validasi Pelunasan & Walk-In -->
      <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Card 1: Validasi Tiket & Pelunasan Kasir -->
        <div class="cashier-card">
          <div class="cashier-card-title">
            <span>🎫 Validasi E-Tiket & Pelunasan 50%</span>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <input type="text" id="searchBookingCode" class="text-input" placeholder="Masukkan Kode Booking (Contoh: FUT-...)" value="<?= htmlspecialchars($searchQuery) ?>" style="flex: 1;">
            <button class="btn btn-primary" onclick="searchTicket()" style="width: auto;">
              Cari
            </button>
          </div>

          <!-- Settlement Detail Box -->
          <div id="settlementDetailBox" style="display: none; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 1rem;">
            <div style="font-size: 0.85rem; margin-bottom: 0.5rem;">
              <strong>Kode Booking:</strong> <span id="detailBookingCode" style="color: var(--primary); font-weight: 700;">-</span>
            </div>
            <div style="font-size: 0.85rem; margin-bottom: 0.25rem;">
              <strong>Penyewa:</strong> <span id="detailCustomer">-</span>
            </div>
            <div style="font-size: 0.85rem; margin-bottom: 0.75rem;">
              <strong>Jadwal:</strong> <span id="detailCourt">-</span>
            </div>

            <div class="bill-card" style="margin-bottom: 0.75rem;">
              <div class="bill-row">
                <span>Total Sewa Lapangan:</span>
                <span id="detailTotalPrice">Rp 0</span>
              </div>
              <div class="bill-row" style="color: #059669;">
                <span>DP 50% (Sudah Dibayar Online):</span>
                <span id="detailPaidDP">Rp 0</span>
              </div>
              <div class="bill-row remaining" style="font-size: 1rem; border-top: 1px dashed var(--border); padding-top: 0.5rem; margin-top: 0.5rem;">
                <span>Wajib Dilunasi di Kasir:</span>
                <span id="detailRemaining">Rp 0</span>
              </div>
            </div>

            <div id="settlementActionButtons" style="display: flex; gap: 0.5rem;"></div>
          </div>
        </div>

        <!-- Card 2: Input Reservasi Walk-In (Di Lokasi) -->
        <div class="cashier-card">
          <div class="cashier-card-title">
            <span>⚽ Reservasi Pelanggan Walk-In</span>
          </div>

          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div class="control-group">
              <label class="control-label">Pilih Lapangan</label>
              <select id="walkinCourt" class="select-input">
                <?php foreach ($courts as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="control-group">
              <label class="control-label">Pilih Jam</label>
              <select id="walkinHour" class="select-input">
                <?php for ($h = OPERATING_HOUR_START; $h < OPERATING_HOUR_END; $h++): ?>
                  <option value="<?= $h ?>"><?= sprintf('%02d:00 - %02d:00', $h, $h + 1) ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="control-group">
              <label class="control-label">Nama Pelanggan / Tim</label>
              <input type="text" id="walkinCustName" class="text-input" placeholder="Nama pemesan langsung di lokasi">
            </div>

            <div class="control-group">
              <label class="control-label">Nomor WhatsApp (Opsional)</label>
              <input type="tel" id="walkinCustPhone" class="text-input" placeholder="08xxxxxxxxxx">
            </div>

            <div class="control-group">
              <label class="control-label">Opsi Pembayaran Walk-In</label>
              <select id="walkinPaymentOption" class="select-input">
                <option value="LUNAS">Langsung Lunas 100% (Check-In)</option>
                <option value="DP50">Bayar DP 50% Dulu</option>
              </select>
            </div>

            <button class="btn btn-primary" onclick="submitWalkInBooking()" style="margin-top: 0.5rem;">
              + Catat Booking Walk-In
            </button>
          </div>
        </div>

      </div>

      <!-- Right Column: Timeline & Jadwal Hari Ini -->
      <div class="cashier-card">
        <div class="cashier-card-title">
          <span>📋 Jadwal & Status Pembayaran Lapangan Hari Ini</span>
        </div>

        <div style="overflow-x: auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Lapangan</th>
                <th>Jam</th>
                <th>Penyewa</th>
                <th>Finansial</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="bookingsTableBody">
              <tr>
                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                  Memuat data transaksi dari server...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

  </main>

  <!-- Toast Container -->
  <div id="toastContainer" class="toast-container"></div>

  <!-- Scripts -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      loadCashierData();
      const initialSearch = document.getElementById('searchBookingCode').value.trim();
      if (initialSearch) {
        searchTicket();
      }
      setInterval(loadCashierData, 5000);
    });

    function showToast(message, type = 'info') {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      toast.innerHTML = message;
      container.appendChild(toast);
      setTimeout(() => toast.remove(), 4000);
    }

    async function loadCashierData() {
      const date = document.getElementById('kasirDateFilter').value;
      try {
        const res = await fetch(`api.php?action=get_cashier_data&date=${date}`);
        const result = await res.json();
        if (result.success) {
          const d = result.data;
          document.getElementById('statTotalBookings').innerText = d.totalBookings;
          document.getElementById('statDPOnline').innerText = `Rp ${d.dpOnline.toLocaleString('id-ID')}`;
          document.getElementById('statCashierPaid').innerText = `Rp ${d.cashierPaid.toLocaleString('id-ID')}`;
          document.getElementById('statTotalOmzet').innerText = `Rp ${d.totalOmzet.toLocaleString('id-ID')}`;

          renderBookingsTable(d.bookings);
        }
      } catch (e) {
        console.error("Gagal memuat data kasir:", e);
      }
    }

    function renderBookingsTable(bookings) {
      const tbody = document.getElementById('bookingsTableBody');
      tbody.innerHTML = '';

      if (!bookings || bookings.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
              Belum ada pesanan lapangan tercatat untuk tanggal ini.
            </td>
          </tr>
        `;
        return;
      }

      bookings.forEach(b => {
        const tr = document.createElement('tr');
        const startH = String(b.slot_hour).padStart(2, '0') + ':00';
        const endH = String(parseInt(b.slot_hour) + 1).padStart(2, '0') + ':00';
        const rem = parseFloat(b.remaining_amount);
        const total = parseFloat(b.total_price);
        const dp = parseFloat(b.dp_amount);

        let badgeHtml = '';
        let actionHtml = '';

        if (b.status === 'SETTLED') {
          badgeHtml = '<span class="ticket-badge badge-fully-paid" style="font-size: 0.75rem;">✔ LUNAS (100%)</span>';
          actionHtml = `<a href="tiket.php?code=${b.booking_code}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 4px 8px;">Lihat Tiket</a>`;
        } else if (b.status === 'DP_PAID') {
          badgeHtml = '<span class="ticket-badge badge-partially-paid" style="font-size: 0.75rem;">⏳ DP (50%)</span>';
          actionHtml = `
            <button class="btn btn-primary" onclick="quickSettle('${b.booking_code}')" style="font-size: 0.75rem; padding: 4px 8px;">
              Pelunasan
            </button>
            <a href="tiket.php?code=${b.booking_code}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 4px 8px; margin-left: 4px;">Tiket</a>
          `;
        } else {
          badgeHtml = '<span style="color: var(--text-muted); font-size: 0.75rem;">Dibatalkan</span>';
          actionHtml = '-';
        }

        tr.innerHTML = `
          <td><strong style="color: var(--primary); font-family: monospace;">${b.booking_code}</strong></td>
          <td>${b.court_name}</td>
          <td>${startH} - ${endH}</td>
          <td>
            <strong>${b.customer_name}</strong><br>
            <small style="color: var(--text-muted);">${b.phone_number}</small>
          </td>
          <td>
            Total: Rp ${total.toLocaleString('id-ID')}<br>
            <small style="color: ${rem > 0 ? '#d97706' : '#059669'};">
              ${rem > 0 ? 'Sisa: Rp ' + rem.toLocaleString('id-ID') : 'Lunas'}
            </small>
          </td>
          <td>${badgeHtml}</td>
          <td>${actionHtml}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    async function searchTicket() {
      const code = document.getElementById('searchBookingCode').value.trim();
      const box = document.getElementById('settlementDetailBox');

      if (!code) {
        showToast('Ketik kode booking terlebih dahulu.', 'warning');
        return;
      }

      try {
        const res = await fetch(`api.php?action=search_ticket&code=${encodeURIComponent(code)}`);
        const data = await res.json();

        if (res.status === 200 && data.success) {
          const b = data.data;
          document.getElementById('detailBookingCode').innerText = b.booking_code;
          document.getElementById('detailCustomer').innerText = `${b.customer_name} (${b.phone_number})`;
          document.getElementById('detailCourt').innerText = `${b.court_name} • ${b.booking_date} (Jam ${String(b.slot_hour).padStart(2, '0')}:00)`;
          document.getElementById('detailTotalPrice').innerText = `Rp ${parseFloat(b.total_price).toLocaleString('id-ID')}`;
          document.getElementById('detailPaidDP').innerText = `Rp ${parseFloat(b.dp_amount).toLocaleString('id-ID')}`;
          document.getElementById('detailRemaining').innerText = `Rp ${parseFloat(b.remaining_amount).toLocaleString('id-ID')}`;

          const btnContainer = document.getElementById('settlementActionButtons');
          if (b.status === 'SETTLED') {
            btnContainer.innerHTML = `<span style="color: #059669; font-weight: 700;">✔ Booking ini sudah lunas 100%!</span>`;
          } else if (b.status === 'DP_PAID') {
            btnContainer.innerHTML = `
              <button class="btn btn-primary" onclick="executeSettlement('${b.booking_code}', 'CASH')" style="flex: 1;">
                💵 Lunasi Tunai (Cash)
              </button>
              <button class="btn btn-secondary" onclick="executeSettlement('${b.booking_code}', 'QRIS_KASIR')" style="flex: 1;">
                💳 Lunasi QRIS Kasir
              </button>
            `;
          } else {
            btnContainer.innerHTML = `<span style="color: var(--danger);">Booking ini berstatus dibatalkan.</span>`;
          }

          box.style.display = 'block';
        } else {
          box.style.display = 'none';
          showToast(`❌ ${data.message}`, 'error');
        }
      } catch (e) {
        showToast('Gagal mencari tiket.', 'error');
      }
    }

    async function quickSettle(code) {
      document.getElementById('searchBookingCode').value = code;
      await searchTicket();
      window.scrollTo({ top: 150, behavior: 'smooth' });
    }

    async function executeSettlement(code, method) {
      try {
        const fd = new FormData();
        fd.append('booking_code', code);
        fd.append('payment_method', method);

        const res = await fetch('api.php?action=settle_cashier', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          showToast(`✅ Pelunasan ${code} berhasil! Status: LUNAS 100%`, 'success');
          searchTicket();
          loadCashierData();
        } else {
          showToast(`❌ Gagal: ${data.message}`, 'error');
        }
      } catch (e) {
        showToast('Terjadi kesalahan jaringan.', 'error');
      }
    }

    async function submitWalkInBooking() {
      const courtId = document.getElementById('walkinCourt').value;
      const hour = document.getElementById('walkinHour').value;
      const name = document.getElementById('walkinCustName').value.trim();
      const phone = document.getElementById('walkinCustPhone').value.trim();
      const date = document.getElementById('kasirDateFilter').value;
      const payOption = document.getElementById('walkinPaymentOption').value;

      if (!name) {
        showToast('Nama pelanggan walk-in wajib diisi!', 'warning');
        return;
      }

      try {
        const payload = {
          court_id: courtId,
          date: date,
          hour: hour,
          customer_name: name,
          phone_number: phone,
          payment_option: payOption
        };

        const res = await fetch('api.php?action=walkin_booking', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.success) {
          showToast(`✅ Booking Walk-in berhasil dicatat! Kode: ${data.data.bookingCode}`, 'success');
          document.getElementById('walkinCustName').value = '';
          document.getElementById('walkinCustPhone').value = '';
          loadCashierData();
        } else {
          showToast(`❌ Gagal: ${data.message}`, 'error');
        }
      } catch (e) {
        showToast('Gagal mencatat booking walk-in.', 'error');
      }
    }
  </script>
</body>
</html>
