# Plan: Tenant-Based Multi-Tenancy

## Context

Nexora Business Suite needs a foundational multi-tenancy layer so that all future features can be built on top of isolated tenant data. The spec defines a shared-database approach where a single `Tenant` model identifies each organisation, and every request resolves the active tenant via subdomain slug (primary) or a signed `X-Tenant-ID` header (secondary). This plan implements the Tenant model, the resolution middleware, a Tenancy singleton service, an opt-in `BelongsToTenant` trait for future models, and comprehensive Pest feature tests.

Key decisions from the spec:
- UUID primary key on `Tenant` — intentional deviation from the app's auto-increment convention; UUID avoids enumerable IDs for a root-level entity.
- Header resolution requires an HMAC-SHA256 signature (`X-Tenant-Signature`) computed from the tenant UUID using `config('app.key')`. API clients receive a pre-computed signature out-of-band.
- Root domain (no subdomain / `www`) passes through without a tenant; the existing `Welcome.vue` is the landing page.
- Tenant resolution failures are logged via `Log::warning()`.

---

## Files to Create

| File | How |
|------|-----|
| `app/Models/Tenant.php` | `artisan make:model Tenant --migration --factory` |
| `database/migrations/{ts}_create_tenants_table.php` | Generated with model |
| `database/factories/TenantFactory.php` | Generated with model |
| `database/seeders/TenantSeeder.php` | `artisan make:seeder TenantSeeder` |
| `app/Support/Tenancy.php` | `artisan make:class Support/Tenancy` |
| `app/Http/Middleware/ResolveTenant.php` | `artisan make:middleware ResolveTenant` |
| `app/Http/Middleware/RequiresTenant.php` | `artisan make:middleware RequiresTenant` |
| `app/Concerns/BelongsToTenant.php` | Manual (follow `app/Concerns/ProfileValidationRules.php`) |
| `app/Models/Scopes/TenantScope.php` | Manual |
| `tests/Feature/Tenancy/TenantResolutionTest.php` | `artisan make:test --pest Tenancy/TenantResolutionTest` |

## Files to Modify

| File | Change |
|------|--------|
| `bootstrap/app.php` | Prepend `ResolveTenant` to web stack; alias `RequiresTenant` |
| `app/Providers/AppServiceProvider.php` | Bind `Tenancy` singleton in `register()` |
| `database/seeders/DatabaseSeeder.php` | Call `TenantSeeder::class` |

---

## Step-by-Step Implementation

### Step 1 — Tenant Model, Migration, Factory, Seeder

Run: `vendor/bin/sail artisan make:model Tenant --migration --factory --no-interaction`
Run: `vendor/bin/sail artisan make:seeder TenantSeeder --no-interaction`

**Migration** (`database/migrations/{ts}_create_tenants_table.php`):
- `$table->uuid('id')->primary()`
- `$table->string('name')`
- `$table->string('slug')->unique()` — index on `slug` for lookup performance
- `$table->boolean('is_active')->default(true)`
- `$table->timestamps()`

**Model** (`app/Models/Tenant.php`):
- `public $incrementing = false`
- `protected $keyType = 'string'`
- `$fillable`: `['name', 'slug', 'is_active']`
- `casts()` method (not property): `['id' => 'string', 'is_active' => 'boolean']`
- `HasFactory` trait; no `BelongsToTenant` on this model itself

**Factory** (`database/factories/TenantFactory.php`):
- `id`: `fake()->uuid()`
- `name`: `fake()->company()`
- `slug`: derive from name — `str(fake()->company())->slug()`; or use `fake()->unique()->slug(3)`
- `is_active`: `true`
- Add `inactive()` state: `['is_active' => false]`

**Seeder** (`database/seeders/TenantSeeder.php`):
- Create 2 tenants with known slugs (`acme`, `globex`) for local development

**Modify** `database/seeders/DatabaseSeeder.php`:
- Add `$this->call(TenantSeeder::class)` before the User factory line

---

### Step 2 — Tenancy Singleton

Run: `vendor/bin/sail artisan make:class Support/Tenancy --no-interaction`

**`app/Support/Tenancy.php`** — plain PHP class (no extends):
- Private `?Tenant $tenant = null` property
- `set(Tenant $tenant): void`
- `get(): ?Tenant`
- `current(): Tenant` — throws `\RuntimeException('No tenant resolved for this request.')` if null
- `hasTenant(): bool`
- `flush(): void` — sets `$this->tenant = null`; called in tests to reset between assertions

**Modify** `app/Providers/AppServiceProvider.php`:
- In `register()`: `$this->app->singleton(Tenancy::class, fn () => new Tenancy())`
- Add import for `App\Support\Tenancy` and `App\Models\Tenant`

---

### Step 3 — ResolveTenant Middleware

Run: `vendor/bin/sail artisan make:middleware ResolveTenant --no-interaction`

**`app/Http/Middleware/ResolveTenant.php`** — resolution logic in `handle()`:

**Subdomain extraction:**
1. `$host = $request->getHost()`
2. `$baseDomain = parse_url(config('app.url'), PHP_URL_HOST)`
3. `$subdomain = str($host)->before('.'.$baseDomain)->toString()` — if `$subdomain === $host` or `$subdomain === 'www'` or `$host === $baseDomain`, no subdomain is present

**If subdomain present (primary path):**
1. `Tenant::where('slug', $subdomain)->first()`
2. Not found → `Log::warning('Tenant resolution failed', ['strategy' => 'subdomain', 'slug' => $subdomain])` → `abort(404)`
3. Found but `!$tenant->is_active` → log + `abort(403)`
4. Found and active → `app(Tenancy::class)->set($tenant)` → `$next($request)`

**If no subdomain, check `X-Tenant-ID` header (secondary path):**
1. Header absent → `$next($request)` (graceful pass-through, no tenant)
2. Header present → validate `X-Tenant-Signature` header:
   - Compute `$expected = hash_hmac('sha256', $request->header('X-Tenant-ID'), config('app.key'))`
   - `hash_equals($expected, $request->header('X-Tenant-Signature', ''))` — if false → `Log::warning(...)` → `abort(403)`
3. Look up `Tenant::find($tenantId)` — not found → log + `abort(404)`
4. Inactive → log + `abort(403)`
5. Valid → set tenant + `$next($request)`

---

### Step 4 — RequiresTenant Middleware

Run: `vendor/bin/sail artisan make:middleware RequiresTenant --no-interaction`

**`app/Http/Middleware/RequiresTenant.php`** — in `handle()`:
- `if (!app(Tenancy::class)->hasTenant()) { abort(404); }`
- Otherwise `$next($request)`

---

### Step 5 — Register Middleware in bootstrap/app.php

**Modify** `bootstrap/app.php` inside `withMiddleware()`:

```
$middleware->web(prepend: [ResolveTenant::class]);
$middleware->alias(['tenant' => RequiresTenant::class]);
```

The existing `web(append: [...])` call stays untouched. `ResolveTenant` prepended means it runs before `HandleInertiaRequests`, ensuring the `Tenancy` singleton is populated before Inertia shares data.

---

### Step 6 — TenantScope and BelongsToTenant Trait

**Create** `app/Models/Scopes/TenantScope.php` implementing `Illuminate\Database\Eloquent\Scope`:
- `apply(Builder $builder, Model $model): void`
- If `app(Tenancy::class)->hasTenant()`: `$builder->where($model->getTable().'.tenant_id', app(Tenancy::class)->current()->id)`
- If no tenant: `$builder->whereRaw('1 = 0')` — fail-safe; prevents accidental full-table exposure on public routes. Models can opt out with `withoutGlobalScope(TenantScope::class)`.

**Create** `app/Concerns/BelongsToTenant.php` (follow namespace/style of `ProfileValidationRules.php`):
- `bootBelongsToTenant(): void` (static, auto-called by Laravel):
  - `static::addGlobalScope(new TenantScope())`
  - `static::creating(fn ($model) => $model->tenant_id = app(Tenancy::class)->current()->id)`
- `initializeBelongsToTenant(): void`: merges `'tenant_id'` into `$this->fillable`
- `tenant(): BelongsTo` — `return $this->belongsTo(Tenant::class)`

---

### Step 7 — Run Migration

```
vendor/bin/sail artisan migrate --no-interaction
```

---

### Step 8 — Feature Tests

Run: `vendor/bin/sail artisan make:test --pest Tenancy/TenantResolutionTest --no-interaction`

**`tests/Feature/Tenancy/TenantResolutionTest.php`** — follows `ProfileUpdateTest.php` conventions:
- `uses(RefreshDatabase::class)` at the top
- Use `$this->get('http://acme.nexora.app/')` to simulate subdomain requests (Laravel reads the host from the full URL)
- For header tests: `$this->withHeaders([...])->get('http://localhost/')`
- After each test that sets the Tenancy singleton, call `app(\App\Support\Tenancy::class)->flush()` in `afterEach()` to reset state

**Test cases:**

| # | Test name | Setup | Assert |
|---|-----------|-------|--------|
| 1 | subdomain resolves active tenant | `Tenant::factory(['slug' => 'acme'])->create()` | Response not 404/403; `app(Tenancy::class)->get()->slug === 'acme'` |
| 2 | subdomain inactive tenant returns 403 | `inactive()` factory state | `assertStatus(403)` |
| 3 | unknown subdomain returns 404 | No matching tenant | `assertStatus(404)` |
| 4 | valid header + valid signature resolves tenant | Compute real HMAC in test body | Not 403/404 |
| 5 | valid header + invalid signature returns 403 | Pass wrong signature string | `assertStatus(403)` |
| 6 | valid header + missing signature returns 403 | Omit `X-Tenant-Signature` | `assertStatus(403)` |
| 7 | subdomain takes precedence over header | Create two tenants; send subdomain `acme` + header for `globex` | Resolved tenant slug is `acme` |
| 8 | no subdomain, no header passes through | Plain `localhost` request | `app(Tenancy::class)->hasTenant()` is false; response not aborted |
| 9 | RequiresTenant aborts when no tenant set | Define inline route with `tenant` middleware; hit with no tenant | `assertStatus(404)` |
| 10 | BelongsToTenant auto-assigns tenant_id | Set tenant; create a stub tenant-scoped model | `$model->tenant_id === $tenant->id` |
| 11 | global scope filters to current tenant | Two tenants, two sets of scoped model records | Query only returns current tenant's records |

For tests 9–11, create a minimal in-memory model or use `Route::get()` inside the test with `RefreshApplication` pattern as needed.

Run: `vendor/bin/sail artisan test --compact --filter=TenantResolutionTest`

---

### Step 9 — Code Style

```
vendor/bin/sail bin pint --dirty --format agent
```

Then re-run the full test suite: `vendor/bin/sail artisan test --compact`

---

## Verification Checklist

- [ ] `tenants` table exists with correct columns after migration
- [ ] `TenantSeeder` creates `acme` and `globex` tenants in local DB
- [ ] GET `http://acme.nexora.app/` (local via Sail hosts config or test) resolves tenant
- [ ] GET `http://localhost/` does not resolve a tenant and shows Welcome page
- [ ] Unknown subdomain returns 404; inactive returns 403
- [ ] Header resolution with valid HMAC works; invalid HMAC returns 403
- [ ] All 11 feature tests pass
- [ ] Existing auth/settings tests remain green
