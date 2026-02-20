<?php

use App\Enums\RoleContext;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('role seeder creates default tenant roles', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::tenant()->count())->toBe(4);
    expect(Role::where('slug', 'owner')->exists())->toBeTrue();
    expect(Role::where('slug', 'admin')->exists())->toBeTrue();
    expect(Role::where('slug', 'member')->exists())->toBeTrue();
    expect(Role::where('slug', 'viewer')->exists())->toBeTrue();
});

test('role seeder creates default administration roles', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::administration()->count())->toBe(2);
    expect(Role::where('slug', 'super-admin')->exists())->toBeTrue();
    expect(Role::where('slug', 'support')->exists())->toBeTrue();
});

test('role seeder is idempotent', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(Role::count())->toBe(6);
});

test('permission seeder creates core permissions', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);

    expect(Permission::count())->toBe(11);
});

test('permission seeder assigns permissions to roles', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);

    $owner = Role::where('slug', 'owner')->first();
    expect($owner->permissions)->toHaveCount(6);

    $admin = Role::where('slug', 'admin')->first();
    expect($admin->permissions)->toHaveCount(4);

    $member = Role::where('slug', 'member')->first();
    expect($member->permissions)->toHaveCount(2);

    $viewer = Role::where('slug', 'viewer')->first();
    expect($viewer->permissions)->toHaveCount(1);

    $superAdmin = Role::where('slug', 'super-admin')->first();
    expect($superAdmin->permissions)->toHaveCount(5);

    $support = Role::where('slug', 'support')->first();
    expect($support->permissions)->toHaveCount(2);
});

test('permission seeder is idempotent', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    expect(Permission::count())->toBe(11);
});

test('all roles have correct context', function () {
    $this->seed(RoleSeeder::class);

    $tenantSlugs = ['owner', 'admin', 'member', 'viewer'];
    foreach ($tenantSlugs as $slug) {
        expect(Role::where('slug', $slug)->first()->context)->toBe(RoleContext::Tenant);
    }

    $adminSlugs = ['super-admin', 'support'];
    foreach ($adminSlugs as $slug) {
        expect(Role::where('slug', $slug)->first()->context)->toBe(RoleContext::Administration);
    }
});
