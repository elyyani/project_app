# Product Requirements Document (PRD) — Versi 2.0
# FutsalHub Arena — Sistem Reservasi Mandiri, Portal Riwayat & POS Kasir (Single-Venue)

---

## Informasi Dokumen

| Properti | Nilai |
|---|---|
| **Judul Dokumen** | Product Requirements Document (PRD) — FutsalHub Arena Platform |
| **Versi Dokumen** | **2.0 (Definitif — Implementasi Lengkap, Sinkron Logika Terbaru & Dual Theme)** |
| **Tanggal Terbit** | 2026-11-25 |
| **Status Dokumen** | Disetujui (Approved for Production, Deployment & Evaluation) |
| **Pemilik Produk (Product Owner)** | Lead Technical PM & Systems Architect |
| **Target Pembaca** | Dosen Penguji, Frontend/Backend Developer, Staf Kasir Meja Depan, dan Owner Venue |

### Riwayat Perubahan Dokumen (Revision History)

| Versi | Tanggal | Penulis | Deskripsi Perubahan |
|---|---|---|---|
| **v1.0** | 2026-11-24 | Tim Product & Architecture | Rilis awal PRD: Venue tunggal FutsalHub Arena (Lapangan 1 Vinyl & Lapangan 2 Rumput Sintetis); 16 slot harian (08:00 - 24:00); Kunci slot 5 menit (TTL 300s); Pembayaran DP 50% + Pelunasan Kasir; 3 halaman dasar (`index.php`, `kasir.php`, `tiket.php`). |
| **v2.0** | 2026-11-25 | Tim Product & Architecture | **Penyelarasan Penuh dengan Logika & Desain Terbaru:**<br>1. **Penambahan Epik 4 Portal Riwayat (`riwayat.php`):** Fitur pencarian instan live query, sistem filter 3 dropdown elegan (*Status Reservasi*, *Jenis Lapangan*, *Waktu/Jadwal*), counter data real-time, dan tombol reset filter otomatis.<br>2. **Restrukturisasi Alur 5 Langkah Pemesanan (`index.php`):** Langkah 1 (Pilih Lapangan), Langkah 2 (Pilih Tanggal 7 hari ke depan), Langkah 3 (Kunci Jam 08:00–24:00 dengan slot card tabular-nums 1 baris anti-wrap), Langkah 4 (Form Pemesan & Bayar DP 50% via QRIS barcode dinamis), Langkah 5 (Tiket Digital & Riwayat).<br>3. **Indikator Langkah Dinamis (Dynamic Single-Active Orange Step Indicator):** Memastikan hanya 1 langkah aktif berwarna oranye pada satu waktu; berpindah otomatis saat klik tanggal (Langkah 2), jam (Langkah 3), pembayaran (Langkah 4), dan tiket (Langkah 5), didukung viewport *Scroll Spy*.<br>4. **Editorial Header & Dual Theme System:** Visual header cinematic editorial berpadu *Marquee Ticker Tape*, navigasi aktif dengan indikator warna, tombol pengalih tema Gelap/Terang (*Dark/Light mode*) di pojok kanan atas, serta penyesuaian terminologi *"2 tipe lapangan"* menjadi *"Jenis Lapangan"*. |

---

## Daftar Isi

- [1. Executive Summary & Visi Produk](#1-executive-summary--visi-produk)
- [2. Posisi Strategis PRD dalam Hirarki Rekayasa](#2-posisi-strategis-prd-dalam-hirarki-rekayasa)
- [3. Persona Pengguna & Empathy Map](#3-persona-pengguna--empathy-map)
- [4. Prinsip Desain Produk & Filosofi UX](#4-prinsip-desain-produk--filosofi-ux)
- [5. Arsitektur Komponen & Peta Layar Aplikasi](#5-arsitektur-komponen--peta-layar-aplikasi)
- [6. Epik Produk & Spesifikasi Fitur Terperinci (Gherkin Acceptance Criteria)](#6-epik-produk--spesifikasi-fitur-terperinci-gherkin-acceptance-criteria)
  - [6.1 Epik 1: Portal Pemesanan Pelanggan & Alur 5 Langkah (index.php)](#61-epik-1-portal-pemesanan-pelanggan--alur-5-langkah-indexphp)
  - [6.2 Epik 2: Portal Pelacakan & Cek Riwayat Booking (riwayat.php)](#62-epik-2-portal-pelacakan--cek-riwayat-booking-riwayatphp)
  - [6.3 Epik 3: Dasbor Kasir & POS Meja Depan (kasir.php)](#63-epik-3-dasbor-kasir--pos-meja-depan-kasirphp)
  - [6.4 Epik 4: E-Tiket Digital & Validasi QR (tiket.php)](#64-epik-4-e-tiket-digital--validasi-qr-tiketphp)
  - [6.5 Epik 5: Backend REST API & Concurrency Lock Engine (api.php & BookingEngine.php)](#65-epik-5-backend-rest-api--concurrency-lock-engine-apiphp--bookingenginephp)
- [7. Matriks Status Tampilan Layar (UI States Matrix)](#7-matriks-status-tampilan-layar-ui-states-matrix)
- [8. Telemetri Produk, Funnel Konversi & North Star Metric](#8-telemetri-produk-funnel-konversi--north-star-metric)
- [9. Kepatuhan Regulasi & Alur Pengalaman Keamanan (Security UX)](#9-kepatuhan-regulasi--alur-pengalaman-keamanan-security-ux)
- [10. Strategi Peluncuran & Kriteria Kesiapan Rilis (Go/No-Go Checklist)](#10-strategi-peluncuran--kriteria-kesiapan-rilis-gono-go-checklist)

---

## 1. Executive Summary & Visi Produk

**FutsalHub Arena Platform** adalah ekosistem reservasi lapangan futsal berbasis web (*Single-Venue*) yang dirancang khusus untuk memodernisasi operasional 2 jenis lapangan utama di venue:
* **Lapangan 1 (Vinyl Indoor Turnamen):** Lantai interlocking polypropylene standar kompetisi nasional (Tarif Reguler Siang: Rp 120.000 / Peak Malam: Rp 160.000).
* **Lapangan 2 (Rumput Sintetis Premium):** Rumput sintetis monofilament 50mm dengan rubber granules peredam benturan (Tarif Reguler Siang: Rp 130.000 / Peak Malam: Rp 170.000).

Sistem ini menuntaskan kendala operasional klasik di lapangan futsal:
1. **Bentrok Jadwal (*Double-Booking*):** Diatasi dengan algoritma penguncian slot terdistribusi 5 menit (*Atomic Pessimistic Lock*).
2. **Penyewa Mangkir (*No-Show*):** Dipangkas hingga $< 1\%$ melalui skema wajib Uang Muka (DP 50%) online via QRIS.
3. **Pencarian Riwayat yang Lambat:** Diatasi dengan portal Cek Booking mandiri (`riwayat.php`) berfitur 3 dropdown filter dan pencarian instan.
4. **Antrean Meja Kasir:** Diatasi dengan terminal POS meja depan (`kasir.php`) berkecepatan tinggi ($\le 10$ detik per pelunasan).

### Visi Produk
> *"Menghadirkan platform reservasi futsal mandiri yang transparan, bebas antrean, dan 100% bebas bentrok jadwal, didukung mekanisme kunci atomik 5 menit, pembayaran uang muka QRIS instan, serta portal riwayat pemesanan yang elegan dan mudah digunakan."*

---

## 2. Posisi Strategis PRD dalam Hirarki Rekayasa

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  BRD (Business Requirements Document)                                       │
│  "Mengapa sistem ini dibangun?" ──► Efisiensi Venue Futsal, Zero Bentrok    │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  PRD (Product Requirements Document) [DOKUMEN INI - v2.0]                   │
│  "Apa yang kita bangun & bagaimana pengalaman pengguna (UX) terwujud?"      │
│  ──► 5 Halaman Web, Alur 5 Langkah, Dropdown Filter, Lock 5 Menit, QRIS     │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  System Architecture & Implementation (PHP Native + MySQL InnoDB)           │
│  "Bagaimana sistem direkayasa di balik layar?"                              │
│  ──► BookingEngine, REST API, Concurrency Locks, ProMax Soft-UI, Dual Theme│
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Persona Pengguna & Empathy Map

### 3.1 Persona Pelanggan: "Ferry Nugroho (23 Tahun)"
* **Profil:** Mahasiswa tingkat akhir merangkap kapten tim amatir "Spartan FC", rutin mengorganisir jadwal main futsal 2 kali seminggu.
* **Tujuan (Goals):**
  - Memilih tanggal main hingga 7 hari ke depan dan memilih jam malam (19:00 - 21:00) secara mandiri.
  - Mendapat kepastian slot tidak diserobot pengguna lain saat sedang mengisi identitas dan membayar DP.
  - Melacak status pemesanan melalui nomor WhatsApp atau kode booking tanpa login akun.
* **Titik Frustrasi (Pains):**
  - Tampilan filter yang berantakan atau tombol status yang membingungkan.
  - Indikator alur yang macet (*stuck*) sehingga tidak tahu sedang di langkah berapa.
  - Pernah datang ke lokasi namun lapangan dipakai tim lain akibat kelalaian admin manual.

### 3.2 Persona Kasir Frontliner: "Rian Pratama (21 Tahun)"
* **Profil:** Staf meja depan FutsalHub Arena shift sore–malam yang bertugas menyambut tim, membagikan rompi/bola, dan menuntaskan pembayaran.
* **Tujuan (Goals):**
  - Mencari kode booking pelanggan dalam $< 5$ detik dan melihat sisa tagihan 50%.
  - Menerima pelunasan sisa 50% secara tunai atau QRIS kasir dan mengubah status jadi Lunas seketika.
  - Mencatat penyewa walk-in langsung di lokasi tanpa merusak jadwal online.

### 3.3 Persona Pemilik Arena: "Pak Hendra Gunawan (46 Tahun)"
* **Profil:** Pemilik tunggal FutsalHub Arena yang memantau utilisasi kedua lapangannya.
* **Tujuan (Goals):**
  - Tingkat okupansi lapangan tinggi, terutama jam malam (*Peak Hours* 15:00 - 24:00).
  - Kepastian arus kas masuk di depan (DP 50%) dan laporan omzet harian yang transparan.

---

## 4. Prinsip Desain Produk & Filosofi UX

1. **P-01: Frictionless Guest Booking (Pemesanan Cepat Tanpa Akun):**
   Pelanggan tidak perlu mendaftar atau login akun. Identitas disederhanakan menjadi Nama Pemesan dan Nomor WhatsApp.
2. **P-02: 5-Minute Atomic Concurrency Lock (Kunci Slot Atomik 5 Menit):**
   Saat pengguna mengklik slot tersedia, sistem mengunci slot tersebut selama 300 detik. Pengguna lain melihat slot berstatus *Diproses Tamu Lain* dengan hitung mundur server.
3. **P-03: Single-Active Dynamic Step Guidance:**
   Pada indikator alur 5 langkah, hanya 1 langkah yang aktif berwarna oranye pada satu waktu. Warna oranye berpindah otomatis mengikuti posisi interaksi atau scroll pengguna (Langkah 1 $\rightarrow$ 2 $\rightarrow$ 3 $\rightarrow$ 4 $\rightarrow$ 5).
4. **P-04: Clean Modular Dropdown Filtering (Bebas Clutter):**
   Penyaringan data di portal riwayat menggunakan 3 dropdown terpadu (*Status*, *Lapangan*, *Waktu*) dalam 1 baris grid yang rapi, menggantikan tombol chip bertumpuk yang membebani mata.
5. **P-05: Tabular Numerics Single-Line Typography:**
   Label jam (`08:00 - 09:00`) diformat dengan font monospace/tabular-nums horizontal 1 baris anti-wrap, menjaga kerapian grid slot di semua ukuran layar.
6. **P-06: Dual Theme System (Dark / Light Mode):**
   Mendukung pergantian tema seketika via tombol di pojok kanan atas dengan palet warna Warm Editorial (Cream Soft-UI pada light mode dan Obsidian Gold pada dark mode).

---

## 5. Arsitektur Komponen & Peta Layar Aplikasi

Platform FutsalHub Arena terdiri dari komponen-komponen terintegrasi:

| Nama File | Peran Komponen | Deskripsi Singkat |
|---|---|---|
| `index.php` | **Portal Pemesanan Utama** | Halaman depan dengan Hero Editorial, Alur 5 Langkah interaktif, pemilihan 2 jenis lapangan, kalender 7 hari, grid jam slot, modal checkout DP 50%, dan penerbitan tiket. |
| `riwayat.php` | **Portal Cek Booking & Riwayat** | Halaman pelacakan pemesanan pelanggan dengan pencarian live, 3 dropdown filter (*Status, Lapangan, Waktu*), kartu riwayat detail, dan tombol reset filter. |
| `kasir.php` | **Terminal Kasir POS Meja Depan** | Terminal operasional kasir untuk pelunasan sisa 50%, pencatatan reservasi walk-in, pembatalan darurat, dan rekapitulasi omzet harian. |
| `tiket.php` | **E-Tiket Digital Resmi** | Halaman e-tiket dinamis ber-QR code kanvas, memuat rincian booking, status pembayaran DP, sisa tagihan di kasir, dan ramah cetak browser. |
| `api.php` | **REST API Backend** | Endpoint AJAX untuk jadwal (`fetch_schedule`), kunci slot (`acquire_lock`), lepas kunci (`release_lock`), konfirmasi DP (`confirm_payment`), dan availability 7 hari. |
| `BookingEngine.php` | **Core Domain Logic & Service** | Kelas inti yang mengeksekusi query database, transaksi ACID, manajemen lock 5 menit, dan kalkulasi tarif lapangan. |
| `Database.php` | **Database Connection Manager** | Singleton wrapper koneksi PDO MySQL dengan error handling dan konfigurasi UTF-8. |
| `config.php` | **Konfigurasi Global Sistem** | Konstanta koneksi database, nama venue, aturan tarif, durasi lock, dan persentase DP. |
| `css/style.css` | **Design System & Stylesheet** | Berisi seluruh variabel warna, tipografi Fraunces/Jakarta Sans, layout grid responsif, animasi modal, dan styling dual dark/light theme. |

---

## 6. Epik Produk & Spesifikasi Fitur Terperinci

---

### 6.1 Epik 1: Portal Pemesanan Pelanggan & Alur 5 Langkah (`index.php`)

Portal pemesanan menyajikan alur mandiri 5 langkah terpadu yang dipandu oleh navigasi pill interaktif:

```
[ 1. Pilih Lapangan ] ──► [ 2. Pilih Tanggal ] ──► [ 3. Kunci Jam ] ──► [ 4. Bayar DP 50% ] ──► [ 5. Tiket & Riwayat ]
```

#### Fitur 1.1: Navigasi Indikator Langkah Oranye Dinamis
* **Kriteria Penerimaan (Gherkin AC):**
  ```gherkin
  Scenario: Indikator langkah menyorot hanya 1 langkah aktif secara dinamis
    Given Pengguna membuka halaman index.php pada bagian Alur Reservasi
    Then hanya pill "1 Pilih Lapangan" yang memiliki latar belakang oranye (active)
    And pill Langkah 2, 3, 4, 5 berlatar belakang putih normal (tidak aktif)
    When Pengguna mengklik salah satu tombol tanggal pada Langkah 2
    Then warna oranye aktif seketika berpindah HANYA ke "2 Pilih Tanggal"
    When Pengguna mengklik atau menggulir ke area slot jam main pada Langkah 3
    Then warna oranye aktif seketika berpindah HANYA ke "3 Kunci Jam (5 Menit)"
    When Pengguna mengklik tombol pill "1 Pilih Lapangan"
    Then layar bergulir kembali ke Langkah 1 dan oranye aktif berpindah ke Langkah 1
  ```

#### Fitur 1.2: Langkah 1 — Pemilihan Jenis Lapangan
* Menyajikan 2 kartu lapangan interaktif:
  - **Lapangan 1 (Vinyl Indoor):** Siang Rp 120.000 / Malam Rp 160.000.
  - **Lapangan 2 (Rumput Sintetis):** Siang Rp 130.000 / Malam Rp 170.000.
* Mengklik salah satu kartu akan mengaktifkan border emas, memperbarui banner info lapangan aktif, dan memuat ketersediaan slot secara asinkron.

#### Fitur 1.3: Langkah 2 — Pemilihan Tanggal Main 7 Hari ke Depan
* Grid 7 tombol tanggal horizontal: masing-masing memuat nama hari (*Hari Ini, Besok, Jumat, dst.*), tanggal (*25 Nov*), serta badge slot tersedia (*misal: 14 Slot*).
* Mengklik tombol tanggal memicu reload slot jam instan tanpa me-refresh seluruh halaman.

#### Fitur 1.4: Langkah 3 — Kunci Jam Main (08:00 - 24:00) & Anti Bentrok 5 Menit
* Terbagi dalam 2 sesi:
  - **Sesi Siang (08:00 - 15:00):** Tarif reguler siang (7 slot).
  - **Sesi Malam (15:00 - 24:00):** Tarif prime-time malam (9 slot).
* **Format Jam:** Ditampilkan horizontal 1 baris (`🕒 08:00 - 09:00`) dengan font monospace/tabular-nums anti-wrap.
* **Penguncian Atomik:**
  ```gherkin
  Scenario: Pengguna mengunci slot jam tersedia
    Given Pengguna memilih tanggal dan jam 19:00 - 20:00 yang berstatus "Tersedia"
    When Pengguna mengklik kartu slot jam tersebut
    Then sistem mengunci slot di database dengan TTL 300 detik (5 menit)
    And membuka Modal Formulir Identitas Pemesan
    And indikator langkah berpindah ke "4 Pembayaran DP 50%"
    And pengunjung lain seketika melihat slot berstatus "Diproses Tamu Lain"
  ```

#### Fitur 1.5: Langkah 4 — Pembayaran DP 50% via QRIS Instan
* Pengguna mengisi Nama Pemesan dan Nomor WhatsApp aktif.
* Tagihan dihitung otomatis tepat 50% dari total tarif (misal: Total Rp 160.000 $\rightarrow$ DP Rp 80.000, Sisa Kasir Rp 80.000).
* Menampilkan visual barcode QRIS dinamis yang siap dipindai.
* Tombol aksi tunggal: `✓ Konfirmasi Pembayaran DP 50%` (tanpa tombol kembali/duplikasi yang membingungkan).

#### Fitur 1.6: Langkah 5 — Penerbitan Tiket & Integrasi Riwayat
* Saat pembayaran DP terkonfirmasi, modal tiket sukses terbuka menampilkan:
  - **Kode Booking Resmi:** Format `FUT-YYYYMMDD-HH-XXXX`.
  - **Rincian:** Nama Pemesan, Lapangan, Tanggal & Jam, serta Sisa Pelunasan di Meja Kasir.
  - **Aksi Cepat:** Tombol *Buka & Cetak E-Tiket Digital*, *Cek Status di Riwayat Pemesanan*, dan *Konfirmasi WhatsApp*.

---

### 6.2 Epik 2: Portal Pelacakan & Cek Riwayat Booking (`riwayat.php`)

Portal mandiri bagi pelanggan untuk memantau status pesanan, mengecek pembayaran DP, dan mencetak ulang e-tiket.

#### Fitur 2.1: Pencarian Cepat Multi-Parameter (Live Search)
* Kolom input pencarian mendukung pencarian berdasarkan:
  - Kode Booking (`FUT-...`)
  - Nama Pemesan / Tim
  - Nomor WhatsApp
* Penyaringan berjalan secara *real-time* saat pengguna mengetik (*live on-input filtering*).

#### Fitur 2.2: Sistem Filter 3 Dropdown Elegan (ProMax Soft-UI)
Menggantikan sistem tombol chip bertumpuk dengan 3 dropdown sejajar dalam satu grid:
1. **Dropdown Status Reservasi:**
   - `Semua Status (Total Data)`
   - `⏳ DP 50% Aktif (Jumlah Booking DP)`
   - `✔ Lunas (Jumlah Booking Lunas)`
2. **Dropdown Jenis Lapangan:**
   - `Semua Lapangan (Total Data)`
   - `⚽ Lapangan 1 - Vinyl (Jumlah)`
   - `🌱 Lapangan 2 - Rumput Sintetis (Jumlah)`
3. **Dropdown Waktu / Jadwal:**
   - `Semua Jadwal Tanggal`
   - `📅 Hari Ini (Jumlah Booking Hari Ini)`
   - `🚀 Jadwal Mendatang (Jumlah Booking Masa Depan)`

* **Kriteria Penerimaan (Gherkin AC):**
  ```gherkin
  Scenario: Pelanggan memfilter riwayat menggunakan dropdown status
    Given Pelanggan membuka halaman riwayat.php dengan total 11 reservasi
    When Pelanggan memilih option "⏳ DP 50% Aktif" pada dropdown Status Reservasi
    Then daftar kartu reservasi langsung tersaring menampilkan hanya 8 booking DP
    And teks ringkasan menampilkan: "Menampilkan 8 dari 11 reservasi"
    And tombol "✕ Reset Filter" muncul secara otomatis
    When Pelanggan menekan tombol "✕ Reset Filter"
    Then seluruh dropdown kembali ke opsi "Semua" dan kartu kembali tampil 11
  ```

#### Fitur 2.3: Desain Panah Tunggal Dropdown
* Setiap elemen `<select>` hanya menampilkan **1 ikon panah tunggal** bawaan peramban yang tajam dan presisi, tanpa duplikasi ikon panah sekunder.

---

### 6.3 Epik 3: Dasbor Kasir & POS Meja Depan (`kasir.php`)

Dasbor operasional staf meja depan untuk mengelola kedatangan tim dan pelunasan transaksi:
* **Pencarian Cepat Kode Booking:** Kotak pencarian respon instan ($\le 5$ detik) untuk mengecek data tim yang tiba.
* **Pelunasan Sisa 50% 1-Klik:** Tombol pelunasan tunai/QRIS kasir yang seketika mengubah status booking dari `DP_PAID` menjadi `SETTLED` (Lunas 100%) dan mencatatnya ke buku besar transaksi.
* **Pendaftaran Booking Walk-In Langsung:** Formulir pembuatan reservasi langsung di tempat bagi tim yang datang langsung ke meja kasir, langsung berstatus Lunas dan seketika mengunci jadwal di portal online.
* **Rekapitulasi Omzet Harian:** Widget ringkasan total uang masuk DP online, pelunasan kasir tunai, dan estimasi penerimaan hari ini.

---

### 6.4 Epik 4: E-Tiket Digital & Validasi QR (`tiket.php`)

Halaman tiket resmi pasca-pemesanan:
* **QR Code Kanvas Dinamis:** Menghasilkan kode QR visual yang memuat URL validasi pemesanan resmi.
* **Rincian Transparansi Finansial:** Memuat label harga sewa, jumlah uang muka DP 50% yang telah lunas, dan sisa tagihan yang wajib dibayar di meja kasir.
* **Ramah Cetak Browser (*Driverless Print*):** Memiliki print stylesheet khusus yang menyembunyikan header/footer web saat pengguna menekan tombol *Cetak E-Tiket*, menghasilkan lembar tiket PDF bersih berstandar A4/struk kasir.

---

### 6.5 Epik 5: Backend REST API & Concurrency Lock Engine (`api.php` & `BookingEngine.php`)

Infrastruktur backend yang menjamin keandalan data dan integritas transaksi:
* **`action=fetch_schedule`:** Mengembalikan status terkini 16 slot harian (`AVAILABLE`, `LOCKED`, `BOOKED`), data pemesan publik, dan sisa detik hitung mundur kunci.
* **`action=acquire_lock`:** Mengeksekusi penguncian slot atomik 5 menit menggunakan kueri bersyarat database; menolak permintaan jika slot telah dikunci/dibooking sesi lain.
* **`action=release_lock`:** Melepaskan kunci secara sukarela jika pengguna membatalkan checkout sebelum waktu 5 menit habis.
* **`action=confirm_payment`:** Memvalidasi pembayaran DP 50%, menghapus lock sementara, dan menerbitkan record booking resmi berstatus `DP_PAID`.
* **Pembersihan Otomatis TTL:** Kunci slot yang telah kedaluwarsa ($> 300$ detik) dibersihkan secara otomatis saat ada kueri jadwal baru, sehingga slot yang ditinggalkan pengguna langsung kembali berstatus Tersedia bagi publik.

---

## 7. Matriks Status Tampilan Layar (UI States Matrix)

| Halaman Antarmuka | Ideal State (Normal) | Empty State (Kosong) | Loading State (Memuat) | Error State (Gagal) |
|---|---|---|---|---|
| **Pemesanan (`index.php`)** | 16 slot tampil dengan indikator status Hijau (Tersedia), Kuning (Dikunci 5m), Merah (Terisi). | Seluruh 16 slot berstatus hijau (Tersedia). | Shimmer placeholder halus saat berganti tanggal/lapangan. | Alert: Slot gagal dikunci karena telah diambil orang lain. |
| **Riwayat (`riwayat.php`)** | Kartu booking tampil lengkap sesuai filter dropdown yang dipilih. | Placeholder bersih: *"Tidak Ada Jadwal yang Cocok"* dengan tombol reset filter. | Animasi pemuatan kartu booking. | Alert: Gagal memuat riwayat booking dari server. |
| **Kasir POS (`kasir.php`)** | Stats cards omzet terisi, tabel jadwal hari ini aktif dengan tombol pelunasan. | Tabel booking kosong (belum ada tim terdaftar pada tanggal terkait). | Spinner saat validasi pencarian kode booking. | Modal peringatan: Kode booking tidak ditemukan. |
| **E-Tiket (`tiket.php`)** | Tiket tampil dengan QR Code, status DP, rincian jadwal, dan sisa kasir. | Kotak tiket tidak ditemukan dengan tombol kembali ke jadwal. | Animasi rendering QR Code kanvas. | Alert: Data tiket tidak valid atau telah dibatalkan. |

---

## 8. Telemetri Produk, Funnel Konversi & North Star Metric

### 8.1 North Star Metric (NSM)
$$\mathbf{\text{North Star Metric}} = \frac{\text{Total Jam Lapangan Terpakai Efektif (DP Paid / Settled)}}{\text{Total Kapasitas Jam Operasional Tersedia (2 Lapangan} \times 16\text{ Jam = 32 Slot/Hari)}}$$

*Target Utilisasi:* Mencapai tingkat okupansi harian $\ge 70\%$ pada hari kerja (Senin–Jumat) dan $\ge 85\%$ pada akhir pekan (Sabtu–Minggu).

### 8.2 Funnel Konversi 5 Langkah Reservasi Mandiri
1. **Langkah 1 (Akses Web & Pilih Lapangan):** 100% Pengunjung.
2. **Langkah 2 (Pilih Tanggal Main 7 Hari):** Target $\ge 80\%$ pengunjung.
3. **Langkah 3 (Kunci Slot Jam Main 5 Menit):** Target $\ge 65\%$ pengunjung.
4. **Langkah 4 (Bayar DP 50% via QRIS):** Target Konversi Akhir $\ge 50\%$.
5. **Langkah 5 (Kehadiran & Pelunasan Kasir):** Target $\ge 99\%$ (Tingkat No-Show $< 1\%$).

---

## 9. Kepatuhan Regulasi & Alur Pengalaman Keamanan (Security UX)

1. **Integritas Konkurensi Database (ACID):** Penguncian slot dan konfirmasi pembayaran dibungkus dalam transaksi atomik MySQL InnoDB.
2. **Pencegahan Kunci Menggantung (*Stale Lock Prevention*):** Kunci sementara memiliki batas masa berlaku (*Time-to-Live*) tepat 300 detik yang divalidasi ganda di level database dan klien.
3. **Keamanan Finansial:** Pembagian tagihan DP 50% dan sisa 50% dihitung otomatis di sisi server (*Server-side Calculation*) untuk mencegah manipulasi nilai uang dari sisi browser.
4. **Audit Trail Finansial:** Setiap transaksi pelunasan kasir mencatat stempel waktu, metode bayar (Tunai/QRIS), dan ID staf kasir yang bertugas.

---

## 10. Strategi Peluncuran & Kriteria Kesiapan Rilis (Go/No-Go Checklist)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    GO / NO-GO LAUNCH READINESS CHECKLIST                    │
├──────────────────────────────────────────────────────────────┬──────────────┤
│ Kriteria Kelaikan Sistem FutsalHub Arena                     │ Status Wajib │
├──────────────────────────────────────────────────────────────┼──────────────┤
│ 1. Skema Database InnoDB termigrasi sempurna tanpa error     │ [ ✔ ] GO     │
│ 2. Uji konkurensi penguncian slot 5 menit (Anti Double-Book) │ [ ✔ ] GO     │
│ 3. Kalkulasi tarif Reguler Siang vs Peak Malam 100% akurat   │ [ ✔ ] GO     │
│ 4. Kunci slot 5 menit otomatis lepas saat waktu habis        │ [ ✔ ] GO     │
│ 5. Indikator langkah oranye dinamis (hanya 1 aktif saat ini) │ [ ✔ ] GO     │
│ 6. Simulasi bayar DP 50% QRIS sukses terbitkan tiket resmi   │ [ ✔ ] GO     │
│ 7. Portal riwayat.php miliki 3 dropdown filter rapi & reset  │ [ ✔ ] GO     │
│ 8. Dropdown filter riwayat bebas double arrow (panah tunggal)│ [ ✔ ] GO     │
│ 9. Terminal kasir.php responsif lunasi sisa bayar (< 5 dtk)  │ [ ✔ ] GO     │
│ 10. E-Tiket tiket.php hasilkan QR Code dinamis & ramah cetak │ [ ✔ ] GO     │
│ 11. Dual Theme System (Dark / Light) berjalan mulus di semua  │ [ ✔ ] GO     │
└──────────────────────────────────────────────────────────────┴──────────────┘
```

---

*Dokumen Product Requirements Document (PRD) v2.0 ini berlaku resmi sebagai spesifikasi definitif sistem FutsalHub Arena dan menjadi acuan utama implementasi teknis serta evaluasi pengujian.*
