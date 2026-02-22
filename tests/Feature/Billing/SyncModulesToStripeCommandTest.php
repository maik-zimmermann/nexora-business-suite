<?php

use App\Models\Module;
use App\Services\StripeProductSync;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('command shows warning and exits successfully when Stripe is not configured', function () {
    config(['cashier.secret' => null]);

    $this->artisan('modules:sync-stripe')
        ->expectsOutput('Stripe is not configured — skipping sync.')
        ->assertSuccessful();
});

test('command calls sync methods when Stripe is configured', function () {
    config(['cashier.secret' => 'sk_test_fake']);

    Module::unsetEventDispatcher();
    $modules = Module::factory()->count(3)->create();

    $mock = Mockery::mock(StripeProductSync::class);
    $mock->shouldReceive('syncSeatProduct')->once();
    $mock->shouldReceive('syncUsageProduct')->once();
    $mock->shouldReceive('sync')->times(3)->with(Mockery::type(Module::class));
    app()->instance(StripeProductSync::class, $mock);

    $this->artisan('modules:sync-stripe')
        ->assertSuccessful();
});
