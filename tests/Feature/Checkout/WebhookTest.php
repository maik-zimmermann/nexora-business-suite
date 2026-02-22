<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Events\TenantProvisioned;
use App\Listeners\HandleStripeCheckoutCompleted;
use App\Listeners\HandleStripeSubscriptionDeleted;
use App\Listeners\HandleStripeSubscriptionUpdated;
use App\Models\CheckoutSession;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookReceived;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('checkout completed webhook provisions user, tenant, membership, and subscription', function () {
    Event::fake([TenantProvisioned::class]);

    CheckoutSession::create([
        'session_id' => 'cs_test_123',
        'email' => 'new@example.com',
        'module_slugs' => ['crm', 'projects'],
        'seat_limit' => 10,
        'usage_quota' => 5000,
        'billing_interval' => BillingInterval::Monthly,
        'expires_at' => now()->addHours(24),
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_123',
                'subscription' => null,
            ],
        ],
    ];

    $listener = app(HandleStripeCheckoutCompleted::class);
    $listener->handle(new WebhookReceived($payload));

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->password)->toBeNull();

    $membership = $user->tenantMemberships()->first();
    expect($membership)->not->toBeNull();

    $tenant = $membership->tenant;
    expect($tenant->is_active)->toBeFalse();

    $subscription = $tenant->tenantSubscription;
    expect($subscription)->not->toBeNull();
    expect($subscription->module_slugs)->toBe(['crm', 'projects']);
    expect($subscription->seat_limit)->toBe(10);
    expect($subscription->usage_quota)->toBe(5000);

    expect(CheckoutSession::where('session_id', 'cs_test_123')->exists())->toBeFalse();

    Event::assertDispatched(TenantProvisioned::class);
});

test('checkout completed webhook is idempotent for duplicate emails', function () {
    Event::fake([TenantProvisioned::class]);

    User::factory()->create(['email' => 'existing@example.com']);

    CheckoutSession::create([
        'session_id' => 'cs_test_dup',
        'email' => 'existing@example.com',
        'module_slugs' => ['crm'],
        'seat_limit' => 5,
        'usage_quota' => 1000,
        'billing_interval' => BillingInterval::Monthly,
        'expires_at' => now()->addHours(24),
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_dup',
                'subscription' => null,
            ],
        ],
    ];

    $listener = app(HandleStripeCheckoutCompleted::class);
    $listener->handle(new WebhookReceived($payload));

    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
    expect(CheckoutSession::where('session_id', 'cs_test_dup')->exists())->toBeFalse();

    Event::assertNotDispatched(TenantProvisioned::class);
});

test('subscription updated webhook syncs status', function () {
    $subscription = TenantSubscription::factory()->create([
        'stripe_subscription_id' => 'sub_test_update',
        'status' => SubscriptionStatus::Trialing,
    ]);

    $periodEnd = now()->addMonth()->getTimestamp();

    $payload = [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_test_update',
                'status' => 'active',
                'current_period_end' => $periodEnd,
            ],
        ],
    ];

    $listener = app(HandleStripeSubscriptionUpdated::class);
    $listener->handle(new WebhookReceived($payload));

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->current_period_end)->not->toBeNull();
});

test('subscription deleted webhook sets read-only with end date', function () {
    $subscription = TenantSubscription::factory()->create([
        'stripe_subscription_id' => 'sub_test_delete',
        'status' => SubscriptionStatus::Active,
    ]);

    $payload = [
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_test_delete',
            ],
        ],
    ];

    $listener = new HandleStripeSubscriptionDeleted;
    $listener->handle(new WebhookReceived($payload));

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::ReadOnly);
    expect($subscription->read_only_ends_at)->not->toBeNull();
    expect((int) now()->startOfDay()->diffInDays($subscription->read_only_ends_at->startOfDay()))->toBe(config('billing.read_only_days'));
});

test('checkout completed listener ignores non-checkout events', function () {
    Event::fake([TenantProvisioned::class]);

    $payload = [
        'type' => 'invoice.paid',
        'data' => ['object' => ['id' => 'inv_123']],
    ];

    $listener = app(HandleStripeCheckoutCompleted::class);
    $listener->handle(new WebhookReceived($payload));

    Event::assertNotDispatched(TenantProvisioned::class);
});
