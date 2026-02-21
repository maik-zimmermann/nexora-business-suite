<?php

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

beforeEach(function () {
    Route::middleware(['web', 'subscription.status'])->get('/test-subscription', fn () => 'ok');
});

test('active subscription passes middleware', function () {
    $tenant = Tenant::factory()->create(['slug' => 'active-co']);
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => SubscriptionStatus::Active,
    ]);

    app(Tenancy::class)->set($tenant);

    $response = $this->get('http://active-co.localhost/test-subscription');

    $response->assertOk();
    expect($response->getContent())->toBe('ok');
});

test('locked subscription returns 403', function () {
    $tenant = Tenant::factory()->create(['slug' => 'locked-co']);
    TenantSubscription::factory()->locked()->create([
        'tenant_id' => $tenant->id,
    ]);

    $response = $this->get('http://locked-co.localhost/test-subscription');

    $response->assertStatus(403);
});

test('read-only subscription passes with flag', function () {
    $tenant = Tenant::factory()->create(['slug' => 'readonly-co']);
    TenantSubscription::factory()->readOnly()->create([
        'tenant_id' => $tenant->id,
    ]);

    $response = $this->get('http://readonly-co.localhost/test-subscription');

    $response->assertOk();
});

test('subscription status command moves expired read-only to locked', function () {
    $subscription = TenantSubscription::factory()->readOnly()->create([
        'read_only_ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:update-statuses')
        ->assertExitCode(0);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Locked);
});

test('subscription status command does not lock non-expired read-only', function () {
    $subscription = TenantSubscription::factory()->readOnly()->create([
        'read_only_ends_at' => now()->addDays(10),
    ]);

    $this->artisan('subscription:update-statuses')
        ->assertExitCode(0);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::ReadOnly);
});
