<?php

namespace App\Observers;

use App\Models\TenantMembership;
use App\Services\SeatTracker;

class TenantMembershipObserver
{
    public function __construct(
        private SeatTracker $seatTracker,
    ) {}

    /**
     * Handle the TenantMembership "created" event.
     */
    public function created(TenantMembership $tenantMembership): void
    {
        $this->seatTracker->record($tenantMembership->tenant);
    }

    /**
     * Handle the TenantMembership "deleted" event.
     */
    public function deleted(TenantMembership $tenantMembership): void
    {
        $this->seatTracker->record($tenantMembership->tenant);
    }
}
