/**
 * kasir.js
 * Logika Interaktif Dasbor Kasir & POS Operasional Lapangan
 */

document.addEventListener("DOMContentLoaded", () => {
  const dateInput = document.getElementById("kasirDateFilter");
  if (dateInput) {
    dateInput.value = FutsalStore.getTodayString();
    dateInput.addEventListener("change", refreshCashierView);
  }

  // Isi jam untuk form walk-in
  const walkinHourSelect = document.getElementById("walkinHour");
  if (walkinHourSelect) {
    walkinHourSelect.innerHTML = "";
    for (let h = 8; h <= 23; h++) {
      const opt = document.createElement("option");
      opt.value = h;
      opt.innerText = `Jam ${String(h).padStart(2, '0')}:00 - ${String(h+1).padStart(2, '0')}:00`;
      walkinHourSelect.appendChild(opt);
    }
  }

  window.addEventListener("futsal_data_updated", refreshCashierView);

  // Inisialisasi tampilan awal
  refreshCashierView();
});

function refreshCashierView() {
  const targetDate = document.getElementById("kasirDateFilter").value || FutsalStore.getTodayString();
  
  // 1. Render Stats
  const stats = FutsalStore.getDailyStats(targetDate);
  document.getElementById("statTotalBookings").innerText = stats.totalBookings;
  document.getElementById("statDPOnline").innerText = `Rp ${stats.totalDPOnline.toLocaleString('id-ID')}`;
  document.getElementById("statCashierPaid").innerText = `Rp ${stats.totalCashierPaid.toLocaleString('id-ID')}`;
  document.getElementById("statTotalOmzet").innerText = `Rp ${stats.totalRevenue.toLocaleString('id-ID')}`;

  // 2. Render Tabel Booking
  const tbody = document.getElementById("bookingsTableBody");
  const bookings = FutsalStore.getBookings()
    .filter(b => b.date === targetDate && b.status !== 'CANCELLED')
    .sort((a, b) => a.hour - b.hour);

  tbody.innerHTML = "";

  if (bookings.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Belum ada jadwal booking untuk tanggal ini.</td></tr>`;
    return;
  }

  bookings.forEach(b => {
    const tr = document.createElement("tr");
    const isCheckedIn = (b.status === "CHECKED_IN" || b.status === "FULLY_PAID");
    
    tr.innerHTML = `
      <td><strong>${b.bookingCode}</strong></td>
      <td>${b.courtName}</td>
      <td><strong>${String(b.hour).padStart(2, '0')}:00 - ${String(b.hour+1).padStart(2, '0')}:00</strong></td>
      <td>${b.customerName} <br><span style="font-size: 0.75rem; color: var(--text-muted);">${b.customerPhone}</span></td>
      <td>
        <span style="font-size: 0.75rem; color: #059669;">DP: Rp ${b.paidAmount.toLocaleString('id-ID')}</span><br>
        <span style="font-size: 0.75rem; color: ${b.remainingAmount > 0 ? '#dc2626; font-weight:700;' : '#64748b;'}">
          Sisa: Rp ${b.remainingAmount.toLocaleString('id-ID')}
        </span>
      </td>
      <td>
        <span class="ticket-badge ${isCheckedIn ? 'badge-fully-paid' : 'badge-partially-paid'}">
          ${isCheckedIn ? '✓ CHECKED-IN' : '⏳ DP LUNAS (50%)'}
        </span>
      </td>
      <td>
        ${!isCheckedIn ? `
          <button class="btn btn-accent" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; width: auto;" onclick="loadBookingForSettlement('${b.bookingCode}')">
            Proses Pelunasan
          </button>
        ` : `
          <span style="font-size: 0.75rem; color: var(--text-muted);">Selesai</span>
        `}
      </td>
    `;
    tbody.appendChild(tr);
  });
}

// Cari & Muat data booking ke modul pelunasan
function searchAndLoadBooking() {
  const code = document.getElementById("searchBookingCode").value.trim();
  if (!code) {
    alert("Masukkan kode booking atau token QR!");
    return;
  }
  loadBookingForSettlement(code);
}

function loadBookingForSettlement(bookingCode) {
  const bookings = FutsalStore.getBookings();
  const b = bookings.find(item => item.bookingCode.toUpperCase() === bookingCode.toUpperCase());

  const container = document.getElementById("settlementDetailBox");
  if (!b) {
    alert(`Kode booking "${bookingCode}" tidak ditemukan di database.`);
    container.style.display = "none";
    return;
  }

  container.style.display = "block";
  document.getElementById("detailBookingCode").innerText = b.bookingCode;
  document.getElementById("detailCustomer").innerText = `${b.customerName} (${b.customerPhone})`;
  document.getElementById("detailCourt").innerText = `${b.courtName} | ${b.date} Jam ${String(b.hour).padStart(2, '0')}:00`;
  document.getElementById("detailTotalPrice").innerText = `Rp ${b.totalPrice.toLocaleString('id-ID')}`;
  document.getElementById("detailPaidDP").innerText = `Rp ${b.paidAmount.toLocaleString('id-ID')} (DP 50% Online)`;
  
  const remainingEl = document.getElementById("detailRemaining");
  remainingEl.innerText = `Rp ${b.remainingAmount.toLocaleString('id-ID')}`;

  const actionBox = document.getElementById("settlementActionButtons");
  if (b.status === "CHECKED_IN" || b.status === "FULLY_PAID") {
    actionBox.innerHTML = `
      <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.75rem; border-radius: 6px; font-weight: 600; text-align: center;">
        ✓ Pelanggan ini sudah lunas & check-in pada: ${new Date(b.checkedInAt).toLocaleTimeString('id-ID')}
      </div>
    `;
  } else {
    actionBox.innerHTML = `
      <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
        <button class="btn btn-accent" onclick="executeSettlement('${b.bookingCode}', 'CASH')">
          💵 Terima Tunai (Rp ${b.remainingAmount.toLocaleString('id-ID')})
        </button>
        <button class="btn btn-primary" onclick="executeSettlement('${b.bookingCode}', 'QRIS_KASIR')">
          📱 Terima QRIS Kasir (Rp ${b.remainingAmount.toLocaleString('id-ID')})
        </button>
      </div>
    `;
  }
}

// Eksekusi Pelunasan di Kasir
function executeSettlement(bookingCode, method) {
  const res = FutsalStore.settleBookingAtCashier(bookingCode, method);
  if (!res.success) {
    alert(res.reason);
    return;
  }

  alert(`✓ Pelunasan sisa tagihan 50% berhasil dicatat via ${method}! Status: CHECKED-IN.`);
  loadBookingForSettlement(bookingCode);
  refreshCashierView();
}

// Submit Reservasi Walk-In
function submitWalkInBooking() {
  const courtId = parseInt(document.getElementById("walkinCourt").value);
  const date = document.getElementById("kasirDateFilter").value || FutsalStore.getTodayString();
  const hour = parseInt(document.getElementById("walkinHour").value);
  const name = document.getElementById("walkinCustName").value.trim();
  const phone = document.getElementById("walkinCustPhone").value.trim();
  const isLunas = (document.getElementById("walkinPaymentOption").value === "LUNAS");

  if (!name) {
    alert("Masukkan nama tim/pelanggan walk-in!");
    return;
  }

  const res = FutsalStore.createWalkInBooking(courtId, date, hour, name, phone, isLunas);
  if (!res.success) {
    alert(res.reason);
    return;
  }

  alert(`✓ Reservasi Walk-In berhasil dibuat!\nKode Booking: ${res.booking.bookingCode}\nStatus: ${res.booking.status}`);
  document.getElementById("walkinCustName").value = "";
  document.getElementById("walkinCustPhone").value = "";
  refreshCashierView();
}

function handleResetData() {
  if (confirm("Reset ulang semua data booking dan kunci slot ke kondisi awal?")) {
    FutsalStore.resetAllData();
    alert("Data berhasil direset!");
    refreshCashierView();
  }
}
