<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'code' => 'KRKS-FRESH',
                'name' => 'Karkas Ayam Fresh',
                'type' => 'persediaan',
                'unit' => 'KG',
                'category' => 'hasil_panen',
                'default_warehouse_code' => 'PAGU',
                'description' => 'Produk utama hasil penyembelihan, distribusi harian.',
            ],
            [
                'code' => 'CKR-FROZEN',
                'name' => 'Ceker Beku',
                'type' => 'persediaan',
                'unit' => 'KG',
                'category' => 'produk',
                'default_warehouse_code' => 'TNJG',
                'description' => 'Ceker ayam beku siap kirim.',
            ],
            [
                'code' => 'LVBIRD',
                'name' => 'Ayam Hidup',
                'type' => 'persediaan',
                'unit' => 'EKR',
                'category' => 'live_bird',
                'default_warehouse_code' => 'PAGU',
                'description' => 'Stok ayam hidup untuk proses penyembelihan.',
            ],
            [
                'code' => 'JASA-POTONG',
                'name' => 'Jasa Potong Custom',
                'type' => 'jasa',
                'unit' => 'EKR',
                'category' => 'umum',
                'default_warehouse_code' => 'CNDI',
                'description' => 'Layanan penyembelihan sesuai permintaan customer.',
            ],
        ];

        foreach ($products as $productData) {
            $warehouseId = Warehouse::where('code', $productData['default_warehouse_code'] ?? null)
                ->value('id');

            Product::updateOrCreate(
                ['code' => $productData['code']],
                [
                    'name' => $productData['name'],
                    'slug' => Str::slug($productData['name']),
                    'type' => $productData['type'],
                    'unit' => $productData['unit'],
                    'category' => $productData['category'],
                    'default_warehouse_id' => $warehouseId,
                    'is_active' => true,
                    'description' => $productData['description'] ?? null,
                ],
            );
        }
    }
}
