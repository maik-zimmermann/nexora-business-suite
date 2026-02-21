<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'stripe_monthly_price_id',
        'stripe_annual_price_id',
        'monthly_price_cents',
        'annual_price_cents',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'monthly_price_cents' => 'integer',
            'annual_price_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
