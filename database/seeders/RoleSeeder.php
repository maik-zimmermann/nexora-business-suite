<?php

namespace Database\Seeders;

use App\Enums\RoleContext;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Owner', 'slug' => 'owner', 'context' => RoleContext::Tenant, 'is_default' => true],
            ['name' => 'Admin', 'slug' => 'admin', 'context' => RoleContext::Tenant, 'is_default' => true],
            ['name' => 'Member', 'slug' => 'member', 'context' => RoleContext::Tenant, 'is_default' => true],
            ['name' => 'Viewer', 'slug' => 'viewer', 'context' => RoleContext::Tenant, 'is_default' => true],
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'context' => RoleContext::Administration, 'is_default' => true],
            ['name' => 'Support', 'slug' => 'support', 'context' => RoleContext::Administration, 'is_default' => true],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
