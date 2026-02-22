<?php

namespace App\Http\Controllers;

use App\Support\Tenancy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantPickerController extends Controller
{
    /**
     * Display the tenant picker page.
     */
    public function show(Request $request): Response
    {
        $tenants = $request->user()
            ->tenantMemberships()
            ->whereHas('tenant', fn ($query) => $query->where('is_active', true))
            ->with('tenant')
            ->get()
            ->map(fn ($membership) => [
                'name' => $membership->tenant->name,
                'slug' => $membership->tenant->slug,
                'url' => Tenancy::tenantUrl($membership->tenant, '/dashboard'),
            ]);

        return Inertia::render('TenantPicker', [
            'tenants' => $tenants,
        ]);
    }
}
