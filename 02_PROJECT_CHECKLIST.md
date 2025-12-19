# 02_PROJECT_CHECKLIST.md - Progress Tracking

Status Legend:
[x] = Selesai
[-] = Sedang Dikerjakan
[ ] = Belum Dikerjakan

## Phase 1: Foundation & Master Data
- [x] **Instalasi:** Laravel, Filament v4, Spatie, Shield.
- [x] **Dependencies:** Install Excel, DomPDF, dan Backup manager.
- [x] **User Management:** Setup Role (Owner, Admin Gudang, Produksi, Sales).
- [x] **Master Warehouses:** CRUD Gudang (Pabrik, Pagu, Tanjung, Candi).
- [x] **Master Units:** KG, Ekor, Pack, Karung.
- [ ] **Master Activity Logs:** Track perubahan data penting.
    - [x] Field: User, Tipe Aktivitas, Deskripsi, Tanggal & Waktu.
    - [x] Buat fitur membatalkan aktivitas yang dipilih.
    - [x] Pastikan semua modul utama mencatat aktivitas CRUD, Termasuk Import.
    - [ ] Cek semua resource yang telah selesai sudah terhubung dengan activity log.
    - [x] Buat filament page untuk melihat activity log dengan filter by user, tanggal, dan tipe aktivitas.
        - [x] Tambahkan fitur export log ke format CSV dan PDF.
        - [x] Di dalam edit page activity log, tambahkan tombol "Revert" untuk mengembalikan data ke kondisi sebelum perubahan.
        - Catatan berikutnya:
            - [ ] Lakukan smoke test pada resource dan fitur export untuk memastikan sinkron dengan izin Shield.
            - [x] Integrasikan log ini ke modul produk setelah selesai.
- [x] **Master Product Categories:** CRUD kategori produk.
    - [x] Field: Kode Kategori, Nama Kategori.
    - [x] Seeder default kategori: Hasil Panen, Live Bird, Produk, Umum.
- [-] **Master Products:**
    - [x] Field: Kode (Terdapat Tombol Generate Kode Otomatis),Nama, Tipe, Satuan (KG, Ekor, Pack, Karung).
    - [x] Jenis Produk: Persediaan, Jasa, Non-Persediaan.
    - [x] Import data products.csv untuk modul Produk.
        - [x] Sesuaikan kode produk menjadi unik dengan format P-XXXX.
        - [x] Sesuaikan kategori produk yang ada di products.csv dengan data dari master product categories.
        - [x] Sesuaikan satuan produk yang ada di products.csv dengan data dari master units.
        - Catatan berikutnya:
            - [x] Bangun workflow import dari products.csv dengan mapping kategori & satuan serta validasi format P-XXXX.
            - [ ] Tuntaskan smoke test activity log sebelum menutup checklist ini.
- [x] **Master COA (Chart of Accounts):** Struktur Parent-Child & Saldo Awal.
    - [x] Buat fitur Import coa.csv
- [x] **Master Supplier Categories:** CRUD kategori supplier.
    - [x] Field: Kode Kategori, Nama Kategori.
    - [x] Seeder default kategori: Umum, Bahan Baku, Perlengkapan, Jasa.
- [-] **Master Suppliers:** CRUD supplier lengkap dengan info kontak dan bank.
    - [x] Field: Kode Pemasok, NPWP, Tipe, Nama Supplier, Nama Pemilik, Nomor Kontak (Bisa lebih dari 1), Atas Nama, Nama Bank, Nomor Rekening, Alamat.
    - [ ] Import data supplier.csv untuk modul Supplier.
        - [ ] Sesuaikan kode supplier menjadi unik dengan format S-XXXX.
        - [ ] Sesuaikan tipe supplier yang ada di supplier.csv dengan data dari master supplier categories.
- [x] **Master Customers Categories:** CRUD kategori customer.
    - [x] Field: Kode Kategori, Nama Kategori.
    - [x] Seeder default kategori: Customer Lama, Customer Baru, Retail, MBG, Partai.
- [-] **Master Customers:** CRUD customer lengkap dengan info kontak dan bank.
    - [x] Field: Kode Customer, Nama Customer, Nomor Telepon (Bisa lebih dari 1), Alamat.
    - [x] Import data customer.csv untuk modul Customer.
        - [x] Sesuaikan kode customer menjadi unik dengan format C-XXXX.
        - [x] Sesuaikan tipe customer yang ada di customer.csv dengan data dari master customer categories.
        - Catatan berikutnya:
            - [x] Rancang workflow import: baca CSV, normalisasi kode C-XXXX, mapping kategori & tipe, serta dukung banyak nomor telepon seperti modul Supplier.
            - [x] Tambahkan sample customer melalui seeder untuk validasi activity log & Shield.

## Phase 2: Purchasing & Inbound (Hulu)
- [ ] **Pembelian Ayam Hidup:**
    - [ ] Form Input: Berat & Ekor.
    - [ ] Hitung Susut Jalan otomatis.
    - [ ] **Export:** Fitur download PO (PDF).

## Phase 3: Production (Inti)
- [ ] **Work Order (Penyembelihan):**
    - [ ] Input: Batch Ayam Hidup (Kg & Ekor).
    - [ ] Output Repeater: List hasil potong.
    - [ ] Validasi Yield & Susut.

## Phase 4: Inventory & Distribution
- [ ] **Transfer Stok:** Form pemindahan barang antar gudang.
- [ ] **Surat Jalan:** Generate PDF Surat Jalan untuk sopir.
- [ ] **Stok Opname & Kartu Stok:** Fitur Export Excel untuk audit stok.

## Phase 5: Sales & Outbound (Hilir)
- [ ] **Penjualan:** Form Order & Cek Stok Realtime.
- [ ] **Invoice:** Generate PDF Tagihan.
- [ ] **Penjualan Limbah:** Form simple Cash.

## Phase 6: Reporting & Dashboard
- [ ] **Laporan Harian Produksi:** Widget Tabel + Tombol "Copy Image".
- [ ] **Laporan Keuangan:** Export Excel untuk Laba Rugi & Neraca.
- [ ] **Backup System:** Setup jadwal backup otomatis (Database & Files).