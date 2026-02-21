<?php

use App\Enums\UsageType;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\UsageRecord;
use App\Services\UsageTracker;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('recording usage creates a usage record', function () {
    $tenant = Tenant::factory()->create();
    $tracker = new UsageTracker;

    $tracker->record($tenant, UsageType::ApiCall, 3);

    expect($tenant->usageRecords()->count())->toBe(1);
    expect($tenant->usageRecords()->first()->quantity)->toBe(3);
    expect($tenant->usageRecords()->first()->type)->toBe(UsageType::ApiCall);
});

test('currentPeriodUsage sums usage within billing period', function () {
    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'current_period_end' => now()->addDays(15),
    ]);

    // Within current period
    UsageRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'quantity' => 10,
        'recorded_at' => now(),
    ]);
    UsageRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'quantity' => 20,
        'recorded_at' => now()->subDays(5),
    ]);

    // Outside current period
    UsageRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'quantity' => 100,
        'recorded_at' => now()->subMonths(2),
    ]);

    $tracker = new UsageTracker;

    expect($tracker->currentPeriodUsage($tenant))->toBe(30);
});

test('remainingQuota returns correct value', function () {
    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'usage_quota' => 1000,
        'current_period_end' => now()->addDays(15),
    ]);

    UsageRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'quantity' => 300,
        'recorded_at' => now(),
    ]);

    $tracker = new UsageTracker;

    expect($tracker->remainingQuota($tenant))->toBe(700);
});

test('isOverQuota returns true when usage exceeds quota', function () {
    $tenant = Tenant::factory()->create();
    $subscription = TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'usage_quota' => 100,
        'current_period_end' => now()->addDays(15),
    ]);

    UsageRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'quantity' => 150,
        'recorded_at' => now(),
    ]);

    expect($subscription->isOverQuota())->toBeTrue();
});

test('isOverQuota returns false when usage is within quota', function () {
    $tenant = Tenant::factory()->create();
    $subscription = TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'usage_quota' => 1000,
        'current_period_end' => now()->addDays(15),
    ]);

    UsageRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'quantity' => 50,
        'recorded_at' => now(),
    ]);

    expect($subscription->isOverQuota())->toBeFalse();
});

test('remainingQuota returns zero when no subscription', function () {
    $tenant = Tenant::factory()->create();
    $tracker = new UsageTracker;

    expect($tracker->remainingQuota($tenant))->toBe(0);
});
