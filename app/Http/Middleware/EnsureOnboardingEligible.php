<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingEligible
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasValidSignature()) {
            $routeUser = $request->route('user');
            $userId = $routeUser instanceof \App\Models\User ? $routeUser->id : $routeUser;

            if ($userId) {
                Auth::loginUsingId($userId);
            }
        } elseif (! Auth::check()) {
            abort(403);
        }

        $user = Auth::user();

        if ($user && $user->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
