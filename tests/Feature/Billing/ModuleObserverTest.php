<?php

use App\Models\Module;
use App\Services\StripeProductSync;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('sync is called when a module is created with Stripe configured', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $mock = Mockery::mock(StripeProductSync::class);
    $mock->shouldReceive('sync')->once()->with(Mockery::type(Module::class));
    app()->instance(StripeProductSync::class, $mock);

    Module::factory()->withoutStripePrices()->create();
});

test('sync is called when relevant fields are updated', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $mock = Mockery::mock(StripeProductSync::class);
    $mock->shouldReceive('sync')->twice()->with(Mockery::type(Module::class));
    app()->instance(StripeProductSync::class, $mock);

    $module = Module::factory()->withoutStripePrices()->create();
    $module->update(['name' => 'Updated Name']);
});

test('sync is not called when only sort_order changes', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    $mock = Mockery::mock(StripeProductSync::class);
    $mock->shouldReceive('sync')->once()->with(Mockery::type(Module::class));
    app()->instance(StripeProductSync::class, $mock);

    $module = Module::factory()->withoutStripePrices()->create();
    $module->update(['sort_order' => 99]);
});

test('sync is not called when cashier secret is null', function () {
    config(['cashier.secret' => null]);

    $mock = Mockery::mock(StripeProductSync::class);
    $mock->shouldNotReceive('sync');
    app()->instance(StripeProductSync::class, $mock);

    Module::factory()->withoutStripePrices()->create();
});
