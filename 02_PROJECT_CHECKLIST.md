# 02_PROJECT_CHECKLIST.md - Progress Tracking

Status Legend:
[x] = Selesai
[-] = Sedang Dikerjakan
[ ] = Belum Dikerjakan

## Phase 1: Foundation & Master Data
- [ ] **Instalasi:** Laravel, Filament v4, Spatie, Shield.
- [ ] **Dependencies:** Install Excel, DomPDF, dan Backup manager.
- [ ] **User Management:** Setup Role (Owner, Admin Gudang, Produksi, Sales).
- [ ] **Master Warehouses:** CRUD Gudang (Pabrik, Pagu, Tanjung, Candi).
- [ ] **Master Products:**
    - [ ] Field: Nama, Tipe, Satuan (Kg & Ekor).
    - [ ] Kategori: Fresh vs Frozen.
- [ ] **Master COA (Chart of Accounts):** Struktur Parent-Child & Saldo Awal.

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