<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

beforeEach(function () {
    Route::middleware(['web', 'tenant', 'tenant.member'])->get('/test-member-route', fn () => 'ok');
});

test('non-member user gets 403', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('http://acme.localhost/test-member-route')
        ->assertForbidden();
});

test('tenant member passes through', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create();
    $role = Role::factory()->member()->create();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    $this->actingAs($user)
        ->get('http://acme.localhost/test-member-route')
        ->assertSuccessful();
});

test('unauthenticated request gets 403', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $this->get('http://acme.localhost/test-member-route')
        ->assertForbidden();
});
