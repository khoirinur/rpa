<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdminEmail = env('SUPER_ADMIN_EMAIL', 'owner@example.com');
        $superAdminName = env('SUPER_ADMIN_NAME', 'Owner Surya Kencana');
        $superAdminPassword = env('SUPER_ADMIN_PASSWORD', 'owner12345');

        $ownerUser = User::firstOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => $superAdminName,
                'password' => Hash::make($superAdminPassword),
            ],
        );

        $guardName = config('auth.defaults.guard', 'web');

        $ownerRole = Role::firstOrCreate([
            'name' => config('filament-shield.super_admin.name', 'owner'),
            'guard_name' => $guardName,
        ]);

        if (! $ownerUser->hasRole($ownerRole->name)) {
            $ownerUser->assignRole($ownerRole);
        }

        $warehouses = [
            [
                'code' => 'PAGU',
                'name' => 'Gudang Pagu',
                'location' => 'Pagu - Kediri',
                'is_default' => true,
                'contact_name' => 'Admin Gudang Pagu',
                'contact_phone' => '0811-111-111',
                'capacity_kg' => 12000,
            ],
            [
                'code' => 'TNJG',
                'name' => 'Gudang Tanjung',
                'location' => 'Tanjung - Kediri',
                'contact_name' => 'Admin Gudang Tanjung',
                'contact_phone' => '0811-333-333',
                'capacity_kg' => 6000,
            ],
            [
                'code' => 'CNDI',
                'name' => 'Gudang Candi',
                'location' => 'Candi - Sidoarjo',
                'contact_name' => 'Admin Gudang Candi',
                'contact_phone' => '0811-444-444',
                'capacity_kg' => 4000,
            ],
        ];

        foreach ($warehouses as $warehouseData) {
            Warehouse::updateOrCreate(
                ['code' => $warehouseData['code']],
                [
                    'name' => $warehouseData['name'],
                    'slug' => Str::slug($warehouseData['name']),
                    'location' => $warehouseData['location'] ?? null,
                    'contact_name' => $warehouseData['contact_name'] ?? null,
                    'contact_phone' => $warehouseData['contact_phone'] ?? null,
                    'capacity_kg' => $warehouseData['capacity_kg'] ?? null,
                    'is_default' => $warehouseData['is_default'] ?? false,
                    'is_active' => $warehouseData['is_active'] ?? true,
                    'notes' => $warehouseData['notes'] ?? null,
                ],
            );
        }
    }
}
