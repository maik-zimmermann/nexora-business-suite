<?php

namespace App\Listeners;

use App\Services\TenantProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Cashier\Events\WebhookReceived;

class HandleStripeCheckoutCompleted implements ShouldQueue
{
    public function __construct(
        private TenantProvisioningService $provisioningService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] !== 'checkout.session.completed') {
            return;
        }

        $this->provisioningService->provision($event->payload);
    }
}
