<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\TenantSubscription;
use Illuminate\Console\Command;

class UpdateSubscriptionStatuses extends Command
{
    /**
     * @var string
     */
    protected $signature = 'subscription:update-statuses';

    /**
     * @var string
     */
    protected $description = 'Move expired read-only subscriptions to locked status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = TenantSubscription::query()
            ->where('status', SubscriptionStatus::ReadOnly)
            ->whereNotNull('read_only_ends_at')
            ->where('read_only_ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Locked]);

        $this->info("Updated {$count} subscription(s) from read-only to locked.");

        return self::SUCCESS;
    }
}
