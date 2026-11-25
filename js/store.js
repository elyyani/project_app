/**
 * store.js
 * Engine Simulasi Basis Data & Distributed Lock Terdistribusi (LocalStorage Engine)
 * Memfasilitasi sinkronisasi multi-tab secara real-time via storage event
 */

const FutsalStore = (function() {
  const LOCK_DURATION_MS = 5 * 60 * 1000; // 5 Menit (300.000 ms) Sesuai PRD

  const INITIAL_COURTS = [
    {
      id: 1,
      name: "Lapangan 1 (Vinyl Indoor)",
      type: "Vinyl",
      desc: "Lantai vinyl berkualitas standar turnamen, nyaman untuk kontrol bola.",
      rates: { offPeak: 120000, peak: 160000 }
    },
    {
      id: 2,
      name: "Lapangan 2 (Rumput Sintetis)",
      type: "Rumput Sintetis",
      desc: "Rumput sintetis halus dengan peredam kejut rubber, cocok untuk permainan cepat.",
      rates: { offPeak: 130000, peak: 170000 }
    }
  ];

  function getTodayString() {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function getCourts() {
    return INITIAL_COURTS;
  }

  function getCourtById(id) {
    return INITIAL_COURTS.find(c => c.id === parseInt(id));
  }

  function getHourlyRate(courtId, hour) {
    const court = getCourtById(courtId);
    if (!court) return 120000;
    // Jam 16:00 ke atas dihitung Peak Hours
    return (hour >= 16) ? court.rates.peak : court.rates.offPeak;
  }

  // --- Lock Operations ---
  function getLocks() {
    try {
      return JSON.parse(localStorage.getItem("futsal_locks") || "{}");
    } catch (e) {
      return {};
    }
  }

  function saveLocks(locks) {
    localStorage.setItem("futsal_locks", JSON.stringify(locks));
    window.dispatchEvent(new Event("futsal_data_updated"));
  }

  function cleanExpiredLocks() {
    const locks = getLocks();
    const now = Date.now();
    let changed = false;

    for (const key in locks) {
      if (locks[key].expiresAt <= now) {
        delete locks[key];
        changed = true;
      }
    }
    if (changed) {
      saveLocks(locks);
    }
  }

  function makeSlotKey(courtId, date, hour) {
    return `${courtId}_${date}_${hour}`;
  }

  function acquireLock(courtId, date, hour, userIdentifier) {
    cleanExpiredLocks();
    const slotKey = makeSlotKey(courtId, date, hour);
    const locks = getLocks();
    const bookings = getBookings();

    // 1. Cek apakah sudah dibooking / lunas
    const existingBooking = bookings.find(b => b.courtId == courtId && b.date == date && b.hour == hour && b.status !== 'CANCELLED');
    if (existingBooking) {
      return { success: false, reason: "Slot ini sudah selesai dibooking oleh pelanggan lain!" };
    }

    // 2. Cek apakah sedang dikunci pengguna lain (Concurrency Race Condition Check)
    const existingLock = locks[slotKey];
    const now = Date.now();
    if (existingLock && existingLock.expiresAt > now) {
      if (existingLock.lockedBy !== userIdentifier) {
        const remainingSeconds = Math.ceil((existingLock.expiresAt - now) / 1000);
        return { 
          success: false, 
          reason: `RACE CONDITION TERDETEKSI: Slot sedang dikunci oleh pengunjung lain! Tersisa ${remainingSeconds} detik.` 
        };
      } else {
        // Pengguna yang sama memperpanjang / tetap memiliki lock
        return { success: true, lock: existingLock };
      }
    }

    // 3. Kunci Berhasil Diambil (Redis Atomic SETNX simulation)
    const rate = getHourlyRate(courtId, hour);
    const lockObj = {
      slotKey: slotKey,
      courtId: parseInt(courtId),
      date: date,
      hour: parseInt(hour),
      lockedBy: userIdentifier,
      createdAt: now,
      expiresAt: now + LOCK_DURATION_MS,
      price: rate,
      dpAmount: rate * 0.5,
      remainingAmount: rate * 0.5
    };

    locks[slotKey] = lockObj;
    saveLocks(locks);

    // Sync ke Backend PHP MySQL
    try {
      fetch('api.php?action=acquire_lock', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `court_id=${courtId}&date=${date}&hour=${hour}&locked_by=${encodeURIComponent(userIdentifier)}`
      }).catch(() => {});
    } catch(e) {}

    return { success: true, lock: lockObj };
  }

  function releaseLock(courtId, date, hour, userIdentifier) {
    const slotKey = makeSlotKey(courtId, date, hour);
    const locks = getLocks();
    if (locks[slotKey]) {
      // Izinkan lepas jika milik user bersangkutan atau force release
      if (!userIdentifier || locks[slotKey].lockedBy === userIdentifier) {
        delete locks[slotKey];
        saveLocks(locks);

        // Sync ke Backend PHP MySQL
        try {
          fetch('api.php?action=release_lock', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `court_id=${courtId}&date=${date}&hour=${hour}&locked_by=${encodeURIComponent(userIdentifier || '')}`
          }).catch(() => {});
        } catch(e) {}

        return true;
      }
    }
    return false;
  }

  function getSlotStatus(courtId, date, hour) {
    cleanExpiredLocks();
    const bookings = getBookings();
    const booked = bookings.find(b => b.courtId == courtId && b.date == date && b.hour == hour && b.status !== 'CANCELLED');
    if (booked) {
      return { 
        status: 'BOOKED', 
        booking: booked 
      };
    }

    const locks = getLocks();
    const slotKey = makeSlotKey(courtId, date, hour);
    const lock = locks[slotKey];
    if (lock && lock.expiresAt > Date.now()) {
      return { 
        status: 'LOCKED', 
        lock: lock,
        remainingSeconds: Math.ceil((lock.expiresAt - Date.now()) / 1000)
      };
    }

    return { status: 'AVAILABLE', price: getHourlyRate(courtId, hour) };
  }

  // --- Booking Operations ---
  function getBookings() {
    try {
      return JSON.parse(localStorage.getItem("futsal_bookings") || "[]");
    } catch (e) {
      return [];
    }
  }

  function saveBookings(bookings) {
    localStorage.setItem("futsal_bookings", JSON.stringify(bookings));
    window.dispatchEvent(new Event("futsal_data_updated"));
  }

  function confirmBookingWithDP(courtId, date, hour, customerName, customerPhone, userIdentifier) {
    cleanExpiredLocks();
    const slotKey = makeSlotKey(courtId, date, hour);
    const locks = getLocks();
    const currentLock = locks[slotKey];

    // Validasi apakah lock masih valid
    if (!currentLock || currentLock.expiresAt <= Date.now()) {
      return { success: false, reason: "Batas waktu kunci 5 menit telah habis! Silakan pilih slot kembali." };
    }

    const bookings = getBookings();
    const randomCode = Math.floor(1000 + Math.random() * 9000);
    const bookingCode = `FUT-${date.replace(/-/g, '')}-${hour}-${randomCode}`;
    const court = getCourtById(courtId);

    const newBooking = {
      id: "b_" + Date.now(),
      bookingCode: bookingCode,
      courtId: parseInt(courtId),
      courtName: court ? court.name : `Lapangan ${courtId}`,
      date: date,
      hour: parseInt(hour),
      customerName: customerName,
      customerPhone: customerPhone,
      totalPrice: currentLock.price,
      paidAmount: currentLock.dpAmount, // DP 50%
      remainingAmount: currentLock.remainingAmount, // Sisa 50%
      status: "PARTIALLY_PAID", // DP Lunas, sisa di kasir
      paymentMethodDP: "QRIS_SIMULATED",
      createdAt: new Date().toISOString(),
      checkedInAt: null,
      settledAt: null,
      settlementMethod: null
    };

    bookings.push(newBooking);
    saveBookings(bookings);

    // Hapus lock karena sudah jadi booking permanen
    delete locks[slotKey];
    saveLocks(locks);

    // Sync ke Backend PHP MySQL
    try {
      fetch('api.php?action=create_booking_dp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          court_id: courtId,
          date: date,
          hour: hour,
          customer_name: customerName,
          phone_number: customerPhone,
          locked_by: userIdentifier
        })
      }).catch(() => {});
    } catch(e) {}

    return { success: true, booking: newBooking };
  }

  function settleBookingAtCashier(bookingCode, paymentMethod) {
    const bookings = getBookings();
    const b = bookings.find(item => item.bookingCode === bookingCode);
    if (!b) return { success: false, reason: "Kode booking tidak ditemukan!" };

    if (b.status === "CHECKED_IN" || b.status === "FULLY_PAID") {
      return { success: false, reason: "Booking ini sudah lunas dan telah di-check in sebelumnya!" };
    }

    b.paidAmount = b.totalPrice;
    b.remainingAmount = 0;
    b.status = "CHECKED_IN";
    b.settledAt = new Date().toISOString();
    b.checkedInAt = new Date().toISOString();
    b.settlementMethod = paymentMethod; // "CASH" atau "QRIS_KASIR"

    saveBookings(bookings);

    // Sync ke Backend PHP MySQL
    try {
      fetch('api.php?action=settle_booking', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `booking_code=${encodeURIComponent(bookingCode)}&payment_method=${encodeURIComponent(paymentMethod)}`
      }).catch(() => {});
    } catch(e) {}

    return { success: true, booking: b };
  }

  function createWalkInBooking(courtId, date, hour, customerName, customerPhone, isLunas) {
    cleanExpiredLocks();
    const slotKey = makeSlotKey(courtId, date, hour);
    const bookings = getBookings();

    // Pastikan slot tidak sedang dipakai
    const existing = bookings.find(b => b.courtId == courtId && b.date == date && b.hour == hour && b.status !== 'CANCELLED');
    if (existing) {
      return { success: false, reason: "Slot ini sudah terisi!" };
    }

    const rate = getHourlyRate(courtId, hour);
    const randomCode = Math.floor(1000 + Math.random() * 9000);
    const bookingCode = `FUT-WALK-${date.replace(/-/g, '')}-${hour}-${randomCode}`;
    const court = getCourtById(courtId);

    const paidAmt = isLunas ? rate : (rate * 0.5);
    const remAmt = isLunas ? 0 : (rate * 0.5);
    const status = isLunas ? "CHECKED_IN" : "PARTIALLY_PAID";

    const newBooking = {
      id: "b_" + Date.now(),
      bookingCode: bookingCode,
      courtId: parseInt(courtId),
      courtName: court ? court.name : `Lapangan ${courtId}`,
      date: date,
      hour: parseInt(hour),
      customerName: customerName + " (Walk-In)",
      customerPhone: customerPhone || "-",
      totalPrice: rate,
      paidAmount: paidAmt,
      remainingAmount: remAmt,
      status: status,
      paymentMethodDP: "CASH_WALKIN",
      createdAt: new Date().toISOString(),
      checkedInAt: isLunas ? new Date().toISOString() : null,
      settledAt: isLunas ? new Date().toISOString() : null,
      settlementMethod: isLunas ? "CASH" : null
    };

    bookings.push(newBooking);
    saveBookings(bookings);

    // Bersihkan lock jika ada
    const locks = getLocks();
    if (locks[slotKey]) {
      delete locks[slotKey];
      saveLocks(locks);
    }

    // Sync ke Backend PHP MySQL
    try {
      fetch('api.php?action=walkin_booking', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          court_id: courtId,
          date: date,
          hour: hour,
          customer_name: customerName,
          phone_number: customerPhone,
          payment_option: isLunas ? 'LUNAS' : 'DP50'
        })
      }).catch(() => {});
    } catch(e) {}

    return { success: true, booking: newBooking };
  }

  function getDailyStats(targetDate) {
    const date = targetDate || getTodayString();
    const bookings = getBookings().filter(b => b.date === date && b.status !== 'CANCELLED');
    
    let totalDPOnline = 0;
    let totalCashierPaid = 0;
    let totalRevenue = 0;

    bookings.forEach(b => {
      // DP Online
      if (b.paymentMethodDP === "QRIS_SIMULATED") {
        totalDPOnline += (b.totalPrice * 0.5);
      } else if (b.paymentMethodDP === "CASH_WALKIN") {
        totalCashierPaid += (b.totalPrice * 0.5);
      }
      
      // Pelunasan di kasir
      if (b.status === "CHECKED_IN" || b.status === "FULLY_PAID") {
        if (b.settlementMethod) {
          totalCashierPaid += (b.totalPrice * 0.5);
        }
      }
      totalRevenue += b.paidAmount;
    });

    return {
      date: date,
      totalBookings: bookings.length,
      totalDPOnline: totalDPOnline,
      totalCashierPaid: totalCashierPaid,
      totalRevenue: totalRevenue
    };
  }

  function seedDemoDataIfEmpty() {
    const today = getTodayString();
    const bookings = getBookings();
    if (bookings.length === 0) {
      const demoBookings = [
        {
          id: "demo_1",
          bookingCode: `FUT-${today.replace(/-/g, '')}-16-1024`,
          courtId: 1,
          courtName: "Lapangan 1 (Vinyl Indoor)",
          date: today,
          hour: 16,
          customerName: "Budi Santoso (Tim Garuda)",
          customerPhone: "081234567890",
          totalPrice: 160000,
          paidAmount: 80000,
          remainingAmount: 80000,
          status: "PARTIALLY_PAID",
          paymentMethodDP: "QRIS_SIMULATED",
          createdAt: new Date(Date.now() - 3600000).toISOString(),
          checkedInAt: null,
          settledAt: null,
          settlementMethod: null
        },
        {
          id: "demo_2",
          bookingCode: `FUT-${today.replace(/-/g, '')}-19-5821`,
          courtId: 2,
          courtName: "Lapangan 2 (Rumput Sintetis)",
          date: today,
          hour: 19,
          customerName: "Rian Hidayat (FC Reuni)",
          customerPhone: "085678901234",
          totalPrice: 170000,
          paidAmount: 170000,
          remainingAmount: 0,
          status: "CHECKED_IN",
          paymentMethodDP: "QRIS_SIMULATED",
          createdAt: new Date(Date.now() - 7200000).toISOString(),
          checkedInAt: new Date(Date.now() - 1800000).toISOString(),
          settledAt: new Date(Date.now() - 1800000).toISOString(),
          settlementMethod: "CASH"
        }
      ];
      saveBookings(demoBookings);
    }
  }

  function resetAllData() {
    localStorage.removeItem("futsal_locks");
    localStorage.removeItem("futsal_bookings");
    try {
      fetch('api.php?action=reset_demo').catch(() => {});
    } catch(e) {}
    seedDemoDataIfEmpty();
    window.dispatchEvent(new Event("futsal_data_updated"));
  }

  // Multi-tab listener
  window.addEventListener('storage', (e) => {
    if (e.key === 'futsal_locks' || e.key === 'futsal_bookings') {
      window.dispatchEvent(new Event("futsal_data_updated"));
    }
  });

  // Jalankan inisialisasi awal
  seedDemoDataIfEmpty();

  // Periodik membersihkan lock kedaluwarsa setiap 5 detik
  setInterval(cleanExpiredLocks, 5000);

  return {
    LOCK_DURATION_MS,
    getTodayString,
    getCourts,
    getCourtById,
    getHourlyRate,
    acquireLock,
    releaseLock,
    getSlotStatus,
    confirmBookingWithDP,
    settleBookingAtCashier,
    createWalkInBooking,
    getBookings,
    getDailyStats,
    resetAllData
  };
})();
