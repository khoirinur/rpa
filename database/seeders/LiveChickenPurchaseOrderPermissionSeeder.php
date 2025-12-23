<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LiveChickenPurchaseOrderPermissionSeeder extends Seeder
{
    /**
     * Assign purchasing permissions to operational roles so the Filament module is visible.
     */
    public function run(): void
    {
        $guardName = config('auth.defaults.guard', 'web');

        $permissions = [
            'ViewAny:LiveChickenPurchaseOrder',
            'View:LiveChickenPurchaseOrder',
            'Create:LiveChickenPurchaseOrder',
            'Update:LiveChickenPurchaseOrder',
            'Delete:LiveChickenPurchaseOrder',
            'Restore:LiveChickenPurchaseOrder',
            'RestoreAny:LiveChickenPurchaseOrder',
            'ForceDelete:LiveChickenPurchaseOrder',
            'ForceDeleteAny:LiveChickenPurchaseOrder',
            'Replicate:LiveChickenPurchaseOrder',
            'Reorder:LiveChickenPurchaseOrder',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guardName,
            ]);
        }

        $roleNames = ['owner', 'admin_gudang'];

        foreach ($roleNames as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guardName,
            ]);

            $role->givePermissionTo($permissions);
        }
    }
}
