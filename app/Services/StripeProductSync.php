<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Module;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripeProductSync
{
    /**
     * Sync a module's Stripe product and prices.
     */
    public function sync(Module $module): void
    {
        if (! config('cashier.secret')) {
            return;
        }

        $stripe = Cashier::stripe();

        $product = $this->findOrCreateProduct(
            $stripe,
            "nexora_module_{$module->slug}",
            $module->name,
            $module->description,
        );

        $monthlyPriceId = $this->syncPrice(
            $stripe,
            $product->id,
            $module->stripe_monthly_price_id,
            $module->monthly_price_cents,
            'month',
        );

        $annualPriceId = $this->syncPrice(
            $stripe,
            $product->id,
            $module->stripe_annual_price_id,
            $module->annual_price_cents,
            'year',
        );

        $module->updateQuietly([
            'stripe_monthly_price_id' => $monthlyPriceId,
            'stripe_annual_price_id' => $annualPriceId,
        ]);
    }

    /**
     * Sync the seat overage Stripe product and prices.
     */
    public function syncSeatProduct(): void
    {
        if (! config('cashier.secret')) {
            return;
        }

        $stripe = Cashier::stripe();

        $product = $this->findOrCreateProduct(
            $stripe,
            'nexora_seat',
            'Additional Seat',
            'Per-seat overage charge for additional team members.',
        );

        $monthlyPriceId = $this->syncPrice(
            $stripe,
            $product->id,
            AppSetting::get('billing.seat_monthly_price_id'),
            config('billing.seat_monthly_cents'),
            'month',
        );

        $annualPriceId = $this->syncPrice(
            $stripe,
            $product->id,
            AppSetting::get('billing.seat_annual_price_id'),
            config('billing.seat_annual_cents'),
            'year',
        );

        AppSetting::set('billing.seat_monthly_price_id', $monthlyPriceId);
        AppSetting::set('billing.seat_annual_price_id', $annualPriceId);
    }

    /**
     * Sync the usage overage Stripe product and metered price.
     */
    public function syncUsageProduct(): void
    {
        if (! config('cashier.secret')) {
            return;
        }

        if (AppSetting::get('billing.usage_metered_price_id')) {
            return;
        }

        $stripe = Cashier::stripe();

        $product = $this->findOrCreateProduct(
            $stripe,
            'nexora_usage',
            'Usage Overage',
            'Metered usage overage charge.',
        );

        $meter = $this->findOrCreateMeter($stripe);

        $price = $stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => config('billing.usage_overage_cents'),
            'currency' => config('cashier.currency'),
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'meter' => $meter->id,
            ],
        ]);

        AppSetting::set('billing.usage_metered_price_id', $price->id);
        AppSetting::set('billing.usage_meter_id', $meter->id);
    }

    /**
     * Find or create a Stripe Billing Meter for usage overage tracking.
     */
    private function findOrCreateMeter(StripeClient $stripe): \Stripe\Billing\Meter
    {
        $existingMeterId = AppSetting::get('billing.usage_meter_id');

        if ($existingMeterId) {
            return $stripe->billing->meters->retrieve($existingMeterId);
        }

        $meters = $stripe->billing->meters->all(['limit' => 100]);

        foreach ($meters->data as $meter) {
            if ($meter->event_name === 'nexora_usage_overage') {
                return $meter;
            }
        }

        return $stripe->billing->meters->create([
            'display_name' => 'Nexora Usage Overage',
            'event_name' => 'nexora_usage_overage',
            'default_aggregation' => ['formula' => 'sum'],
            'customer_mapping' => [
                'type' => 'by_id',
                'event_payload_key' => 'stripe_customer_id',
            ],
        ]);
    }

    /**
     * Find an existing Stripe product by ID or create a new one.
     *
     * Uses deterministic product IDs to avoid Stripe Search API eventual consistency issues.
     */
    private function findOrCreateProduct(
        StripeClient $stripe,
        string $productId,
        string $name,
        ?string $description,
    ): \Stripe\Product {
        try {
            $product = $stripe->products->retrieve($productId);

            if ($product->name !== $name) {
                $product = $stripe->products->update($productId, [
                    'name' => $name,
                ]);
            }

            return $product;
        } catch (InvalidRequestException) {
            // Product does not exist yet — create it.
        }

        return $stripe->products->create([
            'id' => $productId,
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * Sync a recurring price, archiving the old one if the amount changed.
     */
    private function syncPrice(
        StripeClient $stripe,
        string $productId,
        ?string $existingPriceId,
        int $unitAmount,
        string $interval,
    ): string {
        if ($existingPriceId) {
            try {
                $existingPrice = $stripe->prices->retrieve($existingPriceId);

                if ($existingPrice->unit_amount === $unitAmount) {
                    return $existingPriceId;
                }

                $stripe->prices->update($existingPriceId, ['active' => false]);
            } catch (InvalidRequestException) {
                // Price no longer exists in Stripe — create a fresh one.
            }
        }

        $price = $stripe->prices->create([
            'product' => $productId,
            'unit_amount' => $unitAmount,
            'currency' => config('cashier.currency'),
            'recurring' => ['interval' => $interval],
        ]);

        return $price->id;
    }
}
