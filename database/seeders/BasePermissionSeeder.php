<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class BasePermissionSeeder extends Seeder
{
    /**
     * Resource name used in permission keys, e.g. "Product".
     */
    protected string $resource;

    /**
     * Roles that should receive the generated permissions.
     */
    protected array $roleNames = ['owner', 'admin_gudang'];

    /**
     * Override to customize which permission actions are generated.
     */
    protected array $actions = [
        'ViewAny',
        'View',
        'Create',
        'Update',
        'Delete',
        'Restore',
        'RestoreAny',
        'ForceDelete',
        'ForceDeleteAny',
        'Replicate',
        'Reorder',
    ];

    public function run(): void
    {
        $guardName = config('auth.defaults.guard', 'web');
        $permissions = $this->buildPermissionNames();

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guardName,
            ]);
        }

        foreach ($this->roleNames as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guardName,
            ]);

            $role->givePermissionTo($permissions);
        }
    }

    protected function buildPermissionNames(): array
    {
        return array_map(fn (string $action) => sprintf('%s:%s', $action, $this->resource), $this->actions);
    }
}
