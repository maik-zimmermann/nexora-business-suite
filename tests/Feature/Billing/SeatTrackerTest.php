<?php

use App\Enums\BillingInterval;
use App\Models\SeatSnapshot;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantSubscription;
use App\Services\SeatTracker;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('record creates a seat snapshot with current seat count', function () {
    $tenant = Tenant::factory()->create();
    TenantMembership::factory()->count(3)->forTenant($tenant)->create();

    // Observer already created snapshots for each membership — clear them
    SeatSnapshot::query()->delete();

    $tracker = new SeatTracker;
    $tracker->record($tenant);

    expect(SeatSnapshot::where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(SeatSnapshot::where('tenant_id', $tenant->id)->first()->seat_count)->toBe(3);
});

test('peakSeatCount returns max from snapshots within billing period', function () {
    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'billing_interval' => BillingInterval::Monthly,
        'current_period_end' => now()->addDays(15),
    ]);

    // Within current period — peak of 8
    SeatSnapshot::create([
        'tenant_id' => $tenant->id,
        'seat_count' => 5,
        'recorded_at' => now()->subDays(3),
    ]);
    SeatSnapshot::create([
        'tenant_id' => $tenant->id,
        'seat_count' => 8,
        'recorded_at' => now()->subDays(1),
    ]);
    SeatSnapshot::create([
        'tenant_id' => $tenant->id,
        'seat_count' => 6,
        'recorded_at' => now(),
    ]);

    // Outside current period — should be ignored
    SeatSnapshot::create([
        'tenant_id' => $tenant->id,
        'seat_count' => 100,
        'recorded_at' => now()->subMonths(2),
    ]);

    $tracker = new SeatTracker;

    expect($tracker->peakSeatCount($tenant))->toBe(8);
});

test('peakSeatCount respects annual billing interval', function () {
    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'billing_interval' => BillingInterval::Annual,
        'current_period_end' => now()->addMonths(6),
    ]);

    // Within annual period
    SeatSnapshot::create([
        'tenant_id' => $tenant->id,
        'seat_count' => 12,
        'recorded_at' => now()->subMonths(3),
    ]);

    $tracker = new SeatTracker;

    expect($tracker->peakSeatCount($tenant))->toBe(12);
});

test('peakSeatCount falls back to current seat count when no snapshots exist', function () {
    $tenant = Tenant::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'current_period_end' => now()->addDays(15),
    ]);
    TenantMembership::factory()->count(4)->forTenant($tenant)->create();

    $tracker = new SeatTracker;

    expect($tracker->peakSeatCount($tenant))->toBe(4);
});

test('peakSeatCount falls back to current seat count when no subscription', function () {
    $tenant = Tenant::factory()->create();
    TenantMembership::factory()->count(2)->forTenant($tenant)->create();

    $tracker = new SeatTracker;

    expect($tracker->peakSeatCount($tenant))->toBe(2);
});
