# 02_PROJECT_CHECKLIST.md - Progress Tracking

Status Legend:
- [x] = Selesai
- [-] = Sedang Dikerjakan
- [ ] = Belum Dikerjakan

## Phase 1: Foundation & Master Data
- [x] **Instalasi:** Laravel, Filament v4, Spatie, Shield.
- [x] **Dependencies:** Install Excel, DomPDF, dan Backup manager.
- [x] **User Management:** Setup Role (Owner, Admin Gudang, Produksi, Sales).
- [x] **Master Warehouses:** CRUD Gudang (Pabrik, Pagu, Tanjung, Candi).
- [x] **Master Units:** KG, Ekor, Pack, Karung.
- [x] **Master Activity Logs:** Track perubahan data penting.
    - [x] Field: User, Tipe Aktivitas, Deskripsi, Tanggal & Waktu.
    - [x] Buat fitur membatalkan aktivitas yang dipilih.
    - [x] Pastikan semua modul utama mencatat aktivitas CRUD, Termasuk Import.
    - [x] Cek semua resource yang telah selesai sudah terhubung dengan activity log.
    - [x] Buat filament page untuk melihat activity log dengan filter by user, tanggal, dan tipe aktivitas.
        - [x] Tambahkan fitur export log ke format CSV dan PDF.
        - [x] Di dalam edit page activity log, tambahkan tombol "Revert" untuk mengembalikan data ke kondisi sebelum perubahan.
        - Catatan berikutnya:
            - [x] Lakukan smoke test pada resource dan fitur export untuk memastikan sinkron dengan izin Shield.
            - [x] Integrasikan log ini ke modul produk setelah selesai.
- [x] **Master Product Categories:** CRUD kategori produk.
    - [x] Field: Kode Kategori, Nama Kategori.
    - [x] Seeder default kategori: Hasil Panen, Live Bird, Produk, Umum.
- [x] **Master Products:**
    - [x] Field: Kode (Terdapat Tombol Generate Kode Otomatis),Nama, Tipe, Satuan (KG, Ekor, Pack, Karung).
    - [x] Jenis Produk: Persediaan, Jasa, Non-Persediaan.
    - [x] Import data products.csv untuk modul Produk.
        - [x] Sesuaikan kode produk menjadi unik dengan format P-XXXX.
        - [x] Sesuaikan kategori produk yang ada di products.csv dengan data dari master product categories.
        - [x] Sesuaikan satuan produk yang ada di products.csv dengan data dari master units.
        - Catatan berikutnya:
            - [x] Bangun workflow import dari products.csv dengan mapping kategori & satuan serta validasi format P-XXXX.
            - [x] Tuntaskan smoke test activity log sebelum menutup checklist ini.
- [x] **Master COA (Chart of Accounts):** Struktur Parent-Child & Saldo Awal.
    - [x] Buat fitur Import coa.csv
    - [x] Tipe Akun: Akumulasi Penyusutan, Aset Lainnya, Aset Lancar Lainnya, Aset Tetap, Beban, Beban Lainnya, Beban Pokok Penjualan, Kas & Bank, Liabilitas Jangka Panjang, Liabilitas Jangka Pendek, Modal, Pendapatan, Pendapatan Lainnya, Persediaan, Piutang Usaha, Utang Usaha. 
    - [x] Tipe Akun:
        - Akumulasi Penyusutan
        - Aset Lainnya
        - Aset Lancar Lainnya
        - Aset Tetap
        - Beban
        - Beban Lainnya
        - Beban Pokok Penjualan
        - Kas & Bank
        - Liabilitas Jangka Panjang
        - Liabilitas Jangka Pendek
        - Modal
        - Pendapatan
        - Pendapatan Lainnya
        - Persediaan
        - Piutang Usaha
        - Utang Usaha
- [x] **Master Supplier Categories:** CRUD kategori supplier.
    - [x] Field: Kode Kategori, Nama Kategori.
    - [x] Seeder default kategori: Umum, Bahan Baku, Perlengkapan, Jasa.
- [x] **Master Suppliers:** CRUD supplier lengkap dengan info kontak dan bank.
    - [x] Field: Kode Pemasok, NPWP, Tipe, Nama Supplier, Nama Pemilik, Nomor Kontak (Bisa lebih dari 1), Atas Nama, Nama Bank, Nomor Rekening, Alamat.
    - [x] Import data supplier.csv untuk modul Supplier.
        - [x] Sesuaikan kode supplier menjadi unik dengan format S-XXXX.
        - [x] Sesuaikan tipe supplier yang ada di supplier.csv dengan data dari master supplier categories.
        - Catatan berikutnya:
            - [x] Jalankan smoke test activity log & Shield untuk alur import supplier.
- [x] **Master Customers Categories:** CRUD kategori customer.
    - [x] Field: Kode Kategori, Nama Kategori.
    - [x] Seeder default kategori: Customer Lama, Customer Baru, Retail, MBG, Partai.
- [x] **Master Customers:** CRUD customer lengkap dengan info kontak dan bank.
    - [x] Field: Kode Customer, Nama Customer, Nomor Telepon (Bisa lebih dari 1), Alamat.
    - [x] Import data customer.csv untuk modul Customer.
        - [x] Sesuaikan kode customer menjadi unik dengan format C-XXXX.
        - [x] Sesuaikan tipe customer yang ada di customer.csv dengan data dari master customer categories.
        - Catatan berikutnya:
            - [x] Rancang workflow import: baca CSV, normalisasi kode C-XXXX, mapping kategori & tipe, serta dukung banyak nomor telepon seperti modul Supplier.
            - [x] Tambahkan sample customer melalui seeder untuk validasi activity log & Shield.

## Phase 2: Purchasing & Inbound (Hulu)
- [ ] **Pembelian Ayam Hidup:**
    - [ ] **Header Form**
        - [ ] Supplier (wajib) menjadi gate bagi seluruh komponen barang & biaya.
        - [ ] Alamat kirim otomatis terisi dari supplier dan dapat disimpan ulang saat diedit.
        - [ ] Tanggal PO (wajib) dan Tanggal Kirim (opsional) dengan validasi rentang.
        - [ ] Gudang tujuan (wajib) untuk memastikan stok masuk gudang yang benar.
        - [ ] No. PO auto-generate namun masih bisa diedit bila perlu.
    - [ ] **Validasi Konteks**
        - [ ] Tidak boleh mencari atau menambahkan barang sebelum memilih supplier.
        - [ ] Semua field wajib di header harus terisi sebelum tab detail aktif.
    - [ ] **Rincian Barang**
        - [ ] Komponen cari barang memunculkan modal detail ketika dipilih.
        - [ ] Kolom editable: Nama item, Qty + pilihan satuan (Ekor/Kg), Harga Satuan, Diskon (persen atau nominal, max 100% & <= harga), Checkbox Pajak 11%, Catatan opsional.
        - [ ] Subtotal otomatis, tersedia aksi duplikat/baris baru/hapus.
    - [ ] **Info Lainnya**
        - [ ] Syarat pembayaran dengan preset Manual, Net 7/15/30/45/60, COD default, lengkap dengan deskripsi.
        - [ ] Checkbox pajak (Kena vs Termasuk) mengatur cara hitung.
        - [ ] Keterangan bebas serta bidang FOB (alamat tujuan + titik pengiriman) opsional.
    - [ ] **Biaya Lainnya (Opsional)**
        - [ ] Pencarian COA mendukung kode/nama, memunculkan modal biaya.
        - [ ] Modal berisi nama biaya editable, nominal, catatan, dan checkbox alokasi (membagi biaya rata terhadap qty).
    - [ ] **Ringkasan & Footer**
        - [ ] Ringkasan harga: Subtotal, Diskon global (persen/nominal), Pajak (modal untuk memilih DPP 100%, 11/12, 11/12 10%, 40%, 30%, 20%, 10% dan tarif 0%, 10%, 11%, 12%), Total akhir.
        - [ ] Panel kanan: Simpan / Simpan Draft, modal dokumen agnostik (multi-file jpg/png/pdf/docx/xlsx, 5MB per file) dengan daftar file + aksi download/hapus.
        - [ ] Favorit PO: simpan konfigurasi (header, barang, biaya, info lainnya) dan muat ulang favorit ke form aktif.
    - [ ] **Validasi Sebelum Simpan**
        - [ ] Minimal satu barang tersimpan.
        - [ ] Qty dan harga satuan harus > 0.
        - [ ] Diskon tidak boleh negatif atau melampaui harga akhir.
    - [ ] **Perhitungan Tambahan**
        - [ ] Hitung susut jalan otomatis dari selisih berat kirim vs terima.
    - [ ] **Output**
        - [ ] Export PO ke PDF dengan layout siap cetak dan daftar lampiran.
- [ ] **Penerimaan Barang:** Form penerimaan dengan validasi PO.
- [ ] **Stock In:** Form pencatatan masuk barang ke gudang.

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