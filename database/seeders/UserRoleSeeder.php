<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserRoleSeeder extends Seeder
{
    /**
     * Seed predefined operational roles and users.
     */
    public function run(): void
    {
        $defaultPassword = env('DEFAULT_USER_PASSWORD', 'changeme123');
        $guardName = config('auth.defaults.guard', 'web');

        $users = [
            [
                'name' => 'Owner Surya Kencana',
                'email' => 'owner@rpa.test',
                'role' => 'owner',
            ],
            [
                'name' => 'Admin Gudang',
                'email' => 'admin.gudang@rpa.test',
                'role' => 'admin_gudang',
            ],
            [
                'name' => 'Produksi',
                'email' => 'produksi@rpa.test',
                'role' => 'produksi',
            ],
            [
                'name' => 'Sales',
                'email' => 'sales@rpa.test',
                'role' => 'sales',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($defaultPassword),
                ],
            );

            $role = Role::firstOrCreate([
                'name' => $userData['role'],
                'guard_name' => $guardName,
            ]);

            if (! $user->hasRole($role->name)) {
                $user->assignRole($role);
            }
        }
    }
}
