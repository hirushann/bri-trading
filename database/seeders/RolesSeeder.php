<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Sales Representative', 'guard_name' => 'web']);
        
        $permissions = [
            'view_any_order', 'view_order', 'create_order', 
            'view_any_product', 'view_product', 
            'view_any_customer', 'view_customer', 'create_customer',
            'view_any_invoice', 'view_invoice'
        ];

        // Ensure permissions exist before assigning them
        foreach ($permissions as $permissionName) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Give basic permissions to Sales Representative
        $role->givePermissionTo($permissions);

        // Ensure Admin role has all permissions
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(\Spatie\Permission\Models\Permission::all());
    }
}
