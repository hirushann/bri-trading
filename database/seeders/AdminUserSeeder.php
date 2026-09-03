<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure the Admin role exists
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        // Create the admin user
        $admin = User::firstOrCreate(
            ['email' => 'hirushan@dayzsolutions.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('elakiri123'),
            ]
        );

        // Assign the role to the user
        $admin->assignRole($adminRole);
    }
}
