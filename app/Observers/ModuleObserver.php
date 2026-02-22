<?php

namespace App\Observers;

use App\Models\Module;
use App\Services\StripeProductSync;

class ModuleObserver
{
    public function __construct(private StripeProductSync $stripeProductSync) {}

    /**
     * Handle the Module "created" event.
     */
    public function created(Module $module): void
    {
        if (! config('cashier.secret')) {
            return;
        }

        $this->stripeProductSync->sync($module);
    }

    /**
     * Handle the Module "updated" event.
     */
    public function updated(Module $module): void
    {
        if (! config('cashier.secret')) {
            return;
        }

        if (! $module->wasChanged(['name', 'description', 'monthly_price_cents', 'annual_price_cents'])) {
            return;
        }

        $this->stripeProductSync->sync($module);
    }
}
