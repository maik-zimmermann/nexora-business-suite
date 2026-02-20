<?php

namespace App\Models;

use App\Concerns\ProtectsLastOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    /** @use HasFactory<\Database\Factories\TenantMembershipFactory> */
    use HasFactory, ProtectsLastOwner;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'role_id',
    ];

    /**
     * Get the user this membership belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant this membership belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the role assigned to this membership.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
