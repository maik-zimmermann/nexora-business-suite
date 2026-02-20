<?php

use App\Exceptions\LastOwnerException;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

test('membership links user, tenant, and role', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->member()->create();

    $membership = TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    expect($membership->user->id)->toBe($user->id);
    expect($membership->tenant->id)->toBe($tenant->id);
    expect($membership->role->id)->toBe($role->id);
});

test('unique constraint prevents duplicate user-tenant membership', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->member()->create();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);
})->throws(\Illuminate\Database\QueryException::class);

test('user can be a member of multiple tenants', function () {
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $role = Role::factory()->member()->create();

    TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id, 'role_id' => $role->id]);
    TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantB->id, 'role_id' => $role->id]);

    expect($user->tenantMemberships)->toHaveCount(2);
});

test('deleting last owner throws LastOwnerException', function () {
    $tenant = Tenant::factory()->create();
    $ownerRole = Role::factory()->owner()->create();

    $membership = TenantMembership::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'role_id' => $ownerRole->id,
    ]);

    $membership->delete();
})->throws(LastOwnerException::class);

test('changing last owner role throws LastOwnerException', function () {
    $tenant = Tenant::factory()->create();
    $ownerRole = Role::factory()->owner()->create();
    $memberRole = Role::factory()->member()->create();

    $membership = TenantMembership::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'role_id' => $ownerRole->id,
    ]);

    $membership->role_id = $memberRole->id;
    $membership->save();
})->throws(LastOwnerException::class);

test('can delete owner when another owner exists', function () {
    $tenant = Tenant::factory()->create();
    $ownerRole = Role::factory()->owner()->create();

    $membership1 = TenantMembership::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'role_id' => $ownerRole->id,
    ]);

    TenantMembership::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'role_id' => $ownerRole->id,
    ]);

    $membership1->delete();

    expect(TenantMembership::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('can change owner role when another owner exists', function () {
    $tenant = Tenant::factory()->create();
    $ownerRole = Role::factory()->owner()->create();
    $memberRole = Role::factory()->member()->create();

    $membership1 = TenantMembership::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'role_id' => $ownerRole->id,
    ]);

    TenantMembership::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'role_id' => $ownerRole->id,
    ]);

    $membership1->role_id = $memberRole->id;
    $membership1->save();

    expect($membership1->fresh()->role_id)->toBe($memberRole->id);
});
