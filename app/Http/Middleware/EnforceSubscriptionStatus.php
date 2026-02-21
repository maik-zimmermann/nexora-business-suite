<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSubscriptionStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenancy = app(Tenancy::class);

        if (! $tenancy->hasTenant()) {
            return $next($request);
        }

        $subscription = $tenancy->current()->tenantSubscription;

        if (! $subscription) {
            return $next($request);
        }

        if ($subscription->isLocked()) {
            abort(403, 'Your subscription has been locked. Please contact support.');
        }

        if ($subscription->isReadOnly()) {
            $request->attributes->set('subscription_read_only', true);
        }

        return $next($request);
    }
}
