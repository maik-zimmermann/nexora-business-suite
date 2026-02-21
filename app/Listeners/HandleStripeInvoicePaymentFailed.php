<?php

namespace App\Listeners;

use App\Enums\SubscriptionStatus;
use App\Models\TenantSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Cashier\Events\WebhookReceived;

class HandleStripeInvoicePaymentFailed implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] !== 'invoice.payment_failed') {
            return;
        }

        $subscriptionId = $event->payload['data']['object']['subscription'] ?? null;

        if (! $subscriptionId) {
            return;
        }

        $tenantSubscription = TenantSubscription::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $tenantSubscription) {
            return;
        }

        $tenantSubscription->update([
            'status' => SubscriptionStatus::PastDue,
        ]);
    }
}
