# User Tenant Membership and RBAC

## Overview

Users in Nexora Business Suite belong to one of two contexts: they are either a member of a **Tenant** (an organisation using the platform) or they are a **Nexora Administrator** managing the platform itself. Within each context, access to features is governed by a **Role-Based Access Control (RBAC)** system that assigns roles and permissions to users.

## Goals

- Define how users are associated with tenants and how tenant-less (admin) users are handled.
- Provide a flexible RBAC system that works within both the tenant and administration contexts.
- Ensure that a user's permissions are scoped to the correct context — tenant members cannot act on administration resources and vice versa.
- Keep the RBAC model extensible so new roles and permissions can be added without structural changes.

## Non-Goals / Out of Scope

- Tenant onboarding UI (creating or deleting tenants).
- Billing or subscription management.
- SSO or OAuth-based authentication — this covers internal role assignment only.
- Per-tenant custom permission overrides beyond role assignment.

---

## User Contexts

Every user belongs to exactly one of the following contexts:

### 1. Tenant Member

- The user is associated with one or more tenants via a membership record.
- Each membership defines the user's role within that tenant.
- When a tenant-context request is resolved, the user's active role is the one linked to that tenant.

### 2. Nexora Administrator

- The user is not a member of any tenant.
- These users have access to platform-wide administration features such as managing tenants, managing global configuration, and viewing platform-wide analytics.
- Nexora administrators must not be able to impersonate or act within tenant contexts unless explicitly granted.

---

## Membership Model

A pivot/membership record links a user to a tenant with a role. At a minimum it should store:

| Field        | Description                                      |
|--------------|--------------------------------------------------|
| `id`         | UUID primary key                                 |
| `user_id`    | Foreign key to the `users` table                 |
| `tenant_id`  | Foreign key to the `tenants` table               |
| `role_id`    | Foreign key to the `roles` table                 |
| Timestamps   | `created_at`, `updated_at`                       |

- A user may be a member of multiple tenants, each with a different role.
- A user cannot hold two memberships in the same tenant simultaneously.

---

## RBAC Design

### Roles

A `Role` represents a named set of permissions. Roles are scoped to a context: either `tenant` or `administration`.

| Field        | Description                                      |
|--------------|--------------------------------------------------|
| `id`         | UUID primary key                                 |
| `name`       | Human-readable label (e.g. "Owner", "Editor")    |
| `slug`       | Machine-readable identifier (e.g. `owner`)       |
| `context`    | Enum: `tenant` or `administration`               |
| Timestamps   | `created_at`, `updated_at`                       |

### Permissions

A `Permission` represents a single ability (e.g. `tenants.view`, `users.invite`, `billing.manage`).

| Field        | Description                                      |
|--------------|--------------------------------------------------|
| `id`         | UUID primary key                                 |
| `name`       | Human-readable label                             |
| `slug`       | Dot-notation identifier (e.g. `users.invite`)    |
| Timestamps   | `created_at`, `updated_at`                       |

### Role–Permission Assignment

Roles and permissions are linked via a many-to-many relationship. A role may hold multiple permissions; a permission may belong to multiple roles.

### Nexora Administrator Roles

Nexora administrators are assigned an administration-context role directly on the `users` table (via a `role_id` foreign key) or through a separate `admin_role` column. They do not use the tenant membership table.

---

## Permission Checking

The application must be able to answer: *"Does the currently authenticated user have permission X in the current context?"*

- For tenant requests, permissions are resolved from the user's membership role for the active tenant.
- For administration requests, permissions are resolved from the user's administration role.
- A helper (gate, policy, or dedicated service) must centralise this logic so it is not duplicated across controllers.

---

## Default Roles

The following roles should be seeded as part of the initial setup:

### Tenant Roles

| Slug      | Description                                              |
|-----------|----------------------------------------------------------|
| `owner`   | Full access within the tenant; can manage members        |
| `admin`   | Administrative access; cannot transfer ownership         |
| `member`  | Standard access; cannot manage other members             |
| `viewer`  | Read-only access                                         |

### Administration Roles

| Slug              | Description                                          |
|-------------------|------------------------------------------------------|
| `super-admin`     | Full platform access; can manage tenants and admins  |
| `support`         | Can view tenant data for support purposes            |

---

## Middleware & Guard Behaviour

| Scenario                                  | Expected Behaviour                                        |
|-------------------------------------------|-----------------------------------------------------------|
| Authenticated tenant member, valid role   | Resolve membership and apply tenant-scoped permissions    |
| Authenticated user, no tenant membership  | Treat as Nexora administrator if admin role is set        |
| Tenant member accessing admin routes      | Return 403 Forbidden                                      |
| Admin accessing tenant routes             | Return 403 Forbidden (unless explicitly allowed)          |
| Unauthenticated user                      | Redirect to login                                         |

---

## Acceptance Criteria

- [ ] A `TenantMembership` (or equivalent) model exists with the fields described above, along with a migration and factory.
- [ ] A `Role` model exists with `context` scoping, along with a migration, factory, and seeder for default roles.
- [ ] A `Permission` model exists, along with a migration, factory, and seeder for core permissions.
- [ ] Roles and permissions are linked via a seeded many-to-many relationship.
- [ ] A user can be a member of multiple tenants, each with an independent role.
- [ ] Nexora administrators are identifiable without a tenant membership.
- [ ] A centralised permission-checking mechanism is available (gate, policy, or service).
- [ ] Tenant members cannot access administration routes; administrators cannot access tenant routes without membership.
- [ ] All new behaviour is covered by feature tests.
- [ ] No existing tests are broken.

---

## Open Questions

- Should a Nexora administrator be able to temporarily assume a tenant-member role for support purposes (impersonation)? Yes
- Should permissions be assignable directly to users in addition to roles, or only via roles? Only via roles for now
- Should the `owner` role be protected so it cannot be removed if it is the last owner in a tenant? Yes
- Is there a maximum number of members per tenant, or is membership unlimited? For now unlimited
