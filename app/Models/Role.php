<?php

namespace App\Models;

use App\Enums\RoleContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'context',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => RoleContext::class,
            'is_default' => 'boolean',
        ];
    }

    /**
     * The permissions that belong to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Check if the role has a specific permission by slug.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions->contains('slug', $permissionSlug);
    }

    /**
     * Scope to tenant-context roles only.
     */
    public function scopeTenant(Builder $query): Builder
    {
        return $query->where('context', RoleContext::Tenant);
    }

    /**
     * Scope to administration-context roles only.
     */
    public function scopeAdministration(Builder $query): Builder
    {
        return $query->where('context', RoleContext::Administration);
    }
}
