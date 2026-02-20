<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $baseDomain = parse_url(config('app.url'), PHP_URL_HOST);
        $subdomain = $this->extractSubdomain($host, $baseDomain);

        if ($subdomain !== null) {
            return $this->resolveBySubdomain($subdomain, $request, $next);
        }

        if ($request->hasHeader('X-Tenant-ID')) {
            return $this->resolveByHeader($request, $next);
        }

        return $next($request);
    }

    /**
     * Extract the subdomain from the host, or return null if none is present.
     */
    protected function extractSubdomain(string $host, ?string $baseDomain): ?string
    {
        if ($baseDomain === null || $host === $baseDomain) {
            return null;
        }

        $suffix = '.'.$baseDomain;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        if ($subdomain === '' || $subdomain === 'www') {
            return null;
        }

        return $subdomain;
    }

    /**
     * Resolve the tenant by subdomain slug.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    protected function resolveBySubdomain(string $subdomain, Request $request, Closure $next): Response
    {
        $tenant = Tenant::where('slug', $subdomain)->first();

        if ($tenant === null) {
            Log::warning('Tenant resolution failed', [
                'strategy' => 'subdomain',
                'slug' => $subdomain,
            ]);

            abort(404);
        }

        if (! $tenant->is_active) {
            Log::warning('Tenant resolution failed: tenant inactive', [
                'strategy' => 'subdomain',
                'slug' => $subdomain,
                'tenant_id' => $tenant->id,
            ]);

            abort(403);
        }

        app(Tenancy::class)->set($tenant);

        return $next($request);
    }

    /**
     * Resolve the tenant by X-Tenant-ID header with HMAC signature validation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    protected function resolveByHeader(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');
        $signature = $request->header('X-Tenant-Signature', '');
        $expected = hash_hmac('sha256', $tenantId, config('app.key'));

        if (! hash_equals($expected, $signature)) {
            Log::warning('Tenant resolution failed: invalid signature', [
                'strategy' => 'header',
                'tenant_id' => $tenantId,
            ]);

            abort(403);
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            Log::warning('Tenant resolution failed', [
                'strategy' => 'header',
                'tenant_id' => $tenantId,
            ]);

            abort(404);
        }

        if (! $tenant->is_active) {
            Log::warning('Tenant resolution failed: tenant inactive', [
                'strategy' => 'header',
                'tenant_id' => $tenantId,
            ]);

            abort(403);
        }

        app(Tenancy::class)->set($tenant);

        return $next($request);
    }
}
