<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $customers = [
        //     [
        //         'code' => 'C-0100',
        //         'name' => 'Retail Kediri Makmur',
        //         'contact_email' => 'retail+kediri@example.com',
        //         'phones' => ['+6281234567890', '+6285234567891'],
        //         'address_line' => 'Jl. Joyoboyo No. 12',
        //         'city' => 'Kediri',
        //         'province' => 'Jawa Timur',
        //         'notes' => 'Sample retail account untuk uji activity log.',
        //         'category_code' => 'RETAIL',
        //         'warehouse_code' => 'CNDI',
        //     ],
        //     [
        //         'code' => 'C-0200',
        //         'name' => 'MBG Nusantara',
        //         'contact_email' => 'mbg.nusantara@example.com',
        //         'phones' => ['+6282122233344'],
        //         'address_line' => 'Jl. Wates Selatan No. 5',
        //         'city' => 'Kediri',
        //         'province' => 'Jawa Timur',
        //         'notes' => 'Segment MBG untuk kebutuhan Shield testing.',
        //         'category_code' => 'MBG',
        //     ],
        //     [
        //         'code' => 'C-0300',
        //         'name' => 'Partai Sentosa',
        //         'contact_email' => 'partai.sentosa@example.com',
        //         'phones' => ['+6281334455667'],
        //         'address_line' => 'Jl. Raya Industri KM 7',
        //         'city' => 'Sidoarjo',
        //         'province' => 'Jawa Timur',
        //         'notes' => 'Customer partai untuk validasi akses Owner/Admin.',
        //         'category_code' => 'PARTAI',
        //         'warehouse_code' => 'TNJG',
        //     ],
        //     [
        //         'code' => 'C-0400',
        //         'name' => 'Customer Baru Nusantara',
        //         'contact_email' => 'baru.nusantara@example.com',
        //         'phones' => ['+6281998877665'],
        //         'address_line' => 'Jl. Pahlawan No. 8',
        //         'city' => 'Surabaya',
        //         'province' => 'Jawa Timur',
        //         'notes' => 'Entry percontohan untuk kategori Customer Baru.',
        //         'category_code' => 'C-BARU',
        //     ],
        // ];

        // foreach ($customers as $customerData) {
        //     $category = CustomerCategory::query()
        //         ->where('code', strtoupper($customerData['category_code']))
        //         ->orWhere('name', $customerData['category_name'] ?? $customerData['category_code'])
        //         ->first();

        //     if (! $category) {
        //         throw new RuntimeException(sprintf('Kategori customer %s tidak ditemukan.', $customerData['category_code']));
        //     }

        //     $warehouseId = null;

        //     if (! empty($customerData['warehouse_code'])) {
        //         $warehouseId = Warehouse::query()
        //             ->where('code', strtoupper($customerData['warehouse_code']))
        //             ->value('id');
        //     }

        //     if (! $warehouseId) {
        //         $warehouseId = $category->default_warehouse_id;
        //     }

        //     Customer::updateOrCreate(
        //         ['code' => Str::upper($customerData['code'])],
        //         [
        //             'name' => $customerData['name'],
        //             'contact_email' => $customerData['contact_email'] ?? null,
        //             'contact_phone' => implode(';', $customerData['phones']),
        //             'customer_category_id' => $category->getKey(),
        //             'default_warehouse_id' => $warehouseId,
        //             'address_line' => $customerData['address_line'] ?? null,
        //             'city' => $customerData['city'] ?? null,
        //             'province' => $customerData['province'] ?? null,
        //             'notes' => $customerData['notes'] ?? null,
        //             'is_active' => true,
        //         ],
        //     );
        // }
    }
}
