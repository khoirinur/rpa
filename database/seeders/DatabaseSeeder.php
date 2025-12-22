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

        $superAdminEmail = env('SUPER_ADMIN_EMAIL', 'superadmin@rpa.test');
        $superAdminName = env('SUPER_ADMIN_NAME', 'Super Admin');
        $superAdminPassword = env('SUPER_ADMIN_PASSWORD', 'superadmin12345');

        $superadminUser = User::firstOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => $superAdminName,
                'password' => Hash::make($superAdminPassword),
            ],
        );

        $guardName = config('auth.defaults.guard', 'web');

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => $guardName,
        ]);

        if (! $superadminUser->hasRole($superAdminRole->name)) {
            $superadminUser->assignRole($superAdminRole);
        }

        $roleManagementPermissions = [
            'ViewAny:Role',
            'View:Role',
            'Create:Role',
            'Update:Role',
            'Delete:Role',
            'Restore:Role',
            'RestoreAny:Role',
            'ForceDelete:Role',
            'ForceDeleteAny:Role',
            'Replicate:Role',
            'Reorder:Role',
        ];

        $superAdminRole->revokePermissionTo($roleManagementPermissions);

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

        $this->call([
            UnitSeeder::class,
            ProductCategorySeeder::class,
            SupplierCategorySeeder::class,
            CustomerCategorySeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
            UserRoleSeeder::class,
        ]);
    }
}
