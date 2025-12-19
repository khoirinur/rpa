<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $products = [
        //     [
        //         'code' => 'KRKS-FRESH',
        //         'name' => 'Karkas Ayam Fresh',
        //         'type' => 'persediaan',
        //         'unit_code' => 'KG',
        //         'unit_name' => 'Kilogram',
        //         'category_code' => 'HP',
        //         'category_name' => 'Hasil Panen',
        //         'default_warehouse_code' => 'PAGU',
        //         'description' => 'Produk utama hasil penyembelihan, distribusi harian.',
        //     ],
        //     [
        //         'code' => 'CKR-FROZEN',
        //         'name' => 'Ceker Beku',
        //         'type' => 'persediaan',
        //         'unit_code' => 'KG',
        //         'unit_name' => 'Kilogram',
        //         'category_code' => 'PRD',
        //         'category_name' => 'Produk',
        //         'default_warehouse_code' => 'TNJG',
        //         'description' => 'Ceker ayam beku siap kirim.',
        //     ],
        //     [
        //         'code' => 'LVBIRD',
        //         'name' => 'Ayam Hidup',
        //         'type' => 'persediaan',
        //         'unit_code' => 'EKR',
        //         'unit_name' => 'Ekor',
        //         'category_code' => 'LB',
        //         'category_name' => 'Live Bird',
        //         'default_warehouse_code' => 'PAGU',
        //         'description' => 'Stok ayam hidup untuk proses penyembelihan.',
        //     ],
        //     [
        //         'code' => 'JASA-POTONG',
        //         'name' => 'Jasa Potong Custom',
        //         'type' => 'jasa',
        //         'unit_code' => 'EKR',
        //         'unit_name' => 'Ekor',
        //         'category_code' => 'UMM',
        //         'category_name' => 'Umum',
        //         'default_warehouse_code' => 'CNDI',
        //         'description' => 'Layanan penyembelihan sesuai permintaan customer.',
        //     ],
        // ];

        // foreach ($products as $productData) {
        //     $warehouseId = Warehouse::where('code', $productData['default_warehouse_code'] ?? null)
        //         ->value('id');

        //     $categoryId = ProductCategory::query()
        //         ->where('code', strtoupper($productData['category_code'] ?? ''))
        //         ->orWhere('name', $productData['category_name'] ?? $productData['category_code'] ?? '')
        //         ->value('id');

        //     $unitId = Unit::query()
        //         ->where('code', strtoupper($productData['unit_code'] ?? ''))
        //         ->orWhere('name', $productData['unit_name'] ?? $productData['unit_code'] ?? '')
        //         ->value('id');

        //     if (! $categoryId || ! $unitId) {
        //         throw new RuntimeException('Referensi kategori/ satuan untuk seeder produk tidak ditemukan.');
        //     }

        //     Product::updateOrCreate(
        //         ['code' => $productData['code']],
        //         [
        //             'name' => $productData['name'],
        //             'type' => $productData['type'],
        //             'product_category_id' => $categoryId,
        //             'unit_id' => $unitId,
        //             'default_warehouse_id' => $warehouseId,
        //             'is_active' => true,
        //             'description' => $productData['description'] ?? null,
        //         ],
        //     );
        // }
    }
}
