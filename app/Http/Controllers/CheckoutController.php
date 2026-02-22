<?php

namespace App\Http\Controllers;

use App\Enums\BillingInterval;
use App\Http\Requests\CheckoutInitiateRequest;
use App\Models\Module;
use App\Services\CheckoutSessionBuilder;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    /**
     * Display the plan builder page.
     */
    public function index(): Response
    {
        return Inertia::render('checkout/PlanBuilder', [
            'modules' => Module::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'billingIntervals' => array_column(BillingInterval::cases(), 'value'),
            'minSeats' => config('billing.min_seats'),
            'seatOverageMonthlyCents' => config('billing.seat_monthly_cents'),
            'seatOverageAnnualCents' => config('billing.seat_annual_cents'),
            'usageIncludedQuota' => config('billing.usage_included_quota'),
            'usageOverageCents' => config('billing.usage_overage_cents'),
        ]);
    }

    /**
     * Create a Stripe Checkout session and redirect.
     */
    public function store(CheckoutInitiateRequest $request, CheckoutSessionBuilder $builder): SymfonyResponse
    {
        $validated = $request->validated();

        $url = $builder->build(
            email: $validated['email'],
            moduleSlugs: $validated['module_slugs'],
            billingInterval: BillingInterval::from($validated['billing_interval']),
        );

        return Inertia::location($url);
    }

    /**
     * Display the checkout success page.
     */
    public function success(): Response
    {
        return Inertia::render('checkout/Success');
    }

    /**
     * Display the checkout cancelled page.
     */
    public function cancelled(): Response
    {
        return Inertia::render('checkout/Cancelled');
    }
}
