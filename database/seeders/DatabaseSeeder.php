<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
    }
}
