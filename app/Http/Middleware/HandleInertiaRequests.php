<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'tenant' => function () {
                $tenant = app(Tenancy::class)->get();

                if ($tenant === null) {
                    return null;
                }

                $parsed = parse_url(config('app.url'));
                $scheme = $parsed['scheme'] ?? 'https';
                $host = $parsed['host'] ?? 'localhost';
                $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

                return [
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                    'baseUrl' => "{$scheme}://{$tenant->slug}.{$host}{$port}",
                ];
            },
            'subscription' => function () {
                $tenant = app(Tenancy::class)->get();

                if ($tenant === null) {
                    return null;
                }

                $sub = $tenant->tenantSubscription;

                if (! $sub) {
                    return null;
                }

                return [
                    'status' => $sub->status->value,
                    'seat_limit' => $sub->seat_limit,
                    'current_seat_count' => $tenant->currentSeatCount(),
                    'usage_quota' => $sub->usage_quota,
                    'current_usage' => $sub->currentUsage(),
                    'current_period_end' => $sub->current_period_end?->toISOString(),
                ];
            },
            'subscriptionReadOnly' => fn () => $request->attributes->get('subscription_read_only', false),
        ];
    }
}
