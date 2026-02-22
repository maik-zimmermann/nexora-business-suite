<?php

use App\Models\AppSetting;
use App\Services\StripeProductSync;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('syncSeatProduct is a no-op when cashier secret is null', function () {
    config(['cashier.secret' => null]);

    $sync = new StripeProductSync;
    $sync->syncSeatProduct();

    expect(AppSetting::query()->count())->toBe(0);
});

test('syncUsageProduct is a no-op when cashier secret is null', function () {
    config(['cashier.secret' => null]);

    $sync = new StripeProductSync;
    $sync->syncUsageProduct();

    expect(AppSetting::query()->count())->toBe(0);
});
