<?php

namespace App\Listeners;

use App\Enums\SubscriptionStatus;
use App\Models\TenantSubscription;
use App\Services\StripeUsageReporter;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Cashier\Events\WebhookReceived;

class HandleStripeSubscriptionUpdated implements ShouldQueue
{
    public function __construct(
        private StripeUsageReporter $usageReporter,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] !== 'customer.subscription.updated') {
            return;
        }

        $stripeSubscription = $event->payload['data']['object'];
        $stripeId = $stripeSubscription['id'] ?? null;

        if (! $stripeId) {
            return;
        }

        $tenantSubscription = TenantSubscription::where('stripe_subscription_id', $stripeId)->first();

        if (! $tenantSubscription) {
            return;
        }

        $previousPeriodEnd = $tenantSubscription->current_period_end;

        $statusMap = [
            'active' => SubscriptionStatus::Active,
            'trialing' => SubscriptionStatus::Trialing,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Cancelled,
        ];

        $stripeStatus = $stripeSubscription['status'] ?? null;

        if (isset($statusMap[$stripeStatus])) {
            $tenantSubscription->status = $statusMap[$stripeStatus];
        }

        if (isset($stripeSubscription['current_period_end'])) {
            $tenantSubscription->current_period_end = Carbon::createFromTimestamp(
                $stripeSubscription['current_period_end']
            );
        }

        if (isset($stripeSubscription['trial_end'])) {
            $tenantSubscription->trial_ends_at = Carbon::createFromTimestamp(
                $stripeSubscription['trial_end']
            );
        }

        $tenantSubscription->save();

        $newPeriodEnd = $tenantSubscription->current_period_end;
        $periodRolledOver = $newPeriodEnd
            && (! $previousPeriodEnd || ! $newPeriodEnd->equalTo($previousPeriodEnd));

        if ($periodRolledOver && $tenantSubscription->isActive()) {
            $this->usageReporter->reportSeats($tenantSubscription->tenant);
        }
    }
}
