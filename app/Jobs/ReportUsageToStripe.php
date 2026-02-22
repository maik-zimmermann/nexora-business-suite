<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\StripeUsageReporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReportUsageToStripe implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(StripeUsageReporter $reporter): void
    {
        $reporter->reportUsage($this->tenant);
    }
}
