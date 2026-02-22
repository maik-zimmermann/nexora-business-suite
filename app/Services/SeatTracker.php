<?php

namespace App\Services;

use App\Enums\BillingInterval;
use App\Models\SeatSnapshot;
use App\Models\Tenant;

class SeatTracker
{
    /**
     * Record the current seat count for a tenant.
     */
    public function record(Tenant $tenant): void
    {
        SeatSnapshot::create([
            'tenant_id' => $tenant->id,
            'seat_count' => $tenant->currentSeatCount(),
            'recorded_at' => now(),
        ]);
    }

    /**
     * Get the peak seat count for the current billing period.
     */
    public function peakSeatCount(Tenant $tenant): int
    {
        $subscription = $tenant->tenantSubscription;

        if (! $subscription || ! $subscription->current_period_end) {
            return $tenant->currentSeatCount();
        }

        $periodStart = $subscription->billing_interval === BillingInterval::Annual
            ? $subscription->current_period_end->subYear()
            : $subscription->current_period_end->subMonth();

        $peak = SeatSnapshot::where('tenant_id', $tenant->id)
            ->where('recorded_at', '>=', $periodStart)
            ->max('seat_count');

        return $peak ? (int) $peak : $tenant->currentSeatCount();
    }
}
