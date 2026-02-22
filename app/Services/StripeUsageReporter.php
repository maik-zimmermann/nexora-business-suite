<?php

namespace App\Services;

use App\Models\Tenant;
use Laravel\Cashier\Cashier;

class StripeUsageReporter
{
    public function __construct(
        private SeatTracker $seatTracker,
        private UsageTracker $usageTracker,
    ) {}

    /**
     * Report the peak seat count to Stripe for a tenant.
     */
    public function reportSeats(Tenant $tenant): void
    {
        if (! config('cashier.secret')) {
            return;
        }

        $subscription = $tenant->tenantSubscription;

        if (! $subscription || ! $subscription->isActive()) {
            return;
        }

        if (! $subscription->seat_stripe_price_id || ! $subscription->stripe_subscription_id) {
            return;
        }

        $subscriptionItemId = $this->findSubscriptionItemId(
            $subscription->stripe_subscription_id,
            $subscription->seat_stripe_price_id,
        );

        if (! $subscriptionItemId) {
            return;
        }

        $peakSeats = $this->seatTracker->peakSeatCount($tenant);

        Cashier::stripe()->subscriptionItems->createUsageRecord(
            $subscriptionItemId,
            [
                'quantity' => $peakSeats,
                'action' => 'set',
                'timestamp' => now()->getTimestamp(),
            ],
        );
    }

    /**
     * Report the current usage quota consumption to Stripe for a tenant.
     */
    public function reportUsage(Tenant $tenant): void
    {
        if (! config('cashier.secret')) {
            return;
        }

        $subscription = $tenant->tenantSubscription;

        if (! $subscription || ! $subscription->isActive()) {
            return;
        }

        if (! $subscription->usage_stripe_price_id || ! $subscription->stripe_subscription_id) {
            return;
        }

        $subscriptionItemId = $this->findSubscriptionItemId(
            $subscription->stripe_subscription_id,
            $subscription->usage_stripe_price_id,
        );

        if (! $subscriptionItemId) {
            return;
        }

        $currentUsage = $this->usageTracker->currentPeriodUsage($tenant);

        Cashier::stripe()->subscriptionItems->createUsageRecord(
            $subscriptionItemId,
            [
                'quantity' => $currentUsage,
                'action' => 'set',
                'timestamp' => now()->getTimestamp(),
            ],
        );
    }

    /**
     * Find the Stripe subscription item ID that matches the given price ID.
     */
    private function findSubscriptionItemId(string $stripeSubscriptionId, string $priceId): ?string
    {
        $stripe = Cashier::stripe();
        $subscription = $stripe->subscriptions->retrieve($stripeSubscriptionId, [
            'expand' => ['items'],
        ]);

        foreach ($subscription->items->data as $item) {
            if ($item->price->id === $priceId) {
                return $item->id;
            }
        }

        return null;
    }
}
