# Subdomain Routing Separation

## Context

Currently all routes live in a single flat group — tenant app routes (dashboard, settings) are accessible from any hostname, and public routes (checkout, onboarding, welcome) are similarly unguarded by domain. The `ResolveTenant` middleware runs globally on every web request. The spec requires a clean split: public routes belong on the root domain only, and all tenant app routes must live under a tenant subdomain. Redirects must handle cross-domain navigation gracefully, and the frontend must generate subdomain-aware URLs when a tenant is active.

User decisions:
- After root-domain login with **one tenant** → auto-redirect to that tenant's subdomain dashboard. With **multiple tenants** → show a tenant picker page.
- After onboarding completes → redirect directly to `{slug}.nexora.app/dashboard`.

---

## Phase 1: Route Group Restructure

### `bootstrap/app.php`
- Remove `ResolveTenant::class` from the global `$middleware->web(prepend: [...])` stack. It will be applied per route group instead.

### `routes/web.php` — split into two domain groups

**Public domain group** (root domain, no tenant):
```
GET  /              → Welcome (home)
GET  /login         → Auth login
POST /login         → Auth login store
...all other Fortify auth routes...
GET  /checkout      → Checkout flow
POST /checkout/session
GET  /checkout/success
GET  /checkout/cancelled
GET  /onboarding/{user}
POST /onboarding/{user}
GET  /tenants       → Tenant picker (new, auth required)
```
Middleware applied: `web`, `HandleAppearance`, `HandleInertiaRequests`

**Subdomain domain group** (`{tenant}.` prefix using Laravel's `Route::domain()`):
```
GET  /dashboard     → Dashboard
GET  /settings/*    → All settings routes
GET  /login         → Tenant-specific login (mirrors root login, adds tenant branding)
POST /login         → Tenant-specific login store
```
Middleware applied: `web`, `ResolveTenant`, `HandleAppearance`, `HandleInertiaRequests`

The subdomain group domain pattern: `{tenant}.` + the hostname extracted from `config('app.url')` (e.g. `{tenant}.nexora.test` or `{tenant}.nexora.app`).

Extract a helper or use `config('app.url')` parsing to build the domain pattern string consistently — reuse the same logic already in `ResolveTenant::extractSubdomain()`.

Require the sub-route files from within the correct group:
- `routes/checkout.php` and `routes/onboarding.php` → included inside public group
- `routes/settings.php` → included inside subdomain group

---

## Phase 2: New Middleware — `RedirectIfAuthenticated` (root domain)

Create `app/Http/Middleware/RedirectToTenantIfAuthenticated.php`:
- Runs on public-domain routes only (registered in the public route group as middleware)
- If the request user is authenticated and has tenant memberships:
  - If 1 tenant → redirect to `{slug}.{baseDomain}/dashboard`
  - If >1 tenant → redirect to root `/tenants` (picker page)
- If no memberships or not authenticated → pass through

Apply this middleware to specific root-domain routes where it makes sense (e.g. `GET /` home, `GET /login`), not the entire public group.

---

## Phase 3: Tenant Picker Page

New page for multi-tenant users arriving at root domain after login.

**Controller**: `app/Http/Controllers/TenantPickerController.php`
- `show()`: query `$user->tenantMemberships()->with('tenant')->get()`, return Inertia page with tenant list

**Route**: `GET /tenants` (public domain group, `auth` middleware)

**Vue page**: `resources/js/pages/TenantPicker.vue`
- Lists tenant cards (name, slug)
- Each card links directly to `{slug}.{baseDomain}/dashboard` (absolute URL)
- Uses `AuthLayout` or a minimal layout — not AppLayout (no sidebar)
- Shared prop `auth.tenantBaseUrl` (see Phase 5) used to construct links

---

## Phase 4: Onboarding Redirect Fix

**File**: `app/Http/Controllers/OnboardingController.php` — `store()` method

After setting up the tenant, instead of `redirect()->route('dashboard')`:

```php
$tenantBaseUrl = 'https://' . $tenant->slug . '.' . parse_url(config('app.url'), PHP_URL_HOST);
return redirect($tenantBaseUrl . '/dashboard');
```

Reuse the same base domain extraction pattern — consider extracting a small helper method or using a shared utility (e.g. `Tenancy::tenantUrl(Tenant $tenant, string $path)` static-style method or a standalone helper in `app/Support/`).

---

## Phase 5: Inertia Shared Data

**File**: `app/Http/Middleware/HandleInertiaRequests.php` — `share()` method

Add a new shared prop `tenant`:
```php
'tenant' => function () {
    $tenant = app(Tenancy::class)->get();
    if ($tenant === null) {
        return null;
    }
    $host = parse_url(config('app.url'), PHP_URL_HOST);
    $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
    return [
        'slug' => $tenant->slug,
        'name' => $tenant->name,
        'baseUrl' => "{$scheme}://{$tenant->slug}.{$host}",
    ];
},
```

**TypeScript types**: update `resources/js/types/index.d.ts` or a relevant types file to add `tenant` to the shared props type.

---

## Phase 6: Frontend URL Adaptation

The Wayfinder-generated route functions in `@/routes/` currently produce root-relative URLs (e.g. `/dashboard`). When running in a tenant subdomain context, Wayfinder calls will naturally resolve against the current subdomain hostname in the browser — **no Wayfinder changes are needed** since the browser uses the full current URL.

However, links that cross from root domain → tenant subdomain (e.g. tenant picker, onboarding success) must use absolute URLs. These should use the `tenant.baseUrl` shared prop.

**`AppSidebar.vue`** — the `dashboard()` Wayfinder call already works correctly on a subdomain (resolves to `/dashboard` relative to the tenant subdomain). No change needed.

**`UserMenuContent.vue`** — logout: after logout, redirect to root domain login (not subdomain). Update the logout redirect on the backend (`LoginResponse` or Fortify config) to point to the root domain `login` route.

**`pages/auth/Login.vue`** — this page lives at `/login` on both the root domain and tenant subdomains. No structural change needed; the Fortify route group handles both.

**`pages/onboarding/Setup.vue`** — fix the subdomain preview: it currently hardcodes `nexora.io`; change it to derive the base domain from a shared prop or from `window.location.hostname`. Pass the base domain from the `HandleInertiaRequests` shared data or via a controller prop.

---

## Phase 7: Tenant-Specific Login

Tenant subdomains should serve their own `/login` route so users can log in with tenant context (branding, slug display). The existing Fortify login route is already accessible on the subdomain (since it's in the `web` middleware group); the subdomain route group should re-expose a named tenant login that renders the same `auth/Login` page, potentially passing the tenant name via Inertia shared data.

No new Vue page needed — the same `Login.vue` page can show tenant name if `tenant` is in the shared props.

---

## Phase 8: Tests

**File**: `tests/Feature/Tenancy/TenantResolutionTest.php` — update existing tests that rely on the global `ResolveTenant` middleware being available on all routes. Tests that hit `appUrl()` (root domain) must not expect tenant resolution unless they are using the tenant route group.

**New test file**: `tests/Feature/Routing/SubdomainRoutingTest.php`
- Root domain `/` → renders Welcome (no tenant)
- Root domain `/dashboard` → 404 (not defined on root domain)
- Subdomain `/dashboard` (unauthenticated) → redirect to `{tenant}/login` or root login
- Subdomain `/dashboard` (authenticated member) → 200
- Root domain GET `/` with authenticated single-tenant user → redirect to `{slug}.domain/dashboard`
- Root domain GET `/` with authenticated multi-tenant user → redirect to `/tenants`
- Root domain GET `/tenants` (unauthenticated) → redirect to login
- After onboarding store → redirects to `{slug}.domain/dashboard`

Reuse existing helpers: `tenantUrl(string $slug, string $path)` and `appUrl(string $path)` from `tests/Pest.php`.

---

## Critical Files

| File | Change |
|---|---|
| `bootstrap/app.php` | Remove global `ResolveTenant` prepend |
| `routes/web.php` | Split into public + subdomain domain groups |
| `routes/checkout.php` | Move into public domain group (via require) |
| `routes/onboarding.php` | Move into public domain group (via require) |
| `routes/settings.php` | Move into subdomain domain group (via require) |
| `app/Http/Middleware/RedirectToTenantIfAuthenticated.php` | New middleware |
| `app/Http/Controllers/TenantPickerController.php` | New controller |
| `resources/js/pages/TenantPicker.vue` | New Vue page |
| `app/Http/Controllers/OnboardingController.php` | Fix redirect to subdomain |
| `app/Http/Middleware/HandleInertiaRequests.php` | Add `tenant` shared prop |
| `resources/js/pages/onboarding/Setup.vue` | Fix hardcoded domain preview |
| `tests/Feature/Tenancy/TenantResolutionTest.php` | Update for new route structure |
| `tests/Feature/Routing/SubdomainRoutingTest.php` | New routing tests |

---

## Verification

1. Run existing tests to confirm nothing is broken before changes: `vendor/bin/sail artisan test --compact`
2. After implementation, run updated tests: `vendor/bin/sail artisan test --compact --filter=SubdomainRouting`
3. Run full test suite: `vendor/bin/sail artisan test --compact`
4. Manually verify in browser:
   - `nexora.test/` → Welcome page
   - `nexora.test/dashboard` → 404
   - `acme.nexora.test/dashboard` (logged in) → Dashboard
   - `acme.nexora.test/dashboard` (logged out) → redirected to login
   - `nexora.test/` (logged in, single tenant) → redirected to `acme.nexora.test/dashboard`
   - After onboarding → lands on `{slug}.nexora.test/dashboard`
