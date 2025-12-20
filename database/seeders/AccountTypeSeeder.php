<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultWarehouseId = Warehouse::query()
            ->where('is_default', true)
            ->value('id');

        $types = [
            ['code' => 'AKM-PSN', 'name' => 'Akumulasi Penyusutan', 'category' => 'akumulasi_penyusutan', 'warehouse_code' => 'PAGU'],
            ['code' => 'AST-LAIN', 'name' => 'Aset Lainnya', 'category' => 'aset_lainnya', 'warehouse_code' => 'PAGU'],
            ['code' => 'AST-LANCAR', 'name' => 'Aset Lancar Lainnya', 'category' => 'aset_lancar_lainnya', 'warehouse_code' => 'PAGU'],
            ['code' => 'AST-TETAP', 'name' => 'Aset Tetap', 'category' => 'aset_tetap', 'warehouse_code' => 'PAGU'],
            ['code' => 'BEBAN', 'name' => 'Beban', 'category' => 'beban'],
            ['code' => 'BEBAN-LAIN', 'name' => 'Beban Lainnya', 'category' => 'beban_lainnya'],
            ['code' => 'BPP', 'name' => 'Beban Pokok Penjualan', 'category' => 'beban_pokok_penjualan'],
            ['code' => 'KAS-BANK', 'name' => 'Kas & Bank', 'category' => 'kas_bank', 'warehouse_code' => 'PAGU'],
            ['code' => 'LIA-JP', 'name' => 'Liabilitas Jangka Panjang', 'category' => 'liabilitas_jangka_panjang'],
            ['code' => 'LIA-JPENDEK', 'name' => 'Liabilitas Jangka Pendek', 'category' => 'liabilitas_jangka_pendek'],
            ['code' => 'MODAL', 'name' => 'Modal', 'category' => 'modal'],
            ['code' => 'PNDPTN', 'name' => 'Pendapatan', 'category' => 'pendapatan'],
            ['code' => 'PNDPTN-LAIN', 'name' => 'Pendapatan Lainnya', 'category' => 'pendapatan_lainnya'],
            ['code' => 'PERSEDIAAN', 'name' => 'Persediaan', 'category' => 'persediaan', 'warehouse_code' => 'PAGU'],
            ['code' => 'PIUTANG-USAHA', 'name' => 'Piutang Usaha', 'category' => 'piutang_usaha', 'warehouse_code' => 'PAGU'],
            ['code' => 'UTANG-USAHA', 'name' => 'Utang Usaha', 'category' => 'utang_usaha'],
        ];

        foreach ($types as $type) {
            $warehouseId = null;

            if (! empty($type['warehouse_code'])) {
                $warehouseId = Warehouse::query()
                    ->where('code', strtoupper($type['warehouse_code']))
                    ->value('id');
            }

            if (! $warehouseId) {
                $warehouseId = $defaultWarehouseId;
            }

            AccountType::updateOrCreate(
                ['code' => Str::upper($type['code'])],
                [
                    'name' => $type['name'],
                    'category' => $type['category'],
                    'default_warehouse_id' => $warehouseId,
                    'is_active' => true,
                    'description' => null,
                ],
            );
        }
    }
}
