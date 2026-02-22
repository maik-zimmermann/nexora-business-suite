<?php

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\StripeUsageReporter;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('reportSeats is a no-op when cashier secret is null', function () {
    config(['cashier.secret' => null]);

    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create(['tenant_id' => $tenant->id]);

    $reporter = app(StripeUsageReporter::class);
    $reporter->reportSeats($tenant);

    // No exception means success — Stripe was never called
    expect(true)->toBeTrue();
});

test('reportUsage is a no-op when cashier secret is null', function () {
    config(['cashier.secret' => null]);

    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create(['tenant_id' => $tenant->id]);

    $reporter = app(StripeUsageReporter::class);
    $reporter->reportUsage($tenant);

    expect(true)->toBeTrue();
});

test('reportSeats skips tenant with no active subscription', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->locked()->create([
        'tenant_id' => $tenant->id,
        'seat_stripe_price_id' => 'price_seat_123',
    ]);

    $reporter = app(StripeUsageReporter::class);
    $reporter->reportSeats($tenant);

    expect(true)->toBeTrue();
});

test('reportUsage skips tenant with no active subscription', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->locked()->create([
        'tenant_id' => $tenant->id,
        'usage_stripe_price_id' => 'price_usage_123',
    ]);

    $reporter = app(StripeUsageReporter::class);
    $reporter->reportUsage($tenant);

    expect(true)->toBeTrue();
});

test('reportSeats skips when seat_stripe_price_id is null', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'seat_stripe_price_id' => null,
    ]);

    $reporter = app(StripeUsageReporter::class);
    $reporter->reportSeats($tenant);

    expect(true)->toBeTrue();
});

test('reportUsage skips when usage_stripe_price_id is null', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'usage_stripe_price_id' => null,
    ]);

    $reporter = app(StripeUsageReporter::class);
    $reporter->reportUsage($tenant);

    expect(true)->toBeTrue();
});
