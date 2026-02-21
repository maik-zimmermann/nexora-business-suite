<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_role_id',
        'onboarding_completed_at',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'admin_role_id' => 'integer',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * Get the administration role assigned to this user.
     */
    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'admin_role_id');
    }

    /**
     * Get all tenant memberships for this user.
     */
    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * Get the membership for a specific tenant.
     */
    public function membershipFor(Tenant $tenant): ?TenantMembership
    {
        return $this->tenantMemberships()->where('tenant_id', $tenant->id)->first();
    }

    /**
     * Check if this user is a Nexora administrator.
     */
    public function isAdministrator(): bool
    {
        return $this->admin_role_id !== null;
    }

    /**
     * Check if this user is a member of the given tenant.
     */
    public function isMemberOf(Tenant $tenant): bool
    {
        return $this->tenantMemberships()->where('tenant_id', $tenant->id)->exists();
    }

    /**
     * Check if this user has a specific role in the given tenant.
     */
    public function hasTenantRole(Tenant $tenant, string $roleSlug): bool
    {
        return $this->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->exists();
    }

    /**
     * Get all permission slugs for the user in their current context.
     *
     * @return Collection<int, string>
     */
    public function allPermissions(?Tenant $tenant = null): Collection
    {
        if ($this->isAdministrator()) {
            return $this->adminRole->permissions->pluck('slug');
        }

        $tenant ??= app(Tenancy::class)->get();

        if ($tenant === null) {
            return collect();
        }

        $membership = $this->membershipFor($tenant);

        return $membership?->role->permissions->pluck('slug') ?? collect();
    }

    /**
     * Check if the user has a permission in their current context.
     */
    public function hasPermissionTo(string $permissionSlug, ?Tenant $tenant = null): bool
    {
        return $this->allPermissions($tenant)->contains($permissionSlug);
    }

    /**
     * Check if the user has completed onboarding.
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }
}
