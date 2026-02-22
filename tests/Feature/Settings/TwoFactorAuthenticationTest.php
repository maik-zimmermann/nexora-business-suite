<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

test('two factor settings page can be rendered', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(tenantUrl('acme', '/settings/two-factor'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TwoFactor')
            ->where('twoFactorEnabled', false)
        );
});

test('two factor settings page requires password confirmation when enabled', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(tenantUrl('acme', '/settings/two-factor'));

    $response->assertRedirect(route('password.confirm'));
});

test('two factor settings page does not requires password confirmation when disabled', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $this->actingAs($user)
        ->get(tenantUrl('acme', '/settings/two-factor'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TwoFactor')
        );
});

test('two factor settings page returns forbidden response when two factor is disabled', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    config(['fortify.features' => []]);

    $user = User::factory()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(tenantUrl('acme', '/settings/two-factor'))
        ->assertForbidden();
});
