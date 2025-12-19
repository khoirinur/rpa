<?php

namespace Database\Seeders;

use App\Models\SupplierCategory;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class SupplierCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'UMM',
                'name' => 'Umum',
                'default_warehouse_code' => 'PAGU',
                'description' => 'Kategori umum untuk pemasok tanpa klasifikasi khusus.',
            ],
            [
                'code' => 'BBK',
                'name' => 'Bahan Baku',
                'default_warehouse_code' => 'PAGU',
                'description' => 'Supplier bahan baku utama (Ayam Hidup, pakan, dll).',
            ],
            [
                'code' => 'PRL',
                'name' => 'Perlengkapan',
                'default_warehouse_code' => 'TNJG',
                'description' => 'Perlengkapan produksi & fasilitas pendukung.',
            ],
            [
                'code' => 'JSA',
                'name' => 'Jasa',
                'default_warehouse_code' => 'CNDI',
                'description' => 'Vendor jasa (transportasi, maintenance, outsourcing).',
            ],
        ];

        foreach ($categories as $category) {
            $warehouseId = Warehouse::where('code', $category['default_warehouse_code'] ?? null)
                ->value('id');

            SupplierCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'default_warehouse_id' => $warehouseId,
                    'description' => $category['description'] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }
}
