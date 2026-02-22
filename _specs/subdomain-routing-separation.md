# Subdomain Routing Separation

## Overview

The application serves two distinct contexts: the public-facing context (landing page, plan builder, marketing pages) and the tenant application context (dashboard, tenant settings, and all authenticated tenant features). These two contexts must be separated at the routing level using domain groups — the public context is served from the root domain (e.g. `nexora.app`), while the tenant context is served exclusively from subdomains (e.g. `acme.nexora.app`). Accessing a tenant route from the root domain, or a public route without a subdomain when one is required, must result in a correct redirect or error.

## Goals

- Enforce that tenant routes (dashboard, settings, etc.) are only reachable via a subdomain.
- Enforce that public routes (landing, plan builder, etc.) are only reachable from the root domain.
- Handle redirects gracefully: unauthenticated users hitting subdomain routes are redirected to login on the correct domain; users on the root domain who are already in a tenant session are redirected to their subdomain.
- Adapt the frontend so links, forms, and navigation use subdomain-aware URLs when a tenant is active.
- Ensure the `ResolveTenant` middleware only runs on subdomain routes.

## Non-Goals / Out of Scope

- Custom domains per tenant (beyond subdomains).
- Multi-tenant admin panel routing.
- Changes to the tenant resolution logic itself (subdomain slug / header strategies remain as-is).

---

## Routing Structure

### Public Domain Group (`nexora.app`)

Routes served only from the root domain, with no tenant context required:

- Landing / marketing page
- Plan builder
- Global login / registration
- Password reset
- Any other pre-authentication pages

### Tenant Subdomain Group (`{tenant}.nexora.app`)

Routes served only from a tenant subdomain, requiring a resolved tenant:

- Tenant dashboard
- Tenant settings
- All authenticated tenant features
- Any routes currently relying on the `BelongsToTenant` scope

---

## Redirect Behaviour

| Scenario | Expected Behaviour |
|---|---|
| Root domain + authenticated with active tenant | Redirect to `{tenant}.nexora.app/dashboard` |
| Root domain + not authenticated | Serve public/marketing page |
| Subdomain + valid tenant + authenticated | Serve tenant app normally |
| Subdomain + valid tenant + not authenticated | Redirect to login on `{tenant}.nexora.app/login` (or root domain login) |
| Subdomain + unknown tenant slug | 404 |
| Subdomain + inactive tenant | 403 or maintenance page |
| Accessing a public-only route via subdomain | Redirect to root domain equivalent |

---

## Middleware Placement

- The `ResolveTenant` middleware must only be registered on the subdomain route group, not globally.
- An additional middleware on the root domain group should detect an authenticated user with an active tenant and redirect them to their subdomain.
- Auth middleware stays on individual route groups as appropriate.

---

## Frontend Adaptation

- Any in-app navigation (sidebar, nav links, breadcrumbs, etc.) that links to tenant routes must generate URLs with the subdomain prefix when a tenant is active.
- Forms that submit to tenant routes must target the subdomain URL.
- The Inertia shared data (passed via `HandleInertiaRequests`) should include the current tenant's subdomain URL base so the frontend can construct correct URLs without hardcoding domain logic.
- Public pages (landing, plan builder) must not include subdomain links; they link only to root-domain routes.
- The plan builder CTA and any "Go to dashboard" or "Start trial" links must redirect to the correct subdomain after tenant creation/login.

---

## Acceptance Criteria

- [ ] Laravel route files are organised into a public domain group and a subdomain domain group.
- [ ] The `ResolveTenant` middleware is applied only to the subdomain route group.
- [ ] Visiting a tenant dashboard route from the root domain redirects to the correct subdomain.
- [ ] Visiting a public route from a tenant subdomain redirects to the root domain equivalent.
- [ ] An authenticated user on the root domain with an active tenant session is redirected to their tenant subdomain.
- [ ] An unauthenticated user visiting a protected subdomain route is redirected to the login page on the correct domain.
- [ ] Unknown subdomain slugs return a 404 response.
- [ ] The Inertia shared data exposes the tenant's base URL for use in the frontend.
- [ ] Frontend navigation components generate subdomain-aware URLs when a tenant is active.
- [ ] All redirect and routing behaviour is covered by feature tests.
- [ ] No existing tests are broken.

---

## Open Questions

- Should the global login page live on the root domain only, or should each subdomain also have its own `/login` that mirrors the root login? Recommendation: tenant subdomains have their own `/login` that is tenant-context-aware (e.g. can show tenant branding). I want both, a global login page that redirects to the correct tenant subdomain, and a tenant-specific login page that shows tenant branding.
- When a user belongs to multiple tenants, which subdomain should they be redirected to from the root domain after login? Recommendation: show a tenant picker page on the root domain. Show a tenant picker page on the root domain after login, and redirect to the tenant subdomain after selection.
- Should the plan builder redirect to the tenant subdomain immediately after checkout, or after the user manually navigates there? The onboarding flow should be tenant-aware, so it should redirect to the tenant subdomain after checkout.
