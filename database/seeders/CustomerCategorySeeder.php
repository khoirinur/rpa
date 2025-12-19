<?php

namespace Database\Seeders;

use App\Models\CustomerCategory;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class CustomerCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'C-LAMA',
                'name' => 'Customer Lama',
                'default_warehouse_code' => 'PAGU',
            ],
            [
                'code' => 'C-BARU',
                'name' => 'Customer Baru',
                'default_warehouse_code' => 'TNJG',
            ],
            [
                'code' => 'RETAIL',
                'name' => 'Retail',
                'default_warehouse_code' => 'CNDI',
            ],
            [
                'code' => 'MBG',
                'name' => 'MBG',
                'default_warehouse_code' => 'PAGU',
            ],
            [
                'code' => 'PARTAI',
                'name' => 'Partai',
                'default_warehouse_code' => 'TNJG',
            ],
        ];

        foreach ($categories as $category) {
            $warehouseId = Warehouse::where('code', $category['default_warehouse_code'] ?? null)
                ->value('id');

            CustomerCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'default_warehouse_id' => $warehouseId,
                    'description' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
