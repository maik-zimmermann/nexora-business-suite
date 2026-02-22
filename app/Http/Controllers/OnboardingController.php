<?php

namespace App\Http\Controllers;

use App\Http\Requests\OnboardingRequest;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class OnboardingController extends Controller
{
    /**
     * Display the onboarding setup page.
     */
    public function show(User $user): InertiaResponse
    {
        return Inertia::render('onboarding/Setup', [
            'email' => $user->email,
            'user' => ['id' => $user->id],
        ]);
    }

    /**
     * Complete the onboarding process.
     */
    public function store(OnboardingRequest $request, User $user): Response
    {
        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
        ]);

        $tenant = $user->tenantMemberships()->first()?->tenant;

        if ($tenant) {
            $tenant->update([
                'name' => $validated['organisation_name'],
                'slug' => $validated['slug'],
                'is_active' => true,
            ]);
        }

        Auth::login($user);

        if ($tenant) {
            return Inertia::location(Tenancy::tenantUrl($tenant, '/dashboard'));
        }

        return redirect('/');
    }
}
