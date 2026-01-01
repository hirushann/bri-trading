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
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Sales Representative', 'guard_name' => 'web']);
        
        // Give basic permissions
        $role->givePermissionTo([
            'view_any_order', 'view_order', 'create_order', 
            'view_any_product', 'view_product', 
            'view_any_customer', 'view_customer', 'create_customer',
            'view_any_invoice', 'view_invoice'
        ]);
    }
}
