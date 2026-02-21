<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\URL;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('signed url allows access to onboarding page', function () {
    $user = User::factory()->passwordless()->create();

    $url = URL::temporarySignedRoute('onboarding.show', now()->addDays(7), ['user' => $user->id]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Setup')
        ->has('email')
        ->has('user')
    );
});

test('expired signed url returns 403', function () {
    $user = User::factory()->passwordless()->create();

    $url = URL::temporarySignedRoute('onboarding.show', now()->subMinute(), ['user' => $user->id]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('unsigned url without authentication returns 403', function () {
    $user = User::factory()->passwordless()->create();

    $response = $this->get(route('onboarding.show', ['user' => $user->id]));

    $response->assertStatus(403);
});

test('already onboarded user is redirected to dashboard', function () {
    $user = User::factory()->create(['onboarding_completed_at' => now()]);

    $url = URL::temporarySignedRoute('onboarding.show', now()->addDays(7), ['user' => $user->id]);

    $response = $this->get($url);

    $response->assertRedirect(route('dashboard'));
});

test('completing onboarding updates user and tenant', function () {
    $user = User::factory()->passwordless()->create();
    $tenant = Tenant::factory()->inactive()->create();
    $ownerRole = Role::where('slug', 'owner')->first();

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $ownerRole->id,
    ]);

    $response = $this->actingAs($user)->post(route('onboarding.store', ['user' => $user->id]), [
        'name' => 'John Doe',
        'organisation_name' => 'Acme Inc',
        'slug' => 'acme-inc',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->name)->toBe('John Doe');
    expect($user->onboarding_completed_at)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();

    $tenant->refresh();
    expect($tenant->name)->toBe('Acme Inc');
    expect($tenant->slug)->toBe('acme-inc');
    expect($tenant->is_active)->toBeTrue();
});

test('onboarding validates duplicate slug', function () {
    $user = User::factory()->passwordless()->create();
    Tenant::factory()->create(['slug' => 'taken-slug']);

    $response = $this->actingAs($user)->post(route('onboarding.store', ['user' => $user->id]), [
        'name' => 'John',
        'organisation_name' => 'Taken Slug',
        'slug' => 'taken-slug',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertSessionHasErrors('slug');
});
