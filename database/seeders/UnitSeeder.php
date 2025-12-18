<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'code' => 'KG',
                'name' => 'Kilogram',
                'measurement_type' => 'weight',
                'decimal_places' => 3,
                'is_decimal' => true,
                'description' => 'Satuan utama untuk berat (Kg).',
            ],
            [
                'code' => 'EKR',
                'name' => 'Ekor',
                'measurement_type' => 'count',
                'decimal_places' => 0,
                'is_decimal' => false,
                'description' => 'Menghitung jumlah ayam hidup (ekor).',
            ],
            [
                'code' => 'PCK',
                'name' => 'Pack',
                'measurement_type' => 'package',
                'decimal_places' => 0,
                'is_decimal' => false,
                'description' => 'Digunakan untuk produk kemasan (pack).',
            ],
            [
                'code' => 'KRG',
                'name' => 'Karung',
                'measurement_type' => 'package',
                'decimal_places' => 0,
                'is_decimal' => false,
                'description' => 'Digunakan untuk material dalam karung.',
            ],
        ];

        foreach ($units as $unitData) {
            Unit::updateOrCreate(
                ['code' => $unitData['code']],
                [
                    'name' => $unitData['name'],
                    'slug' => Str::slug($unitData['name']),
                    'measurement_type' => $unitData['measurement_type'],
                    'decimal_places' => $unitData['decimal_places'],
                    'is_decimal' => $unitData['is_decimal'],
                    'is_active' => true,
                    'description' => $unitData['description'] ?? null,
                ],
            );
        }
    }
}
