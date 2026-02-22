<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\StripeProductSync;
use Illuminate\Console\Command;

class SyncModulesToStripe extends Command
{
    /**
     * @var string
     */
    protected $signature = 'modules:sync-stripe';

    /**
     * @var string
     */
    protected $description = 'Sync all modules, seat, and usage products to Stripe.';

    /**
     * Execute the console command.
     */
    public function handle(StripeProductSync $sync): int
    {
        if (! config('cashier.secret')) {
            $this->warn('Stripe is not configured — skipping sync.');

            return self::SUCCESS;
        }

        $this->info('Syncing seat product...');
        $sync->syncSeatProduct();

        $this->info('Syncing usage product...');
        $sync->syncUsageProduct();

        $modules = Module::all();

        foreach ($modules as $module) {
            $this->info("Syncing module: {$module->name}");
            $sync->sync($module);
        }

        $this->info("Done. Synced {$modules->count()} module(s).");

        return self::SUCCESS;
    }
}
