<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Hash;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

test('password update page is displayed', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this
        ->actingAs($user)
        ->get(tenantUrl('acme', '/settings/password'));

    $response->assertOk();
});

test('password can be updated', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this
        ->actingAs($user)
        ->from(tenantUrl('acme', '/settings/password'))
        ->put(tenantUrl('acme', '/settings/password'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantUrl('acme', '/settings/password'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $response = $this
        ->actingAs($user)
        ->from(tenantUrl('acme', '/settings/password'))
        ->put(tenantUrl('acme', '/settings/password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(tenantUrl('acme', '/settings/password'));
});
