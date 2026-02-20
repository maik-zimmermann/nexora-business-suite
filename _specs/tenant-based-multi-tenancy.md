# Tenant-Based Multi-Tenancy

## Overview

Nexora Business Suite should support multiple tenants sharing a single application instance. Each tenant is an isolated organisation with their own data, users, and configuration. The application must be able to identify the active tenant on every request — initially via a subdomain slug, with support for a tenant ID header as a secondary resolution strategy.

## Goals

- Establish a foundational multi-tenancy architecture that all future features can build on.
- Ensure tenant data is fully isolated; no tenant can access another tenant's data.
- Allow tenant resolution to be extended without breaking changes.
- Keep tenant context available throughout the request lifecycle (controllers, jobs, middleware, etc.).

## Non-Goals / Out of Scope

- Tenant onboarding UI (creating, editing, or deleting tenants) — this is a separate feature.
- Billing or subscription management per tenant.
- Per-tenant custom domains (beyond subdomain support).
- Database-per-tenant or schema-per-tenant isolation (use shared database with tenant scoping).

---

## Tenant Resolution

Tenant resolution should be handled early in the request lifecycle (middleware). Two strategies must be supported:

### 1. Subdomain Slug (Primary)

- The tenant is identified by the first segment of the hostname.
- Example: `acme.nexora.app` → tenant slug `acme`.
- The root domain (no subdomain, or `www`) should be treated as the marketing/public site — no tenant is resolved.

### 2. Tenant ID Header (Secondary)

- The tenant is identified by a custom HTTP header (e.g. `X-Tenant-ID`).
- This is intended for internal API clients, mobile apps, or server-to-server communication.
- If both a subdomain and a header are present, the subdomain takes precedence.

---

## Tenant Model

A `Tenant` model must be created to represent each tenant organisation. At a minimum it should store:

| Field        | Description                                      |
|--------------|--------------------------------------------------|
| `id`         | UUID primary key                                 |
| `name`       | Human-readable organisation name                 |
| `slug`       | URL-safe identifier used in subdomain resolution |
| `is_active`  | Whether the tenant is active and can be resolved |
| Timestamps   | `created_at`, `updated_at`                       |

The `slug` must be unique and validated to contain only lowercase letters, numbers, and hyphens.

---

## Tenant Context

Once a tenant is resolved, it must be made available globally for the duration of the request. All Eloquent queries on tenant-scoped models should automatically filter by the current tenant (global scope or similar mechanism). The resolved tenant should be accessible from a single location (e.g. a `Tenancy` class or facade) throughout the application.

---

## Middleware Behaviour

| Scenario                              | Expected Behaviour                                      |
|---------------------------------------|---------------------------------------------------------|
| Valid subdomain, active tenant        | Resolve tenant and proceed                              |
| Valid header, active tenant           | Resolve tenant and proceed                              |
| Unknown slug or ID                    | Return 404 or redirect to root                          |
| Inactive tenant                       | Return 403 or a maintenance page                        |
| No subdomain and no header            | No tenant resolved; treat as public/marketing context   |

---

## Acceptance Criteria

- [ ] A `Tenant` model exists with the fields described above, along with a migration, factory, and seeder.
- [ ] A middleware resolves the tenant from the subdomain on each request.
- [ ] A middleware resolves the tenant from the `X-Tenant-ID` header when no subdomain is present.
- [ ] The resolved tenant is accessible throughout the request lifecycle.
- [ ] Requests with an unknown or inactive tenant receive an appropriate error response.
- [ ] Tenant-scoped models automatically filter results to the current tenant.
- [ ] All new behaviour is covered by feature tests.
- [ ] No existing tests are broken.

---

## Open Questions

- Should the `X-Tenant-ID` header require authentication/signing to prevent spoofing, or is it restricted to internal networks only? Yes, it should be authed/signed.
- What should happen on the root domain — a landing page, a login page, or a redirect? A landing page with a redirect to a global login page
- Should tenant resolution failures be logged for monitoring purposes? Yes
