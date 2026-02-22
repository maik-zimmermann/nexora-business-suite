<?php

use App\Models\SeatSnapshot;
use App\Models\Tenant;
use App\Models\TenantMembership;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('adding a membership creates a seat snapshot', function () {
    $tenant = Tenant::factory()->create();

    TenantMembership::factory()->forTenant($tenant)->create();

    expect(SeatSnapshot::where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(SeatSnapshot::where('tenant_id', $tenant->id)->first()->seat_count)->toBe(1);
});

test('removing a membership creates a seat snapshot', function () {
    $tenant = Tenant::factory()->create();

    $membership = TenantMembership::factory()->forTenant($tenant)->create();

    // Reset snapshot count from creation
    SeatSnapshot::query()->delete();

    $membership->delete();

    expect(SeatSnapshot::where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(SeatSnapshot::where('tenant_id', $tenant->id)->first()->seat_count)->toBe(0);
});

test('adding multiple memberships creates multiple snapshots', function () {
    $tenant = Tenant::factory()->create();

    TenantMembership::factory()->forTenant($tenant)->create();
    TenantMembership::factory()->forTenant($tenant)->create();

    $snapshots = SeatSnapshot::where('tenant_id', $tenant->id)->orderBy('id')->get();

    expect($snapshots)->toHaveCount(2);
    expect($snapshots[0]->seat_count)->toBe(1);
    expect($snapshots[1]->seat_count)->toBe(2);
});
