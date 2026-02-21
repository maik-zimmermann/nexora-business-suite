<?php

namespace App\Listeners;

use App\Enums\SubscriptionStatus;
use App\Models\TenantSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Cashier\Events\WebhookReceived;

class HandleStripeSubscriptionDeleted implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] !== 'customer.subscription.deleted') {
            return;
        }

        $stripeId = $event->payload['data']['object']['id'] ?? null;

        if (! $stripeId) {
            return;
        }

        $tenantSubscription = TenantSubscription::where('stripe_subscription_id', $stripeId)->first();

        if (! $tenantSubscription) {
            return;
        }

        $tenantSubscription->update([
            'status' => SubscriptionStatus::ReadOnly,
            'read_only_ends_at' => now()->addDays(config('billing.read_only_days')),
        ]);
    }
}
