<?php

use App\Models\Module;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('checkout page renders with modules', function () {
    Module::factory()->count(3)->create();

    $response = $this->get(route('checkout.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('checkout/PlanBuilder')
        ->has('modules', 3)
        ->has('minimumSeats')
        ->has('billingIntervals')
    );
});

test('checkout page only shows active modules', function () {
    Module::factory()->count(2)->create();
    Module::factory()->inactive()->create();

    $response = $this->get(route('checkout.index'));

    $response->assertInertia(fn ($page) => $page->has('modules', 2));
});

test('checkout store validates required fields', function () {
    $response = $this->post(route('checkout.store'), []);

    $response->assertSessionHasErrors(['email', 'module_slugs', 'seat_limit', 'usage_quota', 'billing_interval']);
});

test('checkout store validates module slugs exist', function () {
    $response = $this->post(route('checkout.store'), [
        'email' => 'test@example.com',
        'module_slugs' => ['nonexistent'],
        'seat_limit' => 5,
        'usage_quota' => 1000,
        'billing_interval' => 'monthly',
    ]);

    $response->assertSessionHasErrors('module_slugs.0');
});

test('checkout store validates minimum seat limit', function () {
    Module::factory()->create(['slug' => 'crm']);

    $response = $this->post(route('checkout.store'), [
        'email' => 'test@example.com',
        'module_slugs' => ['crm'],
        'seat_limit' => 1,
        'usage_quota' => 1000,
        'billing_interval' => 'monthly',
    ]);

    $response->assertSessionHasErrors('seat_limit');
});

test('checkout store validates billing interval enum', function () {
    Module::factory()->create(['slug' => 'crm']);

    $response = $this->post(route('checkout.store'), [
        'email' => 'test@example.com',
        'module_slugs' => ['crm'],
        'seat_limit' => 5,
        'usage_quota' => 1000,
        'billing_interval' => 'weekly',
    ]);

    $response->assertSessionHasErrors('billing_interval');
});

test('checkout success page renders', function () {
    $response = $this->get(route('checkout.success'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('checkout/Success'));
});

test('checkout cancelled page renders', function () {
    $response = $this->get(route('checkout.cancelled'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('checkout/Cancelled'));
});
