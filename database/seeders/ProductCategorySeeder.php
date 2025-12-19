<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'HP', 'name' => 'Hasil Panen'],
            ['code' => 'LB', 'name' => 'Live Bird'],
            ['code' => 'PRD', 'name' => 'Produk'],
            ['code' => 'UMM', 'name' => 'Umum'],
        ];

        foreach ($categories as $category) {
            ProductCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                    'description' => null,
                ],
            );
        }
    }
}
