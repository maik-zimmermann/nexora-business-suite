# Plan: Metered Graduated Tiered Pricing for Seats and Usage Quota

## Context

Currently, seat and usage quota prices in Stripe are flat per-unit prices, and both seat count and usage quota are user-configurable in the plan builder form. The new model removes admin configurability for both — they become system-level values — and switches both Stripe prices to graduated tiered metered prices. This means the minimum quantity is always free (Tier 1), and any usage beyond it is billed at a system-defined rate. Additionally, both seat count and usage quota consumption must be actively reported to Stripe via the metered usage API.

---

## Phase 1: Database — Seat Count Tracking

### 1. Migration — create `seat_snapshots` table

New table to record every seat count change so the billing period peak can be queried.

| Column | Type |
|---|---|
| `id` | bigint, PK |
| `tenant_id` | uuid, FK → tenants.id, cascade delete, indexed |
| `seat_count` | unsignedSmallInt |
| `recorded_at` | timestamp, indexed |
| timestamps | |

### 2. New Model — `SeatSnapshot`

`app/Models/SeatSnapshot.php` via `artisan make:model SeatSnapshot`

- `$fillable = ['tenant_id', 'seat_count', 'recorded_at']`
- Cast `recorded_at` → datetime
- Belongs to `Tenant`

### 3. New Service — `SeatTracker`

`app/Services/SeatTracker.php` via `artisan make:class app/Services/SeatTracker`

**Methods:**
- `record(Tenant $tenant): void` — snapshots `$tenant->currentSeatCount()` into `seat_snapshots` with `recorded_at: now()`
- `peakSeatCount(Tenant $tenant): int` — queries max `seat_count` from `seat_snapshots` within the current billing period (from `current_period_end - interval` to now, matching the billing interval). Falls back to `$tenant->currentSeatCount()` if no snapshots exist.

### 4. Trigger snapshots on membership changes

Add a `TenantMembershipObserver` that calls `SeatTracker::record()` on `created` and `deleted` events. Register it in `AppServiceProvider` alongside `ModuleObserver`.

`app/Observers/TenantMembershipObserver.php` via `artisan make:observer TenantMembershipObserver --model=TenantMembership`

---

## Phase 2: Stripe Provisioning — Graduated Tiered Prices

### 5. Update `StripeProductSync::syncSeatProduct()`

`app/Services/StripeProductSync.php`

Replace the current flat `syncPrice()` call for seat prices with a new private `syncTieredMeteredPrice()` helper.

**New private helper — `syncTieredMeteredPrice()`**

Parameters: `StripeClient`, `productId`, `existingPriceId`, `freeUpTo` (int), `overageAmountCents` (int), `interval` (`month`/`year`).

Logic:
1. If `existingPriceId` exists: retrieve it. If `billing_scheme === 'tiered'` and tiers match, return existing ID (no-op).
2. Otherwise archive the old price (`active: false`) and create a new one.
3. New price params:
   - `billing_scheme: tiered`, `tiers_mode: graduated`
   - `usage_type: metered`, `aggregate_usage: sum`
   - `recurring: {interval}`
   - `tiers: [{up_to: $freeUpTo, unit_amount: 0}, {up_to: 'inf', unit_amount: $overageAmountCents}]`
4. Return new price ID.

**`syncSeatProduct()` changes:**
- Use `syncTieredMeteredPrice()` for both monthly and annual prices, passing `config('billing.min_seats')` as `freeUpTo` and `config('billing.seat_*_cents')` as `overageAmountCents`.
- Store resulting IDs in `AppSetting` as before.

### 6. Update `StripeProductSync::syncUsageProduct()`

`app/Services/StripeProductSync.php`

Replace the current flat metered price creation with `syncTieredMeteredPrice()`.

- `freeUpTo` = `config('billing.usage_included_quota')` (new config key, see Phase 3).
- `overageAmountCents` = `config('billing.usage_overage_cents')`.
- Remove the early-return guard (`if AppSetting::get(...) return`) — the method should now behave like `syncSeatProduct()` (check existing price, only recreate if tiers changed).
- The Stripe Billing Meter logic (`findOrCreateMeter`) remains unchanged.

---

## Phase 3: Configuration

### 7. Update `config/billing.php`

Add:
- `'usage_included_quota' => (int) env('BILLING_USAGE_INCLUDED_QUOTA', 1000)` — the free tier ceiling for usage quota.

Update `.env.example` with `BILLING_USAGE_INCLUDED_QUOTA=1000`.

---

## Phase 4: Metered Usage Reporting to Stripe

### 8. New Service — `StripeUsageReporter`

`app/Services/StripeUsageReporter.php` via `artisan make:class app/Services/StripeUsageReporter`

Injected dependencies: `SeatTracker`, `UsageTracker`.

**`reportSeats(Tenant $tenant): void`**
1. Guard: `if (! config('cashier.secret')) return`.
2. Load `TenantSubscription`. Skip if not active/trialing, or if `seat_stripe_price_id` is null.
3. Retrieve the Stripe subscription item ID for the seat price.
4. Get peak seat count via `SeatTracker::peakSeatCount($tenant)`.
5. Create a Stripe usage record on that subscription item: `action: set`, `quantity: peakSeatCount`, `timestamp: now()`.

**`reportUsage(Tenant $tenant): void`**
1. Guard: `if (! config('cashier.secret')) return`.
2. Load `TenantSubscription`. Skip if not active/trialing, or if `usage_stripe_price_id` is null.
3. Retrieve the Stripe subscription item ID for the usage price.
4. Get current period usage via `UsageTracker::currentPeriodUsage($tenant)`.
5. Create a Stripe usage record: `action: set`, `quantity: currentPeriodUsage`, `timestamp: now()`.

Both methods find the correct Stripe subscription item by matching `price.id` on the subscription's `items.data` array, using the stored `seat_stripe_price_id` / `usage_stripe_price_id` on `TenantSubscription`.

### 9. Report usage on each usage event

Update `UsageTracker::record()` to dispatch a queued job after recording locally.

New queued job: `app/Jobs/ReportUsageToStripe.php` via `artisan make:job ReportUsageToStripe`

- Receives `Tenant $tenant`.
- Calls `StripeUsageReporter::reportUsage($tenant)`.
- Implements `ShouldQueue`.

`UsageTracker::record()` dispatches `ReportUsageToStripe::dispatch($tenant)` after creating the `UsageRecord`.

### 10. Report seats on billing period renewal

Listen for the `customer.subscription.updated` Stripe webhook to detect period renewal and trigger seat reporting.

Update `HandleStripeSubscriptionUpdated` to also call `StripeUsageReporter::reportSeats($tenant)` when a new `current_period_end` is received (i.e. the period rolled over). The tenant is resolved via `TenantSubscription::where('stripe_subscription_id', $stripeId)->first()->tenant`.

---

## Phase 5: Checkout & Plan Builder Changes

### 11. Remove `usage_quota` from checkout form

**`CheckoutInitiateRequest`** — remove `usage_quota` validation rule.

**`CheckoutSessionBuilder`** — remove `$usageQuota` parameter. Pass `config('billing.usage_included_quota')` as the `usage_quota` value when creating the `CheckoutSession` and Stripe subscription metadata.

**`CheckoutController::store()`** — stop passing `usage_quota` from request to builder.

**`CheckoutSession` model/migration** — `usage_quota` column can stay (it still gets populated by the system default). No migration needed.

**`TenantProvisioningService`** — `usage_quota` is read from `CheckoutSession` as before; no change needed since it's still stored there.

### 12. Remove `seat_limit` and `usage_quota` inputs from `PlanBuilder.vue`

`resources/js/pages/checkout/PlanBuilder.vue`

- Remove the `seat_limit` number input and its label. Remove `form.seat_limit` from the form (it no longer needs to be submitted).
- Remove the `usage_quota` number input and its label.
- Remove the `seatMonthlyCents`, `seatAnnualCents` props and all seat pricing calculations (`extraSeats`, `seatPriceCents`, `seatsTotal`).
- Replace the seat section in the summary with an informational block: "Up to N seats included free. Additional seats billed at £X/seat/month (or £Y/seat/year) — billed based on your peak seat count."
- Replace the usage quota section with an informational block: "Up to N,000 API calls/month included free. Additional usage billed at £Z per 1,000 calls."
- Total shown in summary becomes modules-only; seat and usage overages are billed separately as metered.

### 13. Update `CheckoutController::index()` props

Stop passing `minimumSeats`, `seatMonthlyCents`, `seatAnnualCents` to the plan builder page (no longer needed for form inputs). Add informational props for the display-only blocks:
- `minSeats: config('billing.min_seats')`
- `seatOverageMonthlyCents: config('billing.seat_monthly_cents')`
- `seatOverageAnnualCents: config('billing.seat_annual_cents')`
- `usageIncludedQuota: config('billing.usage_included_quota')`
- `usageOverageCents: config('billing.usage_overage_cents')`

### 14. Remove `seat_limit` from `CheckoutInitiateRequest`

Since `seat_limit` is now system-defined (all new subscriptions start at `config('billing.min_seats')`), remove it from the request validation. `CheckoutSessionBuilder` uses `config('billing.min_seats')` directly.

> **Note:** This is a significant UX change — users can no longer choose their seat count at checkout. They start with the minimum and seat overages are billed automatically as they add members. Confirm this is the intended behaviour before implementing.

---

## Phase 6: Tests

All files created via `artisan make:test --pest`.

### `tests/Feature/Billing/SeatTrackerTest.php`
- `record()` creates a `SeatSnapshot` with current seat count and `recorded_at: now()`
- `peakSeatCount()` returns max from snapshots within billing period
- `peakSeatCount()` returns current seat count when no snapshots exist

### `tests/Feature/Billing/TenantMembershipObserverTest.php`
- Adding a membership creates a `SeatSnapshot`
- Removing a membership creates a `SeatSnapshot`

### `tests/Feature/Billing/StripeProductSyncTieredTest.php`
- `syncSeatProduct()` with no Stripe key is a no-op
- `syncUsageProduct()` with no Stripe key is a no-op
- (Mock Stripe client) `syncSeatProduct()` creates a price with `billing_scheme: tiered`, `tiers_mode: graduated`, `usage_type: metered`
- (Mock Stripe client) `syncUsageProduct()` creates a price with `billing_scheme: tiered`, `tiers_mode: graduated`, `usage_type: metered`

### `tests/Feature/Billing/StripeUsageReporterTest.php`
- `reportUsage()` is a no-op when Stripe not configured
- `reportSeats()` is a no-op when Stripe not configured
- `reportUsage()` skips tenant with no active subscription
- `reportSeats()` skips tenant with no active subscription
- (Mock Stripe client) `reportUsage()` calls Stripe usage records API with `action: set` and current period usage
- (Mock Stripe client) `reportSeats()` calls Stripe usage records API with `action: set` and peak seat count

### `tests/Feature/Billing/ReportUsageToStripeJobTest.php`
- Job calls `StripeUsageReporter::reportUsage()` for the given tenant

### `tests/Feature/Checkout/PlanBuilderCheckoutTest.php` (update existing)
- POST to checkout no longer requires `seat_limit` or `usage_quota` fields
- Provisioned subscription gets `usage_quota = config('billing.usage_included_quota')`
- Provisioned subscription gets `seat_limit = config('billing.min_seats')`

---

## Verification

1. `vendor/bin/sail artisan migrate` — new `seat_snapshots` table created cleanly
2. `vendor/bin/sail artisan test --compact --filter="SeatTracker|TenantMembershipObserver|StripeProductSyncTiered|StripeUsageReporter|ReportUsageToStripe|PlanBuilder"` — all new and updated tests pass
3. `vendor/bin/sail artisan test --compact` — full suite passes
4. `vendor/bin/sail bin pint --dirty --format agent` — no formatting issues
5. Manual smoke (with real Stripe keys): `vendor/bin/sail artisan modules:sync-stripe` — seat and usage prices in Stripe Dashboard show graduated tiered structure with first tier at £0

---

## Critical Files

| File | Change |
|---|---|
| `database/migrations/*_create_seat_snapshots_table.php` | New |
| `app/Models/SeatSnapshot.php` | New |
| `app/Services/SeatTracker.php` | New |
| `app/Observers/TenantMembershipObserver.php` | New |
| `app/Providers/AppServiceProvider.php` | Register TenantMembershipObserver |
| `app/Services/StripeProductSync.php` | Add `syncTieredMeteredPrice()`, update `syncSeatProduct()` and `syncUsageProduct()` |
| `config/billing.php` | Add `usage_included_quota` |
| `.env.example` | Add `BILLING_USAGE_INCLUDED_QUOTA` |
| `app/Services/StripeUsageReporter.php` | New |
| `app/Jobs/ReportUsageToStripe.php` | New |
| `app/Services/UsageTracker.php` | Dispatch `ReportUsageToStripe` job on record |
| `app/Listeners/HandleStripeSubscriptionUpdated.php` | Trigger seat reporting on period renewal |
| `app/Http/Requests/CheckoutInitiateRequest.php` | Remove `seat_limit` and `usage_quota` rules |
| `app/Services/CheckoutSessionBuilder.php` | Remove params, use system defaults |
| `app/Http/Controllers/CheckoutController.php` | Update props, stop forwarding removed fields |
| `resources/js/pages/checkout/PlanBuilder.vue` | Replace inputs with informational sections |
