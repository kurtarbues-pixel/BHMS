<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => Role::ADMIN, 'label' => 'Admin'],
            ['name' => Role::LANDLORD, 'label' => 'Landlord'],
            ['name' => Role::STAFF, 'label' => 'Staff'],
            ['name' => Role::TENANT, 'label' => 'Tenant'],
        ] as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
