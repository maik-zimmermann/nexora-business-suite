<?php

namespace App\Support;

use App\Models\Tenant;

class Tenancy
{
    private ?Tenant $tenant = null;

    /**
     * Set the resolved tenant for the current request.
     */
    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the resolved tenant, or null if none is set.
     */
    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the resolved tenant, or throw if none is set.
     *
     * @throws \RuntimeException
     */
    public function current(): Tenant
    {
        if ($this->tenant === null) {
            throw new \RuntimeException('No tenant resolved for this request.');
        }

        return $this->tenant;
    }

    /**
     * Check whether a tenant has been resolved.
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Reset the resolved tenant. Used in tests to reset state between assertions.
     */
    public function flush(): void
    {
        $this->tenant = null;
    }
}
