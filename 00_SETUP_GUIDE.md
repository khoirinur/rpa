# 00_SETUP_GUIDE.md - Initial Setup Instructions

## Context
Project ini adalah ERP Manufaktur untuk RPA (Rumah Potong Ayam) "Surya Kencana".
Stack yang WAJIB digunakan:
- **Framework:** Laravel 11.x
- **Admin Panel:** FilamentPHP v4.x
- **Database:** MySQL
- **CSS:** TailwindCSS (via Filament)

## Step-by-Step Installation

### 1. Laravel & Database
- Install Laravel baru.
- Konfigurasi `.env` untuk koneksi database MySQL.
- Set `DB_COLLATION=utf8mb4_unicode_ci`.

### 2. Filament Admin (Version 4.x)
- Install Panel: `composer require filament/filament:"^4.0"`
- Install Panel Assets: `php artisan filament:install --panels`
- Buat User Admin Pertama.

### 3. Core Dependencies (Wajib Install)
Install package berikut sebelum memulai kodingan fitur:

**A. RBAC (Role Based Access Control)**
- `composer require spatie/laravel-permission`
- `composer require bezhanb/filament-shield`
- Jalankan: `php artisan shield:install` (Pilih 'yes' untuk semua opsi).

**B. Audit Logging (Jejak aktivitas user)**
- `composer require awcodes/filament-activity-log`

**C. Enterprise Export & Reports**
- **Excel:** `composer require pxlrbt/filament-excel` (Integrasi native Filament paling stabil).
- **PDF:** `composer require barryvdh/laravel-dompdf` (Standar industri untuk generate Invoice/Surat Jalan).

**D. System Maintenance (Backup)**
- **Backup:** `composer require spatie/laravel-backup` (Wajib untuk antisipasi server crash).

**E. Helper Laporan (Client-side Image Gen)**
- Tidak perlu composer package.
- Siapkan script `html2canvas` via CDN di layout wrapper Filament atau custom widget blade nantinya.

### 4. Database Config Rules
- **Decimal:** Semua field uang dan berat WAJIB menggunakan `decimal(15, 2)` atau `decimal(10, 3)` untuk berat presisi. JANGAN gunakan `float` atau `double`.
- **SoftDeletes:** Tambahkan trait `SoftDeletes` di semua Model Master Data dan Transaksi.