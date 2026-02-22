<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

test('guests on subdomain are redirected to the login page', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this->get(tenantUrl('acme', '/dashboard'));

    $response->assertRedirect();
});

test('authenticated users can visit the dashboard on tenant subdomain', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(tenantUrl('acme', '/dashboard'));

    $response->assertOk();
});

test('dashboard returns 404 on root domain', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(appUrl('/dashboard'));

    $response->assertStatus(404);
});
