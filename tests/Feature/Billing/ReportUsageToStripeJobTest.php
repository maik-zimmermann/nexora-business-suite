<?php

use App\Jobs\ReportUsageToStripe;
use App\Models\Tenant;
use App\Services\StripeUsageReporter;
use Mockery\MockInterface;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('job calls StripeUsageReporter::reportUsage for the given tenant', function () {
    $tenant = Tenant::factory()->create();

    $mock = $this->mock(StripeUsageReporter::class, function (MockInterface $mock) use ($tenant) {
        $mock->shouldReceive('reportUsage')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $tenant->id));
    });

    $job = new ReportUsageToStripe($tenant);
    $job->handle($mock);
});
