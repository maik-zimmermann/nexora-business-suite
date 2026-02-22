# Plan: Stripe Product Auto-Provisioning

## Context

Currently, Stripe price IDs for modules must be manually created in Stripe, copied into environment variables, and the seeder must be re-run to populate the `modules` table. This is error-prone and blocks self-service module management. This plan replaces that manual process with automatic Stripe product and price creation whenever a module is created or its pricing changes.

Additionally, the seat overage and usage quota overage Stripe products must be provisioned automatically. Subscriptions include a configurable base number of seats and usage units for free — only exceeding those limits incurs extra charges. Currently their Stripe price IDs are env vars with no way to set them automatically.

The spec answers the open questions:
- Seat/usage price IDs → stored in a **`app_settings` table** (dynamic, no restart required)
- No multi-currency support needed
- Old Stripe prices are **archived** (`active: false`) when prices change, never deleted

---

## Phase 1: Database & Model

### 1. Migration — make module Stripe price ID columns nullable
`database/migrations/*_make_module_stripe_price_ids_nullable.php`

Change `stripe_monthly_price_id` and `stripe_annual_price_id` on the `modules` table from NOT NULL to nullable. They start as `null` and get populated by Stripe sync after module creation.

### 2. Migration — create `app_settings` table
`database/migrations/*_create_app_settings_table.php`

| Column | Type |
|--------|------|
| `key` | string, primary key |
| `value` | text, nullable |
| timestamps | |

### 3. Model — `AppSetting`
`app/Models/AppSetting.php` (via `artisan make:model AppSetting`)

- `$primaryKey = 'key'`, `$keyType = 'string'`, `$incrementing = false`
- `$fillable = ['key', 'value']`
- `static get(string $key, mixed $default = null): mixed` — returns stored value or default
- `static set(string $key, mixed $value): void` — `updateOrCreate` by key

---

## Phase 2: Configuration

### 4. Update `config/billing.php`
`config/billing.php`

Add two new keys for seat overage pricing amounts (these are static business decisions, not dynamic):

```
'seat_monthly_cents' => (int) env('BILLING_SEAT_MONTHLY_CENTS', 1500),
'seat_annual_cents'  => (int) env('BILLING_SEAT_ANNUAL_CENTS', 14400),
```

Keep existing `seat_monthly_price_id`, `seat_annual_price_id`, `usage_metered_price_id` as env-var fallbacks.

Also add to `.env.example`:
```
BILLING_SEAT_MONTHLY_CENTS=1500
BILLING_SEAT_ANNUAL_CENTS=14400
BILLING_USAGE_OVERAGE_CENTS=10
```

---

## Phase 3: Core Service

### 5. Service — `StripeProductSync`
`app/Services/StripeProductSync.php` (via `artisan make:class app/Services/StripeProductSync`)

All methods guard with `if (! config('cashier.secret')) { return; }` — same pattern as `TenantProvisioningService`.

Uses `Cashier::stripe()` for all Stripe API calls.

**Private helper — `findOrCreateProduct()`**

Searches for an existing Stripe product using the Products Search API (`$stripe->products->search(['query' => "metadata['key']:'value'"])`). If found, updates the name if it changed. If not found, creates a new product with `name`, `description`, and metadata.

**Private helper — `syncPrice()`**

Accepts: product ID, existing price ID (nullable), unit amount (cents), interval (`month`/`year`). Logic:
1. If an existing price ID is given: retrieve the Stripe price, compare `unit_amount`. If it matches, return the existing ID (no-op).
2. If amount differs: archive the old price (`$stripe->prices->update($id, ['active' => false])`), then create a new price.
3. If no existing price: create a new price.
4. Returns the price ID to store.

Price creation params: `product`, `unit_amount`, `currency: usd`, `recurring: {interval}`.

**Public `sync(Module $module): void`**

1. Call `findOrCreateProduct()` with `metadata['module_slug'] = $module->slug`, using the module name and description.
2. Call `syncPrice()` for monthly (interval: `month`) using `$module->monthly_price_cents` and `$module->stripe_monthly_price_id`.
3. Call `syncPrice()` for annual (interval: `year`) using `$module->annual_price_cents` and `$module->stripe_annual_price_id`.
4. Save updated IDs with `$module->updateQuietly(['stripe_monthly_price_id' => ..., 'stripe_annual_price_id' => ...])` — bypasses observer to prevent loops.

**Public `syncSeatProduct(): void`**

1. `findOrCreateProduct()` with `metadata['nexora_product'] = 'seat'`, name "Additional Seat".
2. Sync monthly seat price using `config('billing.seat_monthly_cents')`, reading existing ID from `AppSetting::get('billing.seat_monthly_price_id')`.
3. Sync annual seat price using `config('billing.seat_annual_cents')`, reading existing ID from `AppSetting::get('billing.seat_annual_price_id')`.
4. Store new IDs via `AppSetting::set('billing.seat_monthly_price_id', ...)` and `AppSetting::set('billing.seat_annual_price_id', ...)`.

**Public `syncUsageProduct(): void`**

1. If `AppSetting::get('billing.usage_metered_price_id')` is already set, return early (metered prices are not updated in-place).
2. `findOrCreateProduct()` with `metadata['nexora_product'] = 'usage'`, name "Usage Overage".
3. Create a metered price: `billing_scheme: per_unit`, `recurring: {interval: month, usage_type: metered, aggregate_usage: sum}`, `unit_amount: config('billing.usage_overage_cents')`.
4. Store via `AppSetting::set('billing.usage_metered_price_id', ...)`.

---

## Phase 4: Observer

### 6. Observer — `ModuleObserver`
`app/Observers/ModuleObserver.php` (via `artisan make:observer ModuleObserver --model=Module`)

Constructor injects `StripeProductSync`.

- `created(Module $module)`: calls `$this->stripeProductSync->sync($module)` if Stripe configured.
- `updated(Module $module)`: calls sync only if `$module->wasChanged(['name', 'description', 'monthly_price_cents', 'annual_price_cents'])`. Skips for unrelated changes (e.g. `sort_order`, `is_active`).

Both methods guard with `if (! config('cashier.secret')) { return; }`.

### 6b. Register observer in `AppServiceProvider`
`app/Providers/AppServiceProvider.php`

Add `$this->configureObservers()` call inside `boot()` alongside existing `configure*` calls.

```php
protected function configureObservers(): void
{
    Module::observe(ModuleObserver::class);
}
```

---

## Phase 5: Artisan Command

### 7. Command — `modules:sync-stripe`
`app/Console/Commands/SyncModulesToStripe.php` (via `artisan make:command SyncModulesToStripe`)

Signature: `modules:sync-stripe`

`handle(StripeProductSync $sync): int`
1. If `config('cashier.secret')` is not set: output warning, return SUCCESS.
2. Call `$sync->syncSeatProduct()` — output progress.
3. Call `$sync->syncUsageProduct()` — output progress.
4. Iterate all modules, call `$sync->sync($module)` for each — output per-module line.
5. Return SUCCESS.

---

## Phase 6: Update Existing Code

### 8. Update `ModuleSeeder`
`database/seeders/ModuleSeeder.php`

- Remove all `stripe_monthly_price_id` and `stripe_annual_price_id` keys from the data arrays.
- After each `updateOrCreate`, call `app(StripeProductSync::class)->sync($module)`.
- The explicit call and observer will both run, but `sync()` is idempotent so this is safe.

### 9. Update `CheckoutSessionBuilder`
`app/Services/CheckoutSessionBuilder.php`

Replace `config('billing.seat_*_price_id')` reads with `AppSetting` reads that fall back to config:

```php
use App\Models\AppSetting;

$seatPriceId = $billingInterval === BillingInterval::Annual
    ? AppSetting::get('billing.seat_annual_price_id', config('billing.seat_annual_price_id'))
    : AppSetting::get('billing.seat_monthly_price_id', config('billing.seat_monthly_price_id'));
```

The existing `if ($seatPriceId)` null guard remains — no other changes needed.

### 10. Update `ModuleFactory`
`database/factories/ModuleFactory.php`

Add a `withoutStripePrices()` state for tests that need to verify pre-sync state:

```php
public function withoutStripePrices(): static
{
    return $this->state(fn (array $attributes) => [
        'stripe_monthly_price_id' => null,
        'stripe_annual_price_id' => null,
    ]);
}
```

No changes to the default state — fake IDs remain correct for test isolation.

---

## Phase 7: Tests

All test files created via `artisan make:test --pest`.

### `tests/Unit/Models/AppSettingTest.php`
`artisan make:test --pest --unit Models/AppSettingTest`

- `get()` returns null when key missing
- `get()` returns default when key missing
- `get()` returns stored value
- `set()` creates a new record
- `set()` updates an existing record (idempotent)

### `tests/Feature/Billing/ModuleObserverTest.php`
`artisan make:test --pest Feature/Billing/ModuleObserverTest`

- Mock `StripeProductSync`: assert `sync()` called once when module created (with Stripe configured)
- Mock `StripeProductSync`: assert `sync()` called once when relevant fields updated
- Mock `StripeProductSync`: assert `sync()` NOT called when only `sort_order` changes
- Mock `StripeProductSync`: assert `sync()` NOT called when `cashier.secret` is null

### `tests/Feature/Billing/StripeProductSyncTest.php`
`artisan make:test --pest Feature/Billing/StripeProductSyncTest`

- `sync()` is no-op when `cashier.secret` is null — module price IDs remain unchanged
- `syncSeatProduct()` is no-op when `cashier.secret` is null — no AppSetting rows created
- `syncUsageProduct()` is no-op when `cashier.secret` is null — no AppSetting rows created
- `syncUsageProduct()` skips creation if `billing.usage_metered_price_id` already in AppSetting

### `tests/Feature/Billing/SyncModulesToStripeCommandTest.php`
`artisan make:test --pest Feature/Billing/SyncModulesToStripeCommandTest`

- Command exits successfully and shows warning when Stripe not configured
- Command mocks `StripeProductSync` and asserts `syncSeatProduct()`, `syncUsageProduct()`, and `sync()` (×N modules) all called when Stripe configured

---

## Verification

1. `vendor/bin/sail artisan migrate` — migrations run cleanly
2. `vendor/bin/sail artisan test --compact --filter="AppSetting|ModuleObserver|StripeProductSync|SyncModules"` — all new tests pass
3. `vendor/bin/sail artisan test --compact` — full suite passes
4. `vendor/bin/sail bin pint --dirty --format agent` — no formatting issues
5. Manual smoke (with real Stripe keys): `vendor/bin/sail artisan modules:sync-stripe` — products and prices appear in Stripe Dashboard, AppSetting rows populated

---

## Critical Files

| File | Change |
|------|--------|
| `database/migrations/*_make_module_stripe_price_ids_nullable.php` | New: make columns nullable |
| `database/migrations/*_create_app_settings_table.php` | New: settings table |
| `app/Models/AppSetting.php` | New: get/set helpers |
| `config/billing.php` | Add seat price amount keys |
| `.env.example` | Add new billing cents vars |
| `app/Services/StripeProductSync.php` | New: core sync logic |
| `app/Observers/ModuleObserver.php` | New: trigger sync on create/update |
| `app/Providers/AppServiceProvider.php` | Register observer |
| `app/Console/Commands/SyncModulesToStripe.php` | New: sync command |
| `database/seeders/ModuleSeeder.php` | Remove env-based price IDs, add sync call |
| `app/Services/CheckoutSessionBuilder.php` | Read seat price IDs from AppSetting |
| `database/factories/ModuleFactory.php` | Add `withoutStripePrices()` state |