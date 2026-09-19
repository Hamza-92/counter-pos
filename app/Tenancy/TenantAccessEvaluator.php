<?php

namespace App\Tenancy;

use App\Models\ControlPlane\Tenant;

final class TenantAccessEvaluator
{
    public function evaluate(Tenant $tenant): TenantAccessDecision
    {
        if ($tenant->status !== 'active') {
            return TenantAccessDecision::deny($tenant->status, $tenant->manual_suspension_reason ?: 'Tenant access is not active.');
        }

        return TenantAccessDecision::allow();
    }
}
