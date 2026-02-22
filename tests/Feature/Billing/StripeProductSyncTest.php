<?php

use App\Models\AppSetting;
use App\Models\Module;
use App\Services\StripeProductSync;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('sync is a no-op when cashier secret is null', function () {
    config(['cashier.secret' => null]);

    Module::unsetEventDispatcher();
    $module = Module::factory()->withoutStripePrices()->create();

    $sync = new StripeProductSync;
    $sync->sync($module);

    $module->refresh();
    expect($module->stripe_monthly_price_id)->toBeNull();
    expect($module->stripe_annual_price_id)->toBeNull();
});

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

test('syncUsageProduct skips creation if price ID already exists in AppSetting', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    AppSetting::set('billing.usage_metered_price_id', 'price_existing_usage');

    $sync = new StripeProductSync;
    $sync->syncUsageProduct();

    expect(AppSetting::get('billing.usage_metered_price_id'))->toBe('price_existing_usage');
});
