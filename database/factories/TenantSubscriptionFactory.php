<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TenantSubscription>
 */
class TenantSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stripe_subscription_id' => 'sub_'.fake()->unique()->bothify('??????????????'),
            'status' => SubscriptionStatus::Active,
            'billing_interval' => BillingInterval::Monthly,
            'module_slugs' => ['crm'],
            'seat_limit' => 5,
            'usage_quota' => 1000,
            'current_period_end' => now()->addMonth(),
        ];
    }

    /**
     * Indicate that the subscription is trialing.
     */
    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(config('billing.trial_days')),
        ]);
    }

    /**
     * Indicate that the subscription is past due.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::PastDue,
        ]);
    }

    /**
     * Indicate that the subscription is read-only.
     */
    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::ReadOnly,
            'read_only_ends_at' => now()->addDays(config('billing.read_only_days')),
        ]);
    }

    /**
     * Indicate that the subscription is locked.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Locked,
        ]);
    }
}
