<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantSubscription;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('hasAvailableSeat returns true when under limit', function () {
    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'seat_limit' => 5,
    ]);

    $role = \App\Models\Role::where('slug', 'owner')->first();
    $user = User::factory()->create();
    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    expect($tenant->currentSeatCount())->toBe(1);
    expect($tenant->hasAvailableSeat())->toBeTrue();
});

test('hasAvailableSeat returns false when at limit', function () {
    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'seat_limit' => 2,
    ]);

    $role = \App\Models\Role::where('slug', 'owner')->first();

    User::factory()->count(2)->create()->each(function ($user) use ($tenant, $role) {
        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
        ]);
    });

    expect($tenant->currentSeatCount())->toBe(2);
    expect($tenant->hasAvailableSeat())->toBeFalse();
});

test('hasAvailableSeat returns true when no subscription exists', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->hasAvailableSeat())->toBeTrue();
});
