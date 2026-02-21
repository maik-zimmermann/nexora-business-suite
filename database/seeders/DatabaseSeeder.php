<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(ModuleSeeder::class);
        $this->call(TenantSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $acme = Tenant::where('slug', 'acme')->first();
        $ownerRole = Role::where('slug', 'owner')->first();

        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $acme->id,
            'role_id' => $ownerRole->id,
        ]);

        $superAdminRole = Role::where('slug', 'super-admin')->first();

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'admin_role_id' => $superAdminRole->id,
        ]);
    }
}
