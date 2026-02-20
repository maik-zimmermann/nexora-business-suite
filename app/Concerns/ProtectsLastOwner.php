<?php

namespace App\Concerns;

use App\Exceptions\LastOwnerException;
use App\Models\Role;

trait ProtectsLastOwner
{
    /**
     * Boot the trait — prevent removing or reassigning the last owner of a tenant.
     */
    public static function bootProtectsLastOwner(): void
    {
        static::updating(function ($membership): void {
            $originalRoleId = $membership->getOriginal('role_id');
            $ownerRole = Role::where('slug', 'owner')->first();

            if ($ownerRole
                && $originalRoleId === $ownerRole->id
                && $membership->role_id !== $ownerRole->id
            ) {
                $remainingOwners = static::where('tenant_id', $membership->tenant_id)
                    ->where('role_id', $ownerRole->id)
                    ->where('id', '!=', $membership->id)
                    ->count();

                if ($remainingOwners === 0) {
                    throw new LastOwnerException('Cannot remove the last owner of a tenant.');
                }
            }
        });

        static::deleting(function ($membership): void {
            $ownerRole = Role::where('slug', 'owner')->first();

            if ($ownerRole && $membership->role_id === $ownerRole->id) {
                $remainingOwners = static::where('tenant_id', $membership->tenant_id)
                    ->where('role_id', $ownerRole->id)
                    ->where('id', '!=', $membership->id)
                    ->count();

                if ($remainingOwners === 0) {
                    throw new LastOwnerException('Cannot remove the last owner of a tenant.');
                }
            }
        });
    }
}
