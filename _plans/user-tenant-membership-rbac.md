# Plan: User Tenant Membership and RBAC

## Context

The multi-tenancy foundation (Tenant model, resolution middleware, `Tenancy` singleton, `BelongsToTenant` trait) is already in place, but there is **no link between users and tenants**. This plan adds:
- A membership system connecting users to tenants with roles
- An RBAC system (roles + permissions) that works in both tenant and administration contexts
- Middleware to guard admin-only and tenant-member-only routes
- Integration with Laravel's Gate system for `$user->can()` permission checks

## Phase 1: Enum + Migrations

### 1.1 Create `app/Enums/RoleContext.php`
- String-backed enum: `Tenant = 'tenant'`, `Administration = 'administration'`

### 1.2 Create migration: `create_roles_table`
- `id` (bigint auto-increment), `name`, `slug` (unique), `context` (string), `is_default` (boolean), timestamps

### 1.3 Create migration: `create_permissions_table`
- `id` (bigint auto-increment), `name`, `slug` (unique), `group` (nullable string), timestamps

### 1.4 Create migration: `create_permission_role_table`
- Pivot: `permission_id` + `role_id` as composite primary key, both with FK + cascadeOnDelete

### 1.5 Create migration: `create_tenant_memberships_table`
- `id`, `user_id` (FK to users), `tenant_id` (uuid, FK to tenants), `role_id` (FK to roles), timestamps
- **Unique constraint** on `[user_id, tenant_id]` — one role per user per tenant
- Note: `tenant_id` must be `uuid` type (not `foreignId`) since `tenants.id` is UUID

### 1.6 Create migration: `add_admin_role_id_to_users_table`
- Nullable `admin_role_id` FK to `roles`, nullOnDelete

### 1.7 Run migrations

## Phase 2: Models

### 2.1 Create `app/Models/Role.php`
- Fillable: name, slug, context, is_default
- Cast `context` to `RoleContext` enum, `is_default` to boolean
- Relationships: `permissions()` (belongsToMany Permission)
- Method: `hasPermission(string $slug): bool` (checks loaded collection)
- Scopes: `scopeTenant()`, `scopeAdministration()`

### 2.2 Create `app/Models/Permission.php`
- Fillable: name, slug, group
- Relationships: `roles()` (belongsToMany Role)

### 2.3 Create `app/Exceptions/LastOwnerException.php`
- Simple RuntimeException subclass

### 2.4 Create `app/Concerns/ProtectsLastOwner.php`
- Trait with `bootProtectsLastOwner()` — model events on `updating` and `deleting`
- Throws `LastOwnerException` when attempting to remove/change the last owner of a tenant
- Follows existing `BelongsToTenant` trait pattern

### 2.5 Create `app/Models/TenantMembership.php`
- Uses `HasFactory`, `ProtectsLastOwner`
- Fillable: user_id, tenant_id, role_id
- Relationships: `user()`, `tenant()`, `role()` (all BelongsTo)
- Does NOT use `BelongsToTenant` (cross-tenant by nature)

### 2.6 Modify `app/Models/User.php`
- Add `admin_role_id` to `$fillable`
- New relationships: `adminRole()` (BelongsTo Role), `tenantMemberships()` (HasMany)
- New methods:
  - `membershipFor(Tenant): ?TenantMembership`
  - `isAdministrator(): bool`
  - `isMemberOf(Tenant): bool`
  - `hasTenantRole(Tenant, string): bool`
  - `allPermissions(?Tenant): Collection<string>`
  - `hasPermissionTo(string, ?Tenant): bool`

### 2.7 Modify `app/Models/Tenant.php`
- New relationships: `memberships()` (HasMany TenantMembership), `members()` (BelongsToMany User via tenant_memberships)

## Phase 3: Factories

### 3.1 `database/factories/RoleFactory.php`
- Default: tenant context. States: `owner()`, `admin()`, `member()`, `viewer()`, `superAdmin()`, `support()`

### 3.2 `database/factories/PermissionFactory.php`

### 3.3 `database/factories/TenantMembershipFactory.php`
- Default references User::factory, Tenant::factory, Role::factory
- States: `forTenant(Tenant)`, `withRole(Role)`

### 3.4 Modify `database/factories/UserFactory.php`
- Add `admin_role_id => null` to definition
- Add `administrator()` state

## Phase 4: Seeders

### 4.1 Create `database/seeders/RoleSeeder.php`
- Seeds 6 default roles using `firstOrCreate` (idempotent):
  - Tenant: owner, admin, member, viewer
  - Administration: super-admin, support

### 4.2 Create `database/seeders/PermissionSeeder.php`
- Seeds core permissions and assigns them to roles via `sync`:
  - Tenant: `members.view`, `members.manage`, `members.remove`, `settings.view`, `settings.manage`, `tenant.manage`
  - Admin: `tenants.view`, `tenants.manage`, `users.view`, `users.manage`, `impersonate`

### 4.3 Modify `database/seeders/DatabaseSeeder.php`
- Call RoleSeeder + PermissionSeeder before TenantSeeder
- Assign test user as owner of Acme Corp
- Create an admin user (`admin@example.com`) with super-admin role

## Phase 5: Gate Integration + Middleware

### 5.1 Modify `app/Providers/AppServiceProvider.php`
- Add `Gate::before()` in `boot()`:
  - Super-admin bypasses all checks (returns true)
  - Otherwise delegates to `$user->hasPermissionTo($ability)`
  - Returns `null` to allow standard policies to still work

### 5.2 Create `app/Http/Middleware/RequiresAdministrator.php`
- Returns 403 if user is not an administrator

### 5.3 Create `app/Http/Middleware/RequiresTenantMembership.php`
- Returns 403 if user is not a member of the current tenant

### 5.4 Modify `bootstrap/app.php`
- Register middleware aliases: `tenant.member` → RequiresTenantMembership, `admin` → RequiresAdministrator

## Phase 6: Tests

All Pest v4, using RefreshDatabase, following existing patterns from `tests/Feature/Tenancy/TenantResolutionTest.php`.

| Test File | Covers |
|-----------|--------|
| `tests/Unit/Enums/RoleContextTest.php` | Enum values |
| `tests/Feature/Rbac/RoleTest.php` | Role creation, scopes, permissions relationship, hasPermission |
| `tests/Feature/Rbac/TenantMembershipTest.php` | Membership CRUD, unique constraint, owner protection |
| `tests/Feature/Rbac/UserPermissionTest.php` | isAdministrator, isMemberOf, hasPermissionTo, Gate integration, super-admin bypass |
| `tests/Feature/Rbac/RequiresAdministratorTest.php` | Middleware blocks non-admins, passes admins |
| `tests/Feature/Rbac/RequiresTenantMembershipTest.php` | Middleware blocks non-members, passes members |
| `tests/Feature/Rbac/SeederTest.php` | Default roles/permissions seeded correctly, idempotent |

## Phase 7: Finalize
- Run `vendor/bin/sail bin pint --dirty --format agent`
- Run `vendor/bin/sail artisan test --compact` to verify all tests pass

## Verification
1. Run migrations: `vendor/bin/sail artisan migrate`
2. Run seeders: `vendor/bin/sail artisan db:seed`
3. Run full test suite: `vendor/bin/sail artisan test --compact`
4. Verify via tinker: create a user, assign membership, check `$user->can('members.view')`
