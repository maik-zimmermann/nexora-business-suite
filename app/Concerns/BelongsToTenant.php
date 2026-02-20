<?php

namespace App\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait — add global scope and auto-assign tenant on creation.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $model->tenant_id = app(Tenancy::class)->current()->id;
        });
    }

    /**
     * Initialize the trait — merge tenant_id into fillable.
     */
    public function initializeBelongsToTenant(): void
    {
        $this->fillable = array_merge($this->fillable, ['tenant_id']);
    }

    /**
     * Get the tenant that owns this model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
