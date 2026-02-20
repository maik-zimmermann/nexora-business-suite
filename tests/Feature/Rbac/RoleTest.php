<?php

use App\Enums\RoleContext;
use App\Models\Permission;
use App\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('role can be created with tenant context', function () {
    $role = Role::factory()->owner()->create();

    expect($role->name)->toBe('Owner');
    expect($role->slug)->toBe('owner');
    expect($role->context)->toBe(RoleContext::Tenant);
    expect($role->is_default)->toBeTrue();
});

test('role can be created with administration context', function () {
    $role = Role::factory()->superAdmin()->create();

    expect($role->context)->toBe(RoleContext::Administration);
});

test('context is cast to RoleContext enum', function () {
    $role = Role::factory()->create();

    expect($role->context)->toBeInstanceOf(RoleContext::class);
});

test('permissions relationship returns attached permissions', function () {
    $role = Role::factory()->create();
    $permission = Permission::factory()->create();

    $role->permissions()->attach($permission);

    expect($role->permissions)->toHaveCount(1);
    expect($role->permissions->first()->id)->toBe($permission->id);
});

test('hasPermission returns true for attached permission', function () {
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['slug' => 'members.view']);

    $role->permissions()->attach($permission);
    $role->load('permissions');

    expect($role->hasPermission('members.view'))->toBeTrue();
});

test('hasPermission returns false for unattached permission', function () {
    $role = Role::factory()->create();

    expect($role->hasPermission('members.view'))->toBeFalse();
});

test('tenant scope filters to tenant roles only', function () {
    Role::factory()->owner()->create();
    Role::factory()->superAdmin()->create();

    $tenantRoles = Role::tenant()->get();

    expect($tenantRoles)->toHaveCount(1);
    expect($tenantRoles->first()->slug)->toBe('owner');
});

test('administration scope filters to admin roles only', function () {
    Role::factory()->owner()->create();
    Role::factory()->superAdmin()->create();

    $adminRoles = Role::administration()->get();

    expect($adminRoles)->toHaveCount(1);
    expect($adminRoles->first()->slug)->toBe('super-admin');
});
