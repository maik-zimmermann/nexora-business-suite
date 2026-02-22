<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

afterEach(function () {
    app(Tenancy::class)->flush();
});

/*
|--------------------------------------------------------------------------
| Root Domain Routing
|--------------------------------------------------------------------------
*/

test('root domain renders welcome page for guests', function () {
    $response = $this->get(appUrl());

    $response->assertOk();
});

test('root domain dashboard returns 404 for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(appUrl('/dashboard'));

    $response->assertStatus(404);
});

test('root domain redirects authenticated single-tenant user to tenant dashboard', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $role = Role::where('slug', 'owner')->first();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    $response = $this->actingAs($user)->get(appUrl());

    $response->assertRedirect(Tenancy::tenantUrl($tenant, '/dashboard'));
});

test('root domain redirects authenticated multi-tenant user to tenant picker', function () {
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create(['slug' => 'acme']);
    $tenantB = Tenant::factory()->create(['slug' => 'globex']);
    $role = Role::where('slug', 'owner')->first();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenantA->id,
        'role_id' => $role->id,
    ]);
    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenantB->id,
        'role_id' => $role->id,
    ]);

    $response = $this->actingAs($user)->get(appUrl());

    $response->assertRedirect(route('tenants.show'));
});

test('root domain authenticated user with no tenants sees welcome page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(appUrl());

    $response->assertOk();
});

/*
|--------------------------------------------------------------------------
| Tenant Picker
|--------------------------------------------------------------------------
*/

test('tenant picker requires authentication', function () {
    $response = $this->get(appUrl('/tenants'));

    $response->assertRedirect();
});

test('tenant picker shows active tenants for authenticated user', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'name' => 'Acme Corp']);
    $role = Role::where('slug', 'owner')->first();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    $response = $this->actingAs($user)->get(appUrl('/tenants'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('TenantPicker')
        ->has('tenants', 1)
        ->where('tenants.0.slug', 'acme')
        ->where('tenants.0.name', 'Acme Corp')
    );
});

/*
|--------------------------------------------------------------------------
| Subdomain Routing
|--------------------------------------------------------------------------
*/

test('subdomain dashboard requires authentication', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this->get(tenantUrl('acme', '/dashboard'));

    $response->assertRedirect();
});

test('subdomain dashboard serves authenticated user', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(tenantUrl('acme', '/dashboard'));

    $response->assertOk();
});

test('subdomain settings requires authentication', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this->get(tenantUrl('acme', '/settings/profile'));

    $response->assertRedirect();
});

test('subdomain settings serves authenticated user', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(tenantUrl('acme', '/settings/profile'));

    $response->assertOk();
});

/*
|--------------------------------------------------------------------------
| Cross-Domain Redirects
|--------------------------------------------------------------------------
*/

test('public routes on subdomain redirect to root domain', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this->get(tenantUrl('acme', '/checkout'));

    $response->assertRedirect(appUrl('/checkout'));
});

test('onboarding route on subdomain redirects to root domain', function () {
    Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->passwordless()->create();

    $response = $this->get(tenantUrl('acme', '/onboarding/'.$user->id));

    $response->assertRedirect(appUrl('/onboarding/'.$user->id));
});

/*
|--------------------------------------------------------------------------
| Login Response Routing
|--------------------------------------------------------------------------
*/

test('login on root domain redirects single-tenant user to tenant dashboard', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $role = Role::where('slug', 'owner')->first();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
    ]);

    $response = $this->post(appUrl('/login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(Tenancy::tenantUrl($tenant, '/dashboard'));
});

test('login on root domain redirects multi-tenant user to tenant picker', function () {
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create(['slug' => 'acme']);
    $tenantB = Tenant::factory()->create(['slug' => 'globex']);
    $role = Role::where('slug', 'owner')->first();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenantA->id,
        'role_id' => $role->id,
    ]);
    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenantB->id,
        'role_id' => $role->id,
    ]);

    $response = $this->post(appUrl('/login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('tenants.show'));
});

test('login on root domain redirects user without tenants to root', function () {
    $user = User::factory()->create();

    $response = $this->post(appUrl('/login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/');
});

test('login on subdomain redirects to subdomain dashboard', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create();

    $response = $this->post(tenantUrl('acme', '/login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
});

test('logout redirects to root domain landing page', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(tenantUrl('acme', '/logout'));

    $this->assertGuest();
    $response->assertRedirect(appUrl(''));
});

/*
|--------------------------------------------------------------------------
| Inertia Shared Tenant Data
|--------------------------------------------------------------------------
*/

test('tenant shared prop is set on subdomain requests', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'name' => 'Acme Corp']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(tenantUrl('acme', '/dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('tenant.slug', 'acme')
        ->where('tenant.name', 'Acme Corp')
        ->where('tenant.baseUrl', fn ($url) => str_contains($url, 'acme'))
    );
});

test('tenant shared prop is null on root domain requests', function () {
    $response = $this->get(appUrl());

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('tenant', null)
    );
});
