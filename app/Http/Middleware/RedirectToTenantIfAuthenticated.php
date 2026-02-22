<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectToTenantIfAuthenticated
{
    /**
     * Redirect authenticated users to their tenant subdomain or the tenant picker.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $activeTenants = $user->tenantMemberships()
            ->whereHas('tenant', fn ($query) => $query->where('is_active', true))
            ->with('tenant')
            ->get();

        if ($activeTenants->isEmpty()) {
            return $next($request);
        }

        if ($activeTenants->count() === 1) {
            return Inertia::location(Tenancy::tenantUrl($activeTenants->first()->tenant, '/dashboard'));
        }

        return Inertia::location(route('tenants.show'));
    }
}
