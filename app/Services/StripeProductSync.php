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
            'module_slug',
            $module->slug,
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
            'nexora_product',
            'seat',
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
            'nexora_product',
            'usage',
            'Usage Overage',
            'Metered usage overage charge.',
        );

        $price = $stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => config('billing.usage_overage_cents'),
            'currency' => 'usd',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'aggregate_usage' => 'sum',
            ],
        ]);

        AppSetting::set('billing.usage_metered_price_id', $price->id);
    }

    /**
     * Find an existing Stripe product by metadata or create a new one.
     */
    private function findOrCreateProduct(
        StripeClient $stripe,
        string $metadataKey,
        string $metadataValue,
        string $name,
        ?string $description,
    ): \Stripe\Product {
        $results = $stripe->products->search([
            'query' => "metadata['{$metadataKey}']:'{$metadataValue}'",
        ]);

        if ($results->data) {
            $product = $results->data[0];

            if ($product->name !== $name) {
                $product = $stripe->products->update($product->id, [
                    'name' => $name,
                ]);
            }

            return $product;
        }

        return $stripe->products->create([
            'name' => $name,
            'description' => $description,
            'metadata' => [$metadataKey => $metadataValue],
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
            'currency' => 'usd',
            'recurring' => ['interval' => $interval],
        ]);

        return $price->id;
    }
}
