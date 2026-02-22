<?php

namespace App\Services;

use App\Enums\UsageType;
use App\Jobs\ReportUsageToStripe;
use App\Models\Tenant;
use App\Models\UsageRecord;

class UsageTracker
{
    /**
     * Record usage for a tenant.
     */
    public function record(Tenant $tenant, UsageType $type, int $quantity = 1): void
    {
        UsageRecord::create([
            'tenant_id' => $tenant->id,
            'type' => $type,
            'quantity' => $quantity,
            'recorded_at' => now(),
        ]);

        ReportUsageToStripe::dispatch($tenant);
    }

    /**
     * Get the total usage for the current billing period.
     */
    public function currentPeriodUsage(Tenant $tenant): int
    {
        $subscription = $tenant->tenantSubscription;

        if (! $subscription) {
            return 0;
        }

        $periodStart = $subscription->current_period_end
            ? $subscription->current_period_end->subMonth()
            : now()->subMonth();

        return (int) $tenant->usageRecords()
            ->where('recorded_at', '>=', $periodStart)
            ->sum('quantity');
    }

    /**
     * Get the remaining usage quota.
     */
    public function remainingQuota(Tenant $tenant): int
    {
        $subscription = $tenant->tenantSubscription;

        if (! $subscription) {
            return 0;
        }

        return max(0, $subscription->usage_quota - $this->currentPeriodUsage($tenant));
    }
}
