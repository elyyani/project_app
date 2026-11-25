/**
 * booking.js
 * Logika Interaktif Halaman Pemesanan Pelanggan (Customer Booking Portal)
 */

let activeLock = null;
let lockCountdownInterval = null;
let userIdentifier = "usr_" + Math.random().toString(36).substring(2, 9); // ID unik sesi browser

document.addEventListener("DOMContentLoaded", () => {
  const dateInput = document.getElementById("datePicker");
  const courtSelect = document.getElementById("courtSelect");
  
  // Set default date to today
  dateInput.value = FutsalStore.getTodayString();
  dateInput.min = FutsalStore.getTodayString();

  // Event Listeners
  dateInput.addEventListener("change", renderSlots);
  courtSelect.addEventListener("change", renderSlots);

  window.addEventListener("futsal_data_updated", () => {
    renderSlots();
    updateActiveLockTimer();
  });

  // Render awal
  renderCourtInfo();
  renderSlots();

  // Auto refresh timer grid setiap 1 detik
  setInterval(() => {
    updateCountdownBadges();
  }, 1000);
});

function renderCourtInfo() {
  const courtId = parseInt(document.getElementById("courtSelect").value);
  const court = FutsalStore.getCourtById(courtId);
  const infoEl = document.getElementById("courtInfo");
  if (court && infoEl) {
    infoEl.innerHTML = `
      <div style="font-size: 0.95rem; font-weight: 600; color: var(--primary);">${court.name}</div>
      <div style="font-size: 0.85rem; color: var(--text-muted);">${court.desc}</div>
      <div style="font-size: 0.8rem; margin-top: 4px; color: #0284c7;">
        Tarif: Reguler (08:00-16:00) <strong>Rp ${court.rates.offPeak.toLocaleString('id-ID')}</strong> | 
        Peak/Malam (16:00-24:00) <strong>Rp ${court.rates.peak.toLocaleString('id-ID')}</strong>
      </div>
    `;
  }
}

function renderSlots() {
  renderCourtInfo();
  const courtId = parseInt(document.getElementById("courtSelect").value);
  const date = document.getElementById("datePicker").value;
  const gridEl = document.getElementById("slotsGrid");

  gridEl.innerHTML = "";

  // 16 slot: Jam 08:00 hingga 23:00 (Selesai 24:00)
  for (let hour = 8; hour <= 23; hour++) {
    const slotData = FutsalStore.getSlotStatus(courtId, date, hour);
    const card = document.createElement("div");
    card.className = `slot-card ${slotData.status.toLowerCase()}`;
    
    const timeLabel = `${String(hour).padStart(2, '0')}:00 - ${String(hour + 1).padStart(2, '0')}:00`;

    if (slotData.status === 'AVAILABLE') {
      card.innerHTML = `
        <div class="slot-time">${timeLabel}</div>
        <div class="slot-price">Rp ${slotData.price.toLocaleString('id-ID')}</div>
        <div class="slot-status" style="color: #059669;">Tersedia</div>
      `;
      card.onclick = () => onSelectSlot(courtId, date, hour, slotData.price);
    } 
    else if (slotData.status === 'LOCKED') {
      const isMine = (activeLock && activeLock.slotKey === slotData.lock.slotKey);
      card.innerHTML = `
        <div class="slot-time">${timeLabel}</div>
        <div class="slot-status" style="color: #d97706;">
          ${isMine ? '🔒 Sedang Anda Pesan' : '⏳ Sedang Diproses'}
        </div>
        <div class="slot-countdown" id="cd_${courtId}_${hour}">
          Tersisa: ${slotData.remainingSeconds}s
        </div>
      `;
      if (isMine) {
        card.style.border = "2px dashed #d97706";
        card.onclick = () => openCheckoutModal(activeLock);
      } else {
        card.onclick = () => {
          showToast(`⚠️ RACE CONDITION: Slot ini sedang dikunci oleh pengunjung lain! Tunggu hingga waktu kunci habis.`);
        };
      }
    } 
    else if (slotData.status === 'BOOKED') {
      card.innerHTML = `
        <div class="slot-time">${timeLabel}</div>
        <div class="slot-price" style="text-decoration: line-through; opacity: 0.7;">Rp ${slotData.booking.totalPrice.toLocaleString('id-ID')}</div>
        <div class="slot-status" style="color: #dc2626;">Terisi (Booked)</div>
      `;
      card.onclick = () => {
        showToast(`❌ Slot ini sudah dibooking (${slotData.booking.customerName}).`);
      };
    }

    gridEl.appendChild(card);
  }
}

function updateCountdownBadges() {
  const courtId = parseInt(document.getElementById("courtSelect").value);
  const date = document.getElementById("datePicker").value;

  for (let hour = 8; hour <= 23; hour++) {
    const slotData = FutsalStore.getSlotStatus(courtId, date, hour);
    const cdEl = document.getElementById(`cd_${courtId}_${hour}`);
    if (cdEl && slotData.status === 'LOCKED') {
      cdEl.innerText = `Tersisa: ${slotData.remainingSeconds}s`;
    }
  }
}

function onSelectSlot(courtId, date, hour, price) {
  // Ambil Distributed Lock 5 Menit
  const lockRes = FutsalStore.acquireLock(courtId, date, hour, userIdentifier);

  if (!lockRes.success) {
    alert(lockRes.reason);
    renderSlots();
    return;
  }

  activeLock = lockRes.lock;
  openCheckoutModal(activeLock);
  renderSlots();
}

function openCheckoutModal(lock) {
  const modal = document.getElementById("checkoutModal");
  const court = FutsalStore.getCourtById(lock.courtId);

  document.getElementById("modalCourtName").innerText = court ? court.name : `Lapangan ${lock.courtId}`;
  document.getElementById("modalDateTime").innerText = `${lock.date} | Jam ${String(lock.hour).padStart(2, '0')}:00 - ${String(lock.hour + 1).padStart(2, '0')}:00`;
  
  document.getElementById("billTotal").innerText = `Rp ${lock.price.toLocaleString('id-ID')}`;
  document.getElementById("billDP").innerText = `Rp ${lock.dpAmount.toLocaleString('id-ID')}`;
  document.getElementById("billRemaining").innerText = `Rp ${lock.remainingAmount.toLocaleString('id-ID')}`;

  modal.classList.add("active");
  startLockTimer(lock.expiresAt);
}

function closeCheckoutModal(release = false) {
  const modal = document.getElementById("checkoutModal");
  modal.classList.remove("active");
  clearInterval(lockCountdownInterval);

  if (release && activeLock) {
    FutsalStore.releaseLock(activeLock.courtId, activeLock.date, activeLock.hour, userIdentifier);
    activeLock = null;
    showToast("Kunci slot telah dilepas.");
    renderSlots();
  }
}

function startLockTimer(expiresAt) {
  clearInterval(lockCountdownInterval);

  function tick() {
    const remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
    const mins = Math.floor(remaining / 60);
    const secs = remaining % 60;
    const timerDisplay = document.getElementById("lockTimerDisplay");
    
    if (timerDisplay) {
      timerDisplay.innerText = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    if (remaining <= 0) {
      clearInterval(lockCountdownInterval);
      alert("Waktu kunci 5 menit Anda telah kedaluwarsa! Slot dilepaskan kembali.");
      closeCheckoutModal(true);
    }
  }

  tick();
  lockCountdownInterval = setInterval(tick, 1000);
}

function updateActiveLockTimer() {
  if (activeLock) {
    const slotStatus = FutsalStore.getSlotStatus(activeLock.courtId, activeLock.date, activeLock.hour);
    if (slotStatus.status !== 'LOCKED') {
      // Lock lepas
      clearInterval(lockCountdownInterval);
      activeLock = null;
      document.getElementById("checkoutModal").classList.remove("active");
    }
  }
}

// Beralih ke Modal Pembayaran DP
function proceedToPaymentDP() {
  const name = document.getElementById("custName").value.trim();
  const phone = document.getElementById("custPhone").value.trim();

  if (!name || !phone) {
    alert("Mohon isi Nama Pemesan dan Nomor WhatsApp aktif!");
    return;
  }

  // Tutup checkout modal, buka payment gateway modal
  document.getElementById("checkoutModal").classList.remove("active");
  const payModal = document.getElementById("paymentModal");
  payModal.classList.add("active");

  document.getElementById("payAmountText").innerText = `Rp ${activeLock.dpAmount.toLocaleString('id-ID')}`;

  // Render Simulated Dynamic QRIS
  const qrisPayload = `00020101021226600016ID.CO.QRIS.WWW0118936009990000000000520458125303360540${activeLock.dpAmount}5802ID5914FUTSALHUB ARENA6007JAKARTA62070703A016304`;
  window.renderQRCode("qrisContainer", qrisPayload, 170);
}

function closePaymentModal() {
  document.getElementById("paymentModal").classList.remove("active");
  // Buka kembali modal checkout jika lock masih aktif
  if (activeLock) {
    document.getElementById("checkoutModal").classList.add("active");
  }
}

// Eksekusi Simulasi Pembayaran DP Berhasil
function simulateSuccessPayment() {
  const name = document.getElementById("custName").value.trim();
  const phone = document.getElementById("custPhone").value.trim();

  const confirmRes = FutsalStore.confirmBookingWithDP(
    activeLock.courtId,
    activeLock.date,
    activeLock.hour,
    name,
    phone,
    userIdentifier
  );

  if (!confirmRes.success) {
    alert(confirmRes.reason);
    closePaymentModal();
    return;
  }

  clearInterval(lockCountdownInterval);
  activeLock = null;
  document.getElementById("paymentModal").classList.remove("active");

  // Buka E-Tiket
  window.location.href = `tiket.php?code=${confirmRes.booking.bookingCode}`;
}

// Fitur Simulasi Race Condition / Pengujian Sistem Terdistribusi
function simulateRaceCondition() {
  const courtId = parseInt(document.getElementById("courtSelect").value);
  const date = document.getElementById("datePicker").value;
  
  // Cari slot kosong pertama
  let targetHour = null;
  for (let h = 8; h <= 23; h++) {
    const s = FutsalStore.getSlotStatus(courtId, date, h);
    if (s.status === 'AVAILABLE') {
      targetHour = h;
      break;
    }
  }

  if (targetHour === null) {
    alert("Semua slot hari ini sudah terisi atau terkunci! Silakan pilih tanggal lain.");
    return;
  }

  const userA = "User_Simulasi_A";
  const userB = "User_Simulasi_B";

  // Simulasi dua request datang di milidetik bersamaan
  const reqA = FutsalStore.acquireLock(courtId, date, targetHour, userA);
  const reqB = FutsalStore.acquireLock(courtId, date, targetHour, userB);

  // Sync uji konkurensi ke MySQL Backend PHP
  fetch(`api.php?action=simulate_race_condition&court_id=${courtId}&date=${date}&hour=${targetHour}`).catch(() => {});

  const hourLabel = `${String(targetHour).padStart(2, '0')}:00`;

  alert(
    `=== SIMULASI KONKURENSI SISTEM TERDISTRIBUSI ===\n\n` +
    `Percobaan mengunci slot jam ${hourLabel} oleh dua user di waktu bersamaan:\n\n` +
    `1. Request User A: ${reqA.success ? 'BERHASIL DITERIMA (Lock Acquired)' : 'GAGAL'}\n` +
    `2. Request User B: ${reqB.success ? 'BERHASIL' : 'DITOLAK (Race Condition Prevented!)\n   Alasan: ' + reqB.reason}\n\n` +
    `Hasil: Mekanisme atomik (Pessimistic Locking) berhasil mencegah double-booking!`
  );

  renderSlots();
}

function showToast(message) {
  let container = document.getElementById("toastContainer");
  if (!container) {
    container = document.createElement("div");
    container.id = "toastContainer";
    container.className = "toast-container";
    document.body.appendChild(container);
  }

  const toast = document.createElement("div");
  toast.className = "toast";
  toast.innerText = message;
  container.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 4000);
}
