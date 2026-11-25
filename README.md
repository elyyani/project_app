# ⚽ FutsalHub Arena — Sistem Pemesanan & POS Kasir Lapangan (Single-Venue)
**Arsitektur:** PHP Native 8+, MySQL InnoDB (ACID & Distributed Row-Locking), Vanilla JS & Pure CSS

---

## 📌 Ringkasan Sistem
**FutsalHub Arena** adalah platform reservasi dan manajemen kasir meja depan untuk venue lapangan futsal mandiri (*Single-Venue*) yang memiliki 2 lapangan:
1. **Lapangan 1 (Vinyl Indoor):** Lantai vinyl standar turnamen (Reguler Rp 120.000 / Peak Rp 160.000).
2. **Lapangan 2 (Rumput Sintetis):** Rumput sintetis peredam kejut (Reguler Rp 130.000 / Peak Rp 170.000).
*Jam Peak:* Dimulai dari pukul 16:00 hingga 24:00.

---

## 🏛️ Tiga Halaman Utama (3-Screen Ecosystem)

1. **`index.php` (Jadwal & Booking Portal):**
   * Menampilkan kalender 16 slot jam per hari (08:00 - 24:00, durasi 1 jam / slot).
   * **Mekanisme Kunci Slot 5 Menit (TTL 300 Detik):** Begitu pelanggan mengklik slot kosong, slot terkunci kuning khusus untuk pelanggan tersebut dengan hitung mundur 5 menit.
   * **Simulasi Payment Gateway DP 50%:** Pelanggan membayar uang muka 50% secara daring via QRIS dinamis.
   * **Tombol Uji Race Condition:** Mensimulasikan dua permintaan simultan yang berebut slot yang sama untuk membuktikan proteksi *Pessimistic Locking* di sistem terdistribusi.

2. **`kasir.php` (Dasbor Kasir & POS Lapangan):**
   * **Kartu Statistik Kas & Shift:** Menampilkan Total Booking, Penerimaan DP Online (50%), Pelunasan Kasir, dan Total Kas Masuk.
   * **Validasi E-Tiket & Pelunasan 50%:** Memasukkan kode booking pelanggan yang tiba di lapangan untuk menagih sisa 50% (Tunai / QRIS Kasir) dan mengubah status menjadi *Lunas / Checked-In*.
   * **Pencatatan Reservasi Walk-In:** Kasir dapat mendaftarkan tim yang datang langsung di lokasi dengan opsi bayar langsung lunas atau DP 50%.
   * **Tabel Jadwal Lapangan Real-Time:** Menampilkan seluruh pesanan hari ini lengkap dengan badge status dan aksi kasir.

3. **`tiket.php` (E-Tiket Digital Pelanggan):**
   * Menerbitkan tiket digital resmi dengan QR Code dinamis (`qrcode.min.js`).
   * Menampilkan rincian sewa, DP yang sudah lunas, sisa pelunasan di kasir, dan tombol cetak PDF (*Driverless Print*).
   * Terdapat tombol pintas menuju kasir untuk mempercepat validasi check-in.

---

## 🚀 Cara Menjalankan Aplikasi

### 1. Nyalakan MySQL
Pastikan service MySQL aktif (misalnya melalui **XAMPP**, **Laragon**, atau MySQL Service).

### 2. Inisialisasi Database (1-Klik)
Jalankan perintah ini di PowerShell / Terminal:
```bash
cd E:\Download\project_booking
php setup.php
```
*(Atau buka `http://localhost/project_booking/setup.php` di peramban browser).*

### 3. Jalankan Web Server
Gunakan server bawaan PHP:
```bash
php -S localhost:8000
```
Lalu buka browser Anda:
* **Halaman Booking Pelanggan:** `http://localhost:8000/index.php`
* **Halaman Kasir POS:** `http://localhost:8000/kasir.php`
* **Halaman E-Tiket:** `http://localhost:8000/tiket.php`

---

## 🧪 Alur Pengujian Fitur (CRUD & Sistem Terdistribusi)

1. **Uji Kunci Slot (5 Menit Lock):**
   * Di `index.php`, klik salah satu slot hijau (misal jam 19:00).
   * Slot seketika berubah menjadi warna kuning (*Dikunci 5 Menit*) dengan countdown timer berjalan mundur. Buka tab baru di browser lain, slot tersebut akan terkunci bagi pengunjung lain.
2. **Uji Simulasi Race Condition:**
   * Klik tombol **"🧪 Uji Simulasi Race Condition"** di bagian atas `index.php`.
   * Sistem akan menjalankan simulasi 2 user di PHP yang berebut slot yang sama di milidetik bersamaan. User A akan sukses mengunci slot, sedangkan User B akan ditolak (*Race Condition Prevented*).
3. **Uji Pembayaran DP 50% (Online):**
   * Isi nama pemesan (misal: *Arya - FC Garuda*), klik *"Bayar DP 50% Sekarang"*.
   * Klik tombol simulasi bayar berhasil. Slot berubah menjadi merah (*Booked / Terisi*) dan Anda otomatis diarahkan ke `tiket.php`.
4. **Uji Pelunasan Kasir:**
   * Salin kode booking dari e-tiket, buka `kasir.php`.
   * Tempelkan kode booking di kolom pencarian lalu klik *"Cari"*.
   * Klik tombol *"💵 Lunasi Tunai (Cash)"*. Status pesanan berubah menjadi Lunas dan omzet kasir bertambah.
5. **Uji Booking Walk-In:**
   * Di `kasir.php`, isi form Reservasi Walk-In (pilih lapangan, jam, nama tim), klik *"Catat Booking Walk-In"*. Jadwal langsung terisi merah di kalender.

---

## 📁 Struktur Berkas

```
project_booking/
├── config.php          # Konfigurasi venue FutsalHub, tarif, dan jam operasional
├── Database.php        # Koneksi PDO Singleton dengan mode ACID InnoDB
├── setup.php           # Installer & database migrator otomatis
├── api.php             # REST API backend engine untuk locking & CRUD transaksi
├── index.php           # Halaman Jadwal & Booking Pelanggan (08:00 - 24:00)
├── kasir.php           # Halaman Dasbor Kasir POS & Reservasi Walk-In
├── tiket.php           # Halaman E-Tiket Digital Pelanggan (QR Code)
├── css/
│   └── style.css       # Stylesheet murni (Pure CSS modern & responsif)
├── js/
│   ├── booking.js      # Logika interaktif kalender, grid slot, modal checkout
│   ├── kasir.js        # Logika dasbor kasir, validasi pelunasan, walk-in
│   ├── tiket.js        # Logika render QR Code dan e-tiket
│   ├── store.js        # Data engine sinkronisasi multi-tab & bridge ke api.php
│   └── qrcode.min.js   # Generator QR Code kanvas
├── PRD_FutsalHub_Arena.docx # Dokumen Word resmi PRD FutsalHub Arena
└── PRD_FutsalHub_Arena.md   # Dokumen Markdown PRD FutsalHub Arena
```
