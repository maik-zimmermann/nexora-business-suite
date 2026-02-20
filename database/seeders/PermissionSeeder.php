<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Members', 'slug' => 'members.view', 'group' => 'members'],
            ['name' => 'Manage Members', 'slug' => 'members.manage', 'group' => 'members'],
            ['name' => 'Remove Members', 'slug' => 'members.remove', 'group' => 'members'],
            ['name' => 'View Settings', 'slug' => 'settings.view', 'group' => 'settings'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'group' => 'settings'],
            ['name' => 'Manage Tenant', 'slug' => 'tenant.manage', 'group' => 'tenant'],
            ['name' => 'View Tenants', 'slug' => 'tenants.view', 'group' => 'tenants'],
            ['name' => 'Manage Tenants', 'slug' => 'tenants.manage', 'group' => 'tenants'],
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'users'],
            ['name' => 'Impersonate', 'slug' => 'impersonate', 'group' => 'administration'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $this->assignPermissionsToRoles();
    }

    /**
     * Assign permissions to the default roles.
     */
    protected function assignPermissionsToRoles(): void
    {
        /** @var array<string, list<string>> */
        $rolePermissions = [
            'owner' => [
                'members.view', 'members.manage', 'members.remove',
                'settings.view', 'settings.manage', 'tenant.manage',
            ],
            'admin' => [
                'members.view', 'members.manage',
                'settings.view', 'settings.manage',
            ],
            'member' => [
                'members.view',
                'settings.view',
            ],
            'viewer' => [
                'members.view',
            ],
            'super-admin' => [
                'tenants.view', 'tenants.manage',
                'users.view', 'users.manage',
                'impersonate',
            ],
            'support' => [
                'tenants.view',
                'users.view',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)->first();

            if ($role) {
                $permissionIds = Permission::whereIn('slug', $permissionSlugs)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }
        }
    }
}
