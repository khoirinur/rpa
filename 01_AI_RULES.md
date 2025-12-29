# 01_AI_RULES.md - Rules of Engagement & Business Logic

## ⚠️ CRITICAL INSTRUCTIONS (BACA SEBELUM MENULIS KODE)

### 1. Technology Stack Enforcement
- **Strictly Filament v4:** Gunakan fitur terbaru dari Filament v4. Jangan membuat Controller/View manual (Blade) kecuali dipinta secara eksplisit untuk Custom Widget.
- **Livewire:** Gunakan Livewire state management standar Filament.
- **No API/SPA:** Jangan membuat API endpoint atau React/Vue frontend terpisah. Logic ada di Monolith Laravel.

### 2. Business Logic (RPA Domain)
Sistem ini adalah **Disassembly Manufacturing** (1 Input -> Banyak Output), BUKAN Assembly.
- **Produksi:** Flow-nya adalah `Ayam Hidup` (Input) -> diproses menjadi -> `Karkas`, `Ceker`, `Kepala`, `Jeroan` (Output).
- **Satuan:** Pembelian menggunakan DUA satuan: **Berat (Kg)** dan **Ekor**. Keduanya harus dicatat di database.
- **Gudang:** Multi-warehouse (Pabrik, Pagu, Tanjung, Candi). Stok harus dipisah per `warehouse_id`.

### 2.1 Pola Form & Input yang Harus Diulang
Digunakan sebagai standar untuk modul **Pembelian Ayam Hidup** dan modul lanjutan seperti **Work Order (Penyembelihan)**, **Inventory & Distribution**, dan turunan lain.
- **Gate Entity Terlebih Dahulu:** Semua komponen detail (barang/hasil produksi) wajib dikunci sampai entitas utama dipilih (contoh: supplier, batch, atau gudang sumber).
- **Pencarian Barang dengan Modal:** Input barang memakai komponen pencarian (searchable select) yang, setelah memilih item, selalu membuka modal detail untuk mengisi Qty, Satuan (Kg/Ekor), harga, diskon, pajak, dan catatan. Jangan mengijinkan edit inline tanpa modal.
- **Tab Fitur Tambahan:** Minimal ada tab terpisah untuk informasi tambahan (contoh: `Detail PO`, `Pembayaran & Pajak`, atau tab biaya lainnya). Modul baru harus mengikuti pola tab ini untuk memisahkan input dasar vs opsi lanjutan.
- **Ringkasan dengan Hidden + Display:** Semua angka ringkasan (qty, berat, subtotal, diskon, pajak, total akhir) disimpan dalam field tersembunyi untuk nilai mentah dan ditampilkan di field read-only terformat. Ini wajib agar konsisten untuk tiap modul.
- **Masking & Sanitasi:** Input uang memakai masker `RawJs $money`, diikuti sanitizer server-side sebelum perhitungan; pastikan logika ini direuse.
- **Audit & Favorit:** Modal barang, biaya tambahan, dan ringkasan harus tetap men-trigger `LogsActivity` serta mendukung penyimpanan konfigurasi favorit bila konteksnya mirip PO.

### 3. Coding Standards & Safety
- **Race Condition:** Gunakan `DB::transaction(function() { ... })` dan `lockForUpdate()` pada logic pengurangan stok. JANGAN gunakan Queue untuk transaksi user (harus synchronous).
- **HPP (COGS):** Jangan hardcode logic HPP. Siapkan struktur agar biaya ayam hidup bisa dialokasikan persentase-nya ke produk turunan.
- **Language:** UI Label dan Pesan Error harus dalam **Bahasa Indonesia**.

**[IMPORTANT] RBAC & Visibility Implementation:**
Setiap komponen sensitif (Kolom Harga Beli, Tombol Delete, Menu Laporan Laba Rugi) WAJIB menggunakan syntax visibility Laravel Shield/Spatie secara eksplisit.
Contoh: `->visible(fn () => auth()->user()->can('view_buying_price'))`

**[IMPORTANT] Pastikan semua resource menggunakan trait `LogsActivity` untuk audit trail.**

### 4. Known Mistakes / Do Not Repeat (Catatan Evaluasi)
- **Jangan gunakan Float:** Gunakan `decimal`.
- **Jangan Hapus Data:** Gunakan `SoftDeletes` saja.
- **Jangan Overengineer:** Fokus MVP.

### 5. Frontend & Reporting Strategies
- **WhatsApp Sharing:** Gunakan pendekatan **Client-side rendering** dengan `html2canvas` untuk widget dashboard harian.
- **Official Documents (Invoice/Surat Jalan):** Gunakan `barryvdh/laravel-dompdf` dengan Blade View terpisah.
- **Bulk Data (Accounting):** Gunakan `pxlrbt/filament-excel` pada Table Action.

### 6. Export Implementation Rules
- Jangan buat Controller export manual. Gunakan `ExportAction` dari plugin `filament-excel` di header tabel Filament.
- Format Excel harus rapi: Gunakan Heading yang jelas dan format number yang sesuai (Accounting format).