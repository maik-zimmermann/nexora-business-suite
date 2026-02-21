<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Model;

class CheckoutSession extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'email',
        'module_slugs',
        'seat_limit',
        'usage_quota',
        'billing_interval',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module_slugs' => 'array',
            'billing_interval' => BillingInterval::class,
            'seat_limit' => 'integer',
            'usage_quota' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
