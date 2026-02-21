<?php

namespace App\Listeners;

use App\Events\TenantProvisioned;
use App\Notifications\TenantActivationEmail;

class SendTenantActivationEmail
{
    /**
     * Handle the event.
     */
    public function handle(TenantProvisioned $event): void
    {
        $event->user->notify(new TenantActivationEmail($event->user, $event->tenant));
    }
}
