<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

test('profile page is displayed', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this
        ->actingAs($user)
        ->get(tenantUrl('acme', '/settings/profile'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this
        ->actingAs($user)
        ->from(tenantUrl('acme', '/settings/profile'))
        ->patch(tenantUrl('acme', '/settings/profile'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantUrl('acme', '/settings/profile'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this
        ->actingAs($user)
        ->from(tenantUrl('acme', '/settings/profile'))
        ->patch(tenantUrl('acme', '/settings/profile'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantUrl('acme', '/settings/profile'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this
        ->actingAs($user)
        ->delete(tenantUrl('acme', '/settings/profile'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this
        ->actingAs($user)
        ->from(tenantUrl('acme', '/settings/profile'))
        ->delete(tenantUrl('acme', '/settings/profile'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(tenantUrl('acme', '/settings/profile'));

    expect($user->fresh())->not->toBeNull();
});
