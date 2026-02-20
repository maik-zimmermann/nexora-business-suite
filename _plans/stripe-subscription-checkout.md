# Plan: Stripe Subscription Checkout with Tenant Creation

## Context

The current registration flow uses Fortify's standard register form. This replaces it with a Stripe Checkout-based subscription flow: users build a custom plan (modules + seats + usage quota), enter only their email, and are redirected to Stripe. On successful payment, a webhook provisions a new user, tenant, and subscription automatically. A post-checkout onboarding flow collects deferred data (name, org name, subdomain, password). Subscriptions include seat limits, usage quotas, metered overage billing, and a free 14-day trial with card required. Cancellation flows through read-only access for a configurable period, followed by a full lockout.

---

## Key Decisions

- **Billing entity**: `Tenant` (not `User`) — all subscriptions belong to organisations.
- **Package**: `laravel/cashier-stripe` — handles Stripe Checkout, webhooks, subscriptions, and metered billing.
- **Modules**: Database-driven `Module` model with a seeder.
- **Free trial**: 14 days, credit card required upfront.
- **Registration**: Fortify's `Features::registration()` is removed. The old `/register` route is replaced by the checkout plan builder.
- **Onboarding auth**: Signed URL (7-day expiry) sent via email — no password required to access the onboarding page.

---

## Phase 1: Dependencies & Configuration

### Install Laravel Cashier
```
vendor/bin/sail composer require laravel/cashier
vendor/bin/sail artisan vendor:publish --tag="cashier-migrations"
vendor/bin/sail artisan vendor:publish --tag="cashier-config"
```

- **`config/cashier.php`**: Set `model` to `App\Models\Tenant`.
- **`.env`**: Add `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `CASHIER_CURRENCY=usd`.
- **New `config/billing.php`**: Config-driven constants (trial days = 14, min seats = 5, read-only days = 30, Stripe Price IDs for per-seat and metered overage).

---

## Phase 2: Database Migrations

Run all migrations with `vendor/bin/sail artisan migrate` after creating.

### Modify published Cashier migration
The published migration targets `users`. Edit it before running to target `tenants`. Because `tenants.id` is a UUID string (not bigint), verify the Cashier `subscriptions` and `subscription_items` tables use `string` for `billable_id`.

Adds to `tenants` table: `stripe_id` (nullable, unique), `pm_type`, `pm_last_four`, `trial_ends_at`.

### New migrations to create via `artisan make:migration`

1. **`create_modules_table`**: `id`, `name`, `slug` (unique), `description` (nullable text), `stripe_monthly_price_id`, `stripe_annual_price_id`, `monthly_price_cents`, `annual_price_cents`, `is_active` (bool, default true), `sort_order` (smallint, default 0), timestamps.

2. **`create_tenant_subscriptions_table`**: `id`, `tenant_id` (uuid FK, unique, cascade delete), `stripe_subscription_id` (nullable), `status` (string), `billing_interval` (string: monthly/annual), `module_slugs` (json), `seat_limit` (smallint, min 5), `seat_stripe_price_id` (nullable), `usage_quota` (unsignedInt), `usage_stripe_price_id` (nullable), `trial_ends_at` (nullable timestamp), `read_only_ends_at` (nullable timestamp), `current_period_end` (nullable timestamp), timestamps.

3. **`create_usage_records_table`**: `id`, `tenant_id` (uuid FK, cascade delete, indexed), `type` (string), `quantity` (unsignedInt, default 1), `recorded_at` (timestamp, indexed), timestamps.

4. **`create_checkout_sessions_table`**: `id`, `session_id` (string, unique), `email`, `module_slugs` (json), `seat_limit` (smallint), `usage_quota` (unsignedInt), `billing_interval` (string), `expires_at` (timestamp), timestamps.

5. **`make_users_password_nullable`**: Change `users.password` to nullable (for users created without a password during webhook provisioning). Also add `onboarding_completed_at` (nullable timestamp).

---

## Phase 3: Enums

Create via `artisan make:class`:
- **`App\Enums\SubscriptionStatus`** — backed string enum: `Active`, `Trialing`, `PastDue`, `Cancelled`, `ReadOnly`, `Locked`.
- **`App\Enums\BillingInterval`** — backed string enum: `Monthly`, `Annual`.
- **`App\Enums\UsageType`** — backed string enum (at least `ApiCall` to start).

---

## Phase 4: Models

### Update `App\Models\Tenant`
File: `app/Models/Tenant.php`
- Add `Laravel\Cashier\Billable` trait.
- Add Cashier fields to `$fillable`: `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`.
- Add relationships: `tenantSubscription(): HasOne TenantSubscription`, `usageRecords(): HasMany UsageRecord`.
- Add helpers: `currentSeatCount(): int` (count of memberships), `hasAvailableSeat(): bool`.

### Update `App\Models\User`
File: `app/Models/User.php`
- Make `password` nullable in fillable and casts.
- Add `onboarding_completed_at` to `$casts` (datetime).
- Add `hasCompletedOnboarding(): bool`.

### New `App\Models\Module`
File: `app/Models/Module.php` (via `artisan make:model Module --factory`)
- Fillable: all module columns. Casts: `is_active` bool, `monthly_price_cents` / `annual_price_cents` int.

### New `App\Models\TenantSubscription`
File: `app/Models/TenantSubscription.php` (via `artisan make:model TenantSubscription --factory`)
- Fillable: all columns. Casts: `module_slugs` array, `status` SubscriptionStatus enum, `billing_interval` BillingInterval enum, timestamps.
- Relationship: `tenant(): BelongsTo`.
- Helpers: `isActive()`, `isReadOnly()`, `isLocked()`, `isPastDue()`, `currentUsage(): int`, `isOverQuota(): bool`.

### New `App\Models\UsageRecord`
File: `app/Models/UsageRecord.php` (via `artisan make:model UsageRecord --factory`)
- Fillable: `tenant_id`, `type`, `quantity`, `recorded_at`. Relationship: `tenant(): BelongsTo`.

### New `App\Models\CheckoutSession`
File: `app/Models/CheckoutSession.php` (via `artisan make:model CheckoutSession`)
- Fillable: all columns. Casts: `module_slugs` array, `expires_at` datetime.

---

## Phase 5: Service Classes

Create via `artisan make:class`:

### `App\Services\CheckoutSessionBuilder`
1. Accepts: email, module slugs array, seat count, usage quota, billing interval.
2. Resolves `Module` records and their Stripe Price IDs for the selected interval.
3. Builds Stripe Checkout line items: one per module, one for seat quantity (using `config('billing.seat_monthly_price_id')` / annual equivalent), one metered overage item.
4. Creates Stripe Checkout Session in `subscription` mode via `Cashier::stripe()->checkout->sessions->create(...)` — with `customer_email`, `trial_period_days: 14`, `success_url`, `cancel_url`.
5. Persists a `CheckoutSession` record keyed by the Stripe session ID.
6. Returns the Stripe redirect URL.

### `App\Services\TenantProvisioningService`
Called from webhook listener inside a `DB::transaction()`:
1. Looks up `CheckoutSession` by Stripe session ID.
2. Creates `User` (email only, password null, email_verified_at null).
3. Creates `Tenant` (temp slug from email prefix + unique suffix, `is_active = false`).
4. Creates `TenantSubscription` (status trialing, seat_limit, module_slugs, usage_quota, stripe_subscription_id, trial_ends_at from Stripe payload).
5. Creates `TenantMembership` with the `owner` role (looked up by slug from seeded roles).
6. Calls `$tenant->createOrGetStripeCustomer()` to link Stripe customer to tenant.
7. Dispatches `TenantProvisioned` event.
8. Deletes the `CheckoutSession` record.

### `App\Services\UsageTracker`
- `record(Tenant, UsageType, int $quantity = 1): void` — creates `UsageRecord`, reports usage to Stripe metered billing API via `$tenant->subscription()->reportUsage($quantity)`.
- `currentPeriodUsage(Tenant): int` — sums `UsageRecord.quantity` within the current billing period.
- `remainingQuota(Tenant): int`.

---

## Phase 6: Events & Listeners

### Event: `App\Events\TenantProvisioned`
Carries `User $user` and `Tenant $tenant`.

### Listener: `App\Listeners\SendTenantActivationEmail`
Listens to `TenantProvisioned`. Calls `$user->notify(new TenantActivationEmail($user, $tenant))`.

### Notification: `App\Notifications\TenantActivationEmail`
Sends a mail notification with a `URL::temporarySignedRoute('onboarding.show', now()->addDays(7), ['user' => $user->id])` link.

### Webhook Listeners (all implement `ShouldQueue`)

Register all in `AppServiceProvider::boot()` via `Event::listen(WebhookReceived::class, ...)`.

- **`HandleStripeCheckoutCompleted`** — type `checkout.session.completed` → calls `TenantProvisioningService::provision()`.
- **`HandleStripeSubscriptionUpdated`** — type `customer.subscription.updated` → finds `TenantSubscription` by `stripe_subscription_id`, syncs status, `current_period_end`, `trial_ends_at`.
- **`HandleStripeSubscriptionDeleted`** — type `customer.subscription.deleted` → sets status to `ReadOnly`, sets `read_only_ends_at = now()->addDays(config('billing.read_only_days'))`.
- **`HandleStripeInvoicePaymentFailed`** — type `invoice.payment_failed` → sets status to `PastDue`, notifies tenant owner.

---

## Phase 7: Middleware

Create via `artisan make:middleware`:

### `App\Http\Middleware\EnsureOnboardingEligible`
Alias: `onboarding.eligible`. Applied to onboarding routes (unauthenticated access via signed URL):
- If `$request->hasValidSignature()`: log in the user via `Auth::loginUsingId($request->route('user'))` and continue.
- Else if already authenticated: continue.
- Else: abort 403.
- If user has already completed onboarding (`hasCompletedOnboarding()`): redirect to dashboard.

### `App\Http\Middleware\EnforceSubscriptionStatus`
Alias: `subscription.status`. Applied inside tenant-scoped routes after `tenant.member`:
- Reads `app(Tenancy::class)->current()->tenantSubscription`.
- `isLocked()`: abort 403 with message.
- `isReadOnly()`: set `subscription_read_only` flag on request attributes (shared via `HandleInertiaRequests`).
- Otherwise: continue.

### Update `bootstrap/app.php`
- Add CSRF exclusion: `$middleware->validateCsrfTokens(except: ['/stripe/webhook'])`.
- Register new middleware aliases: `onboarding.eligible` and `subscription.status`.
- Add additional route files: `routes/checkout.php` and `routes/onboarding.php`.

### Update `app/Http/Middleware/HandleInertiaRequests.php`
Add to `share()`:
```php
'subscription' => fn () => /* resolve from Tenancy singleton */
    null if no tenant, else [status, seat_limit, current_seat_count, usage_quota, current_usage, current_period_end]
```

---

## Phase 8: Routes

### `routes/checkout.php` (no auth required)
| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | `/checkout` | `CheckoutController@index` | `checkout.index` |
| POST | `/checkout/session` | `CheckoutController@store` | `checkout.store` |
| GET | `/checkout/success` | `CheckoutController@success` | `checkout.success` |
| GET | `/checkout/cancelled` | `CheckoutController@cancelled` | `checkout.cancelled` |

### `routes/onboarding.php` (middleware: `onboarding.eligible`)
| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | `/onboarding/{user}` | `OnboardingController@show` | `onboarding.show` |
| POST | `/onboarding/{user}` | `OnboardingController@store` | `onboarding.store` |

Note: Cashier auto-registers `POST /stripe/webhook` — no manual route needed.

### Update `routes/web.php`
- Replace `canRegister` prop with `canCheckout: true` (or just link directly to `route('checkout.index')`).
- `require __DIR__.'/checkout.php'` and `require __DIR__.'/onboarding.php'`.

---

## Phase 9: Controllers & Form Requests

### Controllers (via `artisan make:controller`)

**`App\Http\Controllers\CheckoutController`**
- `index()`: Returns `checkout/PlanBuilder` Inertia page with `modules` (active, sorted), `minimumSeats: 5`, billing interval options.
- `store(CheckoutInitiateRequest)`: Calls `CheckoutSessionBuilder::build(...)`, redirects to Stripe URL via `redirect()->away()`.
- `success()`: Returns `checkout/Success` Inertia page.
- `cancelled()`: Returns `checkout/Cancelled` Inertia page.

**`App\Http\Controllers\OnboardingController`**
- `show(Request)`: Returns `onboarding/Setup` Inertia page with user email as prop.
- `store(OnboardingRequest)`: Updates user (name, password, `onboarding_completed_at = now()`), updates tenant (name, slug, `is_active = true`), redirects to tenant dashboard.

### Form Requests (via `artisan make:request`)

**`App\Http\Requests\CheckoutInitiateRequest`**: Rules for email, module_slugs (exists in modules table), seat_limit (min 5), usage_quota, billing_interval.

**`App\Http\Requests\OnboardingRequest`**: Rules for name, organisation_name, slug (regex `^[a-z0-9-]+$`, unique:tenants,slug), password (Password::default(), confirmed).

---

## Phase 10: Fortify Changes

**`config/fortify.php`**: Remove `Features::registration()` from the features array.

**`app/Providers/FortifyServiceProvider.php`**:
- Remove `Fortify::createUsersUsing(CreateNewUser::class)` call.
- Remove `Fortify::registerView(...)` call.

The `resources/js/pages/auth/Register.vue` file is deleted — it is no longer referenced by any route.

---

## Phase 11: Seeders & Factories

### `Database\Seeders\ModuleSeeder`
Seeds 5 example modules (CRM, Projects, Invoicing, HR, Support) with placeholder Stripe Price IDs (from env variables so local/staging can differ). Added to `DatabaseSeeder::run()` before `TenantSeeder`.

### Factories
- **`ModuleFactory`**: Default state with fake Stripe Price IDs, realistic prices, `is_active: true`. State: `inactive()`.
- **`TenantSubscriptionFactory`**: Default state `status: active`, `seat_limit: 5`, `usage_quota: 1000`, `module_slugs: ['crm']`, `billing_interval: monthly`. States: `trialing()`, `pastDue()`, `readOnly()`, `locked()`.
- **Update `UserFactory`**: Add `passwordless()` state (password null, email_verified_at null, onboarding_completed_at null).

---

## Phase 12: Vue Pages

### `resources/js/pages/checkout/PlanBuilder.vue`
Two-column layout (uses `AuthSplitLayout` or a wide guest layout without card constraints). Left: module checklist, billing interval toggle (monthly/annual), seat count input (min 5), usage quota selector, email field. Right: sticky live price summary. Submits via Inertia Form to `checkout.store`. Uses Reka UI components and Lucide icons.

### `resources/js/pages/checkout/Success.vue`
Simple centered page (`AuthSimpleLayout`). Checkmark icon, "Check your inbox" heading, explanation text. Link to login.

### `resources/js/pages/checkout/Cancelled.vue`
Simple centered page (`AuthSimpleLayout`). Back link to `checkout.index`.

### `resources/js/pages/onboarding/Setup.vue`
Single-page form (`AuthSimpleLayout` or `AuthCardLayout`). Fields: full name, organisation name, subdomain (with live `{slug}.nexora.io` preview), password + confirmation. Submits to `onboarding.store`.

---

## Phase 13: Subscription Status Enforcement (Scheduled Command)

`App\Console\Commands\UpdateSubscriptionStatuses` — command `subscription:update-statuses`:
1. Moves `read_only` subscriptions past `read_only_ends_at` → `locked`.

Register in `routes/console.php`: `Schedule::command('subscription:update-statuses')->daily()`.

---

## Phase 14: Tests

All tests use `uses(RefreshDatabase::class)`. Run with `vendor/bin/sail artisan test --compact --filter=...`.

| File | Key Scenarios |
|------|--------------|
| `tests/Feature/Checkout/PlanBuilderTest.php` | Page renders with modules; validation errors for no modules, < 5 seats, invalid email; `CheckoutSession` record created on valid submit; redirects to Stripe URL |
| `tests/Feature/Checkout/WebhookTest.php` | `checkout.session.completed` provisions user/tenant/membership/subscription; sends activation email; idempotent on duplicate; `customer.subscription.updated` syncs status; `customer.subscription.deleted` sets grace period; `invoice.payment_failed` sets past_due and notifies owner; invalid Stripe signature → 403 |
| `tests/Feature/Checkout/OnboardingTest.php` | Signed URL allows unauthenticated access; expired URL → 403; completing onboarding sets all fields and sets `is_active = true`; duplicate slug → validation error; already-onboarded user redirected away |
| `tests/Feature/Billing/SeatLimitTest.php` | `hasAvailableSeat()` returns true/false correctly; adding member beyond limit fails |
| `tests/Feature/Billing/SubscriptionStatusTest.php` | Active tenant passes middleware; locked → 403; read-only/grace → passes with prop flag |
| `tests/Feature/Billing/UsageTrackerTest.php` | Recording creates `UsageRecord`; `currentPeriodUsage()` sums within period only; `isOverQuota()` true/false |
| `tests/Feature/Auth/RegistrationTest.php` | Replace existing tests: `GET /register` → 404; `POST /register` → 404 (feature disabled) |

---

## Verification

1. `vendor/bin/sail artisan migrate --fresh --seed` — all migrations run, modules seeded.
2. `vendor/bin/sail artisan test --compact` — all tests pass.
3. `vendor/bin/sail bin pint --dirty --format agent` — code formatted.
4. Manual smoke test: `GET /checkout` renders plan builder; `GET /register` returns 404.
5. Stripe CLI: `stripe listen --forward-to localhost/stripe/webhook` to test webhook locally.

---

## Critical Files to Modify

| File | Change |
|------|--------|
| `config/fortify.php` | Remove `Features::registration()` |
| `app/Providers/FortifyServiceProvider.php` | Remove `createUsersUsing` and `registerView` |
| `app/Models/Tenant.php` | Add `Billable` trait, relationships, seat helpers |
| `app/Models/User.php` | Nullable password, `onboarding_completed_at` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share subscription state |
| `bootstrap/app.php` | CSRF exclusion, new middleware aliases, new route files |
| `routes/web.php` | Require checkout.php and onboarding.php, update Welcome props |
| `database/seeders/DatabaseSeeder.php` | Add `ModuleSeeder` |
