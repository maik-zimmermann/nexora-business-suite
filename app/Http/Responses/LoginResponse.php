<?php

namespace App\Http\Responses;

use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 200);
        }

        $tenancy = app(Tenancy::class);

        if ($tenancy->hasTenant()) {
            return redirect()->intended('/dashboard');
        }

        $user = $request->user();

        $activeTenants = $user->tenantMemberships()
            ->whereHas('tenant', fn ($query) => $query->where('is_active', true))
            ->with('tenant')
            ->get();

        if ($activeTenants->count() === 1) {
            return redirect()->intended(
                Tenancy::tenantUrl($activeTenants->first()->tenant, '/dashboard')
            );
        }

        if ($activeTenants->count() > 1) {
            return redirect()->route('tenants.show');
        }

        return redirect('/');
    }
}
