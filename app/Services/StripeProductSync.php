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
     * Sync the seat overage Stripe product with graduated tiered metered prices.
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
            'Per-seat graduated tiered charge for team members.',
        );

        $freeUpTo = (int) config('billing.min_seats');

        $monthlyPriceId = $this->syncTieredMeteredPrice(
            $stripe,
            $product->id,
            AppSetting::get('billing.seat_monthly_price_id'),
            $freeUpTo,
            config('billing.seat_monthly_cents'),
            'month',
        );

        $annualPriceId = $this->syncTieredMeteredPrice(
            $stripe,
            $product->id,
            AppSetting::get('billing.seat_annual_price_id'),
            $freeUpTo,
            config('billing.seat_annual_cents'),
            'year',
        );

        AppSetting::set('billing.seat_monthly_price_id', $monthlyPriceId);
        AppSetting::set('billing.seat_annual_price_id', $annualPriceId);
    }

    /**
     * Sync the usage overage Stripe product with graduated tiered metered price.
     */
    public function syncUsageProduct(): void
    {
        if (! config('cashier.secret')) {
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

        $freeUpTo = (int) config('billing.usage_included_quota');

        $priceId = $this->syncTieredMeteredPrice(
            $stripe,
            $product->id,
            AppSetting::get('billing.usage_metered_price_id'),
            $freeUpTo,
            config('billing.usage_overage_cents'),
            'month',
            $meter->id,
        );

        AppSetting::set('billing.usage_metered_price_id', $priceId);
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

    /**
     * Sync a graduated tiered metered price, archiving the old one if the tiers changed.
     */
    private function syncTieredMeteredPrice(
        StripeClient $stripe,
        string $productId,
        ?string $existingPriceId,
        int $freeUpTo,
        int $overageAmountCents,
        string $interval,
        ?string $meterId = null,
    ): string {
        if ($existingPriceId) {
            try {
                $existingPrice = $stripe->prices->retrieve($existingPriceId, ['expand' => ['tiers']]);

                if ($this->tieredPriceMatches($existingPrice, $freeUpTo, $overageAmountCents)) {
                    return $existingPriceId;
                }

                $stripe->prices->update($existingPriceId, ['active' => false]);
            } catch (InvalidRequestException) {
                // Price no longer exists in Stripe — create a fresh one.
            }
        }

        $recurring = [
            'interval' => $interval,
            'usage_type' => 'metered',
        ];

        if ($meterId) {
            $recurring['meter'] = $meterId;
        }

        $price = $stripe->prices->create([
            'product' => $productId,
            'currency' => config('cashier.currency'),
            'billing_scheme' => 'tiered',
            'tiers_mode' => 'graduated',
            'tiers' => [
                [
                    'up_to' => $freeUpTo,
                    'unit_amount' => 0,
                ],
                [
                    'up_to' => 'inf',
                    'unit_amount' => $overageAmountCents,
                ],
            ],
            'recurring' => $recurring,
        ]);

        return $price->id;
    }

    /**
     * Check if an existing tiered price matches the expected tier configuration.
     */
    private function tieredPriceMatches(
        \Stripe\Price $price,
        int $freeUpTo,
        int $overageAmountCents,
    ): bool {
        if ($price->billing_scheme !== 'tiered' || $price->tiers_mode !== 'graduated') {
            return false;
        }

        $tiers = $price->tiers ?? [];

        if (count($tiers) !== 2) {
            return false;
        }

        return $tiers[0]->up_to === $freeUpTo
            && $tiers[0]->unit_amount === 0
            && $tiers[1]->unit_amount === $overageAmountCents;
    }
}
