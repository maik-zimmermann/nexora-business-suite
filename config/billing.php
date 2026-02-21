<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trial Period
    |--------------------------------------------------------------------------
    |
    | The number of days for the free trial period.
    |
    */

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Minimum Seats
    |--------------------------------------------------------------------------
    |
    | The minimum number of seats per subscription.
    |
    */

    'min_seats' => (int) env('BILLING_MIN_SEATS', 5),

    /*
    |--------------------------------------------------------------------------
    | Read-Only Period
    |--------------------------------------------------------------------------
    |
    | Days of read-only access after subscription cancellation before lockout.
    |
    */

    'read_only_days' => (int) env('BILLING_READ_ONLY_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Stripe Price IDs
    |--------------------------------------------------------------------------
    |
    | Price IDs used for per-seat billing and metered usage overage.
    |
    */

    'seat_monthly_price_id' => env('BILLING_SEAT_MONTHLY_PRICE_ID'),

    'seat_annual_price_id' => env('BILLING_SEAT_ANNUAL_PRICE_ID'),

    'usage_metered_price_id' => env('BILLING_USAGE_METERED_PRICE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Seat & Usage Overage Pricing (Cents)
    |--------------------------------------------------------------------------
    |
    | Unit amounts used when auto-provisioning Stripe prices for seat overage
    | and usage overage products.
    |
    */

    'seat_monthly_cents' => (int) env('BILLING_SEAT_MONTHLY_CENTS', 1500),

    'seat_annual_cents' => (int) env('BILLING_SEAT_ANNUAL_CENTS', 14400),

    'usage_overage_cents' => (int) env('BILLING_USAGE_OVERAGE_CENTS', 10),

];
