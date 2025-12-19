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
- [ ] **Master Product Categories:** CRUD kategori produk.
    - [ ] Field: Kode Kategori, Nama Kategori.
    - [ ] Seeder default kategori: Hasil Panen, Live Bird, Produk, Umum.
- [ ] **Master Products:**
    - [ ] Field: Kode (Terdapat Tombol Generate Kode Otomatis),Nama, Tipe, Satuan (KG, Ekor, Pack, Karung).
    - [ ] Jenis Produk: Persediaan, Jasa, Non-Persediaan.
    - [ ] Import data products.csv untuk modul Produk.
        - [ ] Sesuaikan kode produk menjadi unik dengan format P-XXXX.
        - [ ] Sesuaikan kategori produk yang ada di products.csv dengan data dari master product categories.
        - [ ] Sesuaikan satuan produk yang ada di products.csv dengan data dari master units.
- [x] **Master COA (Chart of Accounts):** Struktur Parent-Child & Saldo Awal.
    - [ ] Buat fitur Import coa.csv
- [ ] **Master Supplier Categories:** CRUD kategori supplier.
    - [ ] Field: Kode Kategori, Nama Kategori.
    - [ ] Seeder default kategori: Umum, Bahan Baku, Perlengkapan, Jasa.
- [ ] **Master Suppliers:** CRUD supplier lengkap dengan info kontak dan bank.
    - [ ] Field: Kode Pemasok, NPWP, Tipe, Nama Supplier, Nama Pemilik, Nomor Kontak, Atas Nama, Nama Bank, Nomor Rekening, Alamat.
    - [ ] Import data supplier.csv untuk modul Supplier.
        - [ ] Sesuaikan kode supplier menjadi unik dengan format S-XXXX.
        - [ ] Sesuaikan tipe supplier yang ada di supplier.csv dengan data dari master supplier categories.
- [ ] **Master Customers Categories:** CRUD kategori customer.
    - [ ] Field: Kode Kategori, Nama Kategori.
    - [ ] Seeder default kategori: Customer Lama, Customer Baru, Retail, MBG, Partai.
- [ ] **Master Customers:** CRUD customer lengkap dengan info kontak dan bank.
    - [ ] Field: Kode Customer, Nama Customer, Nomor Telepon, Alamat, Tipe.
    - [ ] Import data customer.csv untuk modul Customer.
        - [ ] Sesuaikan kode customer menjadi unik dengan format C-XXXX.
        - [ ] Sesuaikan tipe customer yang ada di customer.csv dengan data dari master customer categories.

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