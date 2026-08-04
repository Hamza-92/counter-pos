<?php

namespace App\Tenancy;

use LogicException;

final class TenancyManager
{
    private ?TenantContext $tenant = null;

    private ?ControlPlaneContext $controlPlane = null;

    public function enterTenant(TenantContext $tenant): void
    {
        if ($this->tenant !== null || $this->controlPlane !== null) {
            throw new LogicException('A tenancy context is already active.');
        }

        $this->tenant = $tenant;
    }

    public function enterControlPlane(ControlPlaneContext $controlPlane): void
    {
        if ($this->tenant !== null || $this->controlPlane !== null) {
            throw new LogicException('A tenancy context is already active.');
        }

        $this->controlPlane = $controlPlane;
    }

    public function tenant(): TenantContext
    {
        return $this->tenant ?? throw new LogicException('No tenant context is active.');
    }

    public function controlPlane(): ControlPlaneContext
    {
        return $this->controlPlane ?? throw new LogicException('No control-plane context is active.');
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function isControlPlane(): bool
    {
        return $this->controlPlane !== null;
    }

    public function reset(): void
    {
        $this->tenant = null;
        $this->controlPlane = null;
    }
}
