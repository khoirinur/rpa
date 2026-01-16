<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccountImport;
use App\Models\CustomerCategory;
use App\Models\CustomerImport;
use App\Models\ProductImport;
use App\Models\SupplierCategory;
use App\Models\SupplierImport;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RefreshSeedAndImportCommand extends Command
{
    protected $signature = 'rpa:refresh-seed-import';

    protected $description = 'migrate:fresh --seed lalu impor CSV coa, supplier, customer, products';

    public function handle(): int
    {
        $this->info('Menjalankan migrate:fresh --seed ...');
        $this->call('migrate:fresh', ['--seed' => true]);

        try {
            $this->importChartOfAccounts();
            $this->importSuppliers();
            $this->importCustomers();
            $this->importProducts();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Selesai. Semua import selesai diproses.');

        return self::SUCCESS;
    }

    protected function importChartOfAccounts(): void
    {
        $storedPath = $this->storeCsv('coa.csv');

        $import = ChartOfAccountImport::create([
            'file_name' => 'coa.csv',
            'file_path' => $storedPath,
            'file_disk' => 'public',
        ]);

        $this->info('Mengimpor Chart of Account ...');
        $import->process();
        $this->summarize($import->status, $import->imported_rows, $import->failed_rows, $import->log, 'Chart of Account');
    }

    protected function importSuppliers(): void
    {
        $storedPath = $this->storeCsv('supplier.csv');

        $import = SupplierImport::create([
            'file_name' => 'supplier.csv',
            'file_path' => $storedPath,
            'file_disk' => 'public',
            'fallback_supplier_category_id' => SupplierCategory::query()->value('id'),
            'default_warehouse_id' => $this->defaultWarehouseId(),
        ]);

        $this->info('Mengimpor Supplier ...');
        $import->process();
        $this->summarize($import->status, $import->imported_rows, $import->failed_rows, $import->log, 'Supplier');
    }

    protected function importCustomers(): void
    {
        $storedPath = $this->storeCsv('customer.csv');

        $import = CustomerImport::create([
            'file_name' => 'customer.csv',
            'file_path' => $storedPath,
            'file_disk' => 'public',
            'fallback_customer_category_id' => CustomerCategory::query()->value('id'),
            'default_warehouse_id' => $this->defaultWarehouseId(),
        ]);

        $this->info('Mengimpor Customer ...');
        $import->process();
        $this->summarize($import->status, $import->imported_rows, $import->failed_rows, $import->log, 'Customer');
    }

    protected function importProducts(): void
    {
        $storedPath = $this->storeCsv('products.csv');

        $import = ProductImport::create([
            'file_name' => 'products.csv',
            'file_path' => $storedPath,
            'file_disk' => 'public',
            'default_warehouse_id' => $this->defaultWarehouseId(),
        ]);

        $this->info('Mengimpor Produk ...');
        $import->process();
        $this->summarize($import->status, $import->imported_rows, $import->failed_rows, $import->log, 'Produk');
    }

    protected function storeCsv(string $filename): string
    {
        $absolutePath = base_path($filename);

        if (! File::exists($absolutePath)) {
            throw new RuntimeException("Berkas {$filename} tidak ditemukan di root project.");
        }

        $disk = Storage::disk('public');
        $targetPath = 'seed-imports/'.Str::random(8).'-'.$filename;

        $disk->put($targetPath, File::get($absolutePath));

        return $targetPath;
    }

    protected function defaultWarehouseId(): ?int
    {
        return Warehouse::query()
            ->where('is_default', true)
            ->value('id')
            ?? Warehouse::query()->value('id');
    }

    protected function summarize(string $status, ?int $imported, ?int $failed, ?array $log, string $label): void
    {
        $this->info(sprintf('%s: status %s, berhasil %d, gagal %d', $label, $status, $imported ?? 0, $failed ?? 0));

        if (! empty($log)) {
            $this->warn(sprintf('Log %s:', $label));
            foreach ($log as $message) {
                $this->line('- '.$message);
            }
        }
    }
}
