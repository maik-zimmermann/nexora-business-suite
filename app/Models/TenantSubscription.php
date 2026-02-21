<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'stripe_subscription_id',
        'status',
        'billing_interval',
        'module_slugs',
        'seat_limit',
        'seat_stripe_price_id',
        'usage_quota',
        'usage_stripe_price_id',
        'trial_ends_at',
        'read_only_ends_at',
        'current_period_end',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module_slugs' => 'array',
            'status' => SubscriptionStatus::class,
            'billing_interval' => BillingInterval::class,
            'seat_limit' => 'integer',
            'usage_quota' => 'integer',
            'trial_ends_at' => 'datetime',
            'read_only_ends_at' => 'datetime',
            'current_period_end' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing]);
    }

    public function isReadOnly(): bool
    {
        return $this->status === SubscriptionStatus::ReadOnly;
    }

    public function isLocked(): bool
    {
        return $this->status === SubscriptionStatus::Locked;
    }

    public function isPastDue(): bool
    {
        return $this->status === SubscriptionStatus::PastDue;
    }

    public function currentUsage(): int
    {
        return (int) $this->tenant->usageRecords()
            ->when($this->current_period_end, function ($query) {
                $query->where('recorded_at', '>=', $this->current_period_end->subMonth());
            })
            ->sum('quantity');
    }

    public function isOverQuota(): bool
    {
        return $this->currentUsage() > $this->usage_quota;
    }
}
