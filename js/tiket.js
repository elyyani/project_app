/**
 * tiket.js
 * Logika Render E-Tiket Digital & Pembuatan QR Code
 */

document.addEventListener("DOMContentLoaded", () => {
  const urlParams = new URLSearchParams(window.location.search);
  const code = urlParams.get("code");

  const bookings = FutsalStore.getBookings();
  let booking = null;

  if (code) {
    booking = bookings.find(b => b.bookingCode.toUpperCase() === code.toUpperCase());
  } else if (bookings.length > 0) {
    // Ambil booking terakhir jika tidak ada query param
    booking = bookings[bookings.length - 1];
  }

  const container = document.getElementById("ticketContainer");

  if (!booking) {
    container.innerHTML = `
      <div style="text-align: center; padding: 3rem; background: #fff; border-radius: 8px;">
        <h3>Tiket Tidak Ditemukan</h3>
        <p style="color: var(--text-muted); margin-top: 0.5rem;">Tidak ada data pemesanan yang cocok.</p>
        <a href="index.php" class="btn btn-primary" style="margin-top: 1rem; width: auto; display: inline-flex;">
          Kembali ke Jadwal
        </a>
      </div>
    `;
    return;
  }

  renderTicketUI(booking);
});

function renderTicketUI(b) {
  const isCheckedIn = (b.status === "CHECKED_IN" || b.status === "FULLY_PAID");

  document.getElementById("ticketCode").innerText = b.bookingCode;
  document.getElementById("ticketCourt").innerText = b.courtName;
  document.getElementById("ticketDate").innerText = b.date;
  document.getElementById("ticketTime").innerText = `Jam ${String(b.hour).padStart(2, '0')}:00 - ${String(b.hour+1).padStart(2, '0')}:00`;
  document.getElementById("ticketCustomer").innerText = `${b.customerName} (${b.customerPhone})`;

  document.getElementById("ticketTotal").innerText = `Rp ${b.totalPrice.toLocaleString('id-ID')}`;
  document.getElementById("ticketDP").innerText = `Rp ${b.paidAmount.toLocaleString('id-ID')}`;
  document.getElementById("ticketRemaining").innerText = `Rp ${b.remainingAmount.toLocaleString('id-ID')}`;

  const badgeEl = document.getElementById("ticketStatusBadge");
  const alertBox = document.getElementById("ticketNoticeBox");

  if (isCheckedIn) {
    badgeEl.className = "ticket-badge badge-fully-paid";
    badgeEl.innerText = "✓ LUNAS & CHECKED-IN";
    alertBox.innerHTML = `
      <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.85rem; border-radius: 6px; font-size: 0.85rem;">
        <strong>Status Valid!</strong> Tiket telah diverifikasi di meja kasir. Selamat bermain!
      </div>
    `;
  } else {
    badgeEl.className = "ticket-badge badge-partially-paid";
    badgeEl.innerText = "⏳ DP LUNAS (50%) - BELUM LUNAS";
    alertBox.innerHTML = `
      <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; color: #92400e; padding: 0.85rem; border-radius: 6px; font-size: 0.85rem;">
        <strong>Perhatian Penting:</strong> Uang muka 50% berhasil dibayarkan. Tunjukkan QR Code ini kepada kasir saat tiba di lokasi untuk melunasi sisa tagihan <strong>Rp ${b.remainingAmount.toLocaleString('id-ID')}</strong> (Tunai/QRIS Kasir) sebelum masuk lapangan.
      </div>
    `;
  }

  // Render QR Code yang menyimpan kode booking
  window.renderQRCode("ticketQrCanvas", b.bookingCode, 180);

  // Link buka di kasir
  const cashierLink = document.getElementById("btnOpenInCashier");
  if (cashierLink) {
    cashierLink.href = `kasir.php?search=${encodeURIComponent(b.bookingCode)}`;
  }
}
