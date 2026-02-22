<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureRootDomain
{
    /**
     * Redirect to the root domain if the request is on a tenant subdomain.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app(Tenancy::class)->hasTenant()) {
            return Inertia::location(rtrim(config('app.url'), '/').$request->getRequestUri());
        }

        return $next($request);
    }
}
