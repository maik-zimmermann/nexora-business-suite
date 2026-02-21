<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use Billable;

    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'slug',
        'is_active',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Get all memberships for this tenant.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * Get all member users for this tenant.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Get the tenant's subscription details.
     */
    public function tenantSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class);
    }

    /**
     * Get all usage records for this tenant.
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    /**
     * Get the current number of seats in use.
     */
    public function currentSeatCount(): int
    {
        return $this->memberships()->count();
    }

    /**
     * Check if the tenant has an available seat.
     */
    public function hasAvailableSeat(): bool
    {
        $subscription = $this->tenantSubscription;

        if (! $subscription) {
            return true;
        }

        return $this->currentSeatCount() < $subscription->seat_limit;
    }
}
