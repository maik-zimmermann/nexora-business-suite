<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

test('isAdministrator returns true when admin_role_id is set', function () {
    $user = User::factory()->administrator()->create();

    expect($user->isAdministrator())->toBeTrue();
});

test('isAdministrator returns false when admin_role_id is null', function () {
    $user = User::factory()->create();

    expect($user->isAdministrator())->toBeFalse();
});

test('isMemberOf returns true when user has membership', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->member()->create();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    expect($user->isMemberOf($tenant))->toBeTrue();
});

test('isMemberOf returns false when user has no membership', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    expect($user->isMemberOf($tenant))->toBeFalse();
});

test('hasTenantRole checks role slug for specific tenant', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $ownerRole = Role::factory()->owner()->create();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $ownerRole->id,
    ]);

    expect($user->hasTenantRole($tenant, 'owner'))->toBeTrue();
    expect($user->hasTenantRole($tenant, 'member'))->toBeFalse();
});

test('hasPermissionTo resolves permission via tenant membership role', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->member()->create();
    $permission = Permission::factory()->create(['slug' => 'members.view']);
    $role->permissions()->attach($permission);

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    expect($user->hasPermissionTo('members.view', $tenant))->toBeTrue();
    expect($user->hasPermissionTo('members.manage', $tenant))->toBeFalse();
});

test('hasPermissionTo resolves permission via admin role', function () {
    $role = Role::factory()->superAdmin()->create();
    $permission = Permission::factory()->create(['slug' => 'tenants.view']);
    $role->permissions()->attach($permission);

    $user = User::factory()->create(['admin_role_id' => $role->id]);

    expect($user->hasPermissionTo('tenants.view'))->toBeTrue();
    expect($user->hasPermissionTo('nonexistent'))->toBeFalse();
});

test('hasPermissionTo returns false with no membership and no admin role', function () {
    $user = User::factory()->create();

    expect($user->hasPermissionTo('members.view'))->toBeFalse();
});

test('hasPermissionTo uses current tenant from Tenancy singleton', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->member()->create();
    $permission = Permission::factory()->create(['slug' => 'settings.view']);
    $role->permissions()->attach($permission);

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    app(Tenancy::class)->set($tenant);

    expect($user->hasPermissionTo('settings.view'))->toBeTrue();
});

test('gate can() delegates to hasPermissionTo', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->member()->create();
    $permission = Permission::factory()->create(['slug' => 'members.view']);
    $role->permissions()->attach($permission);

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    app(Tenancy::class)->set($tenant);

    expect($user->can('members.view'))->toBeTrue();
    expect($user->can('members.manage'))->toBeFalse();
});

test('super-admin can() returns true for any ability', function () {
    $role = Role::factory()->superAdmin()->create();
    $user = User::factory()->create(['admin_role_id' => $role->id]);

    expect($user->can('members.view'))->toBeTrue();
    expect($user->can('anything.at.all'))->toBeTrue();
});
