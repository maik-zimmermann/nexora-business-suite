<?php

namespace App\Services;

use App\Enums\BillingInterval;
use App\Models\CheckoutSession;
use App\Models\Module;
use Laravel\Cashier\Cashier;

class CheckoutSessionBuilder
{
    /**
     * Build a Stripe Checkout session and persist a local CheckoutSession record.
     *
     * @param  array<int, string>  $moduleSlugs
     */
    public function build(
        string $email,
        array $moduleSlugs,
        int $seatLimit,
        int $usageQuota,
        BillingInterval $billingInterval,
    ): string {
        $modules = Module::query()
            ->where('is_active', true)
            ->whereIn('slug', $moduleSlugs)
            ->get();

        $lineItems = [];

        foreach ($modules as $module) {
            $priceId = $billingInterval === BillingInterval::Annual
                ? $module->stripe_annual_price_id
                : $module->stripe_monthly_price_id;

            $lineItems[] = [
                'price' => $priceId,
                'quantity' => 1,
            ];
        }

        $seatPriceId = $billingInterval === BillingInterval::Annual
            ? config('billing.seat_annual_price_id')
            : config('billing.seat_monthly_price_id');

        if ($seatPriceId) {
            $lineItems[] = [
                'price' => $seatPriceId,
                'quantity' => $seatLimit,
            ];
        }

        $sessionParams = [
            'mode' => 'subscription',
            'customer_email' => $email,
            'line_items' => $lineItems,
            'subscription_data' => [
                'trial_period_days' => config('billing.trial_days'),
                'metadata' => [
                    'seat_limit' => $seatLimit,
                    'usage_quota' => $usageQuota,
                    'module_slugs' => implode(',', $moduleSlugs),
                    'billing_interval' => $billingInterval->value,
                ],
            ],
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancelled'),
        ];

        $stripeClient = Cashier::stripe();
        $session = $stripeClient->checkout->sessions->create($sessionParams);

        CheckoutSession::create([
            'session_id' => $session->id,
            'email' => $email,
            'module_slugs' => $moduleSlugs,
            'seat_limit' => $seatLimit,
            'usage_quota' => $usageQuota,
            'billing_interval' => $billingInterval,
            'expires_at' => now()->addHours(24),
        ]);

        return $session->url;
    }
}
