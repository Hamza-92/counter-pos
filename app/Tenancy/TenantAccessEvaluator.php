<?php

namespace App\Tenancy;

use App\Models\ControlPlane\Tenant;
use Carbon\CarbonImmutable;

final class TenantAccessEvaluator
{
    public function evaluate(Tenant $tenant, ?CarbonImmutable $now = null): TenantAccessDecision
    {
        if ($tenant->status !== 'active') {
            return TenantAccessDecision::deny($tenant->status, $tenant->manual_suspension_reason ?: 'Tenant access is not active.');
        }

        if (! config('tenancy.require_subscription', true)) {
            return TenantAccessDecision::allow();
        }

        $now ??= CarbonImmutable::now('UTC');
        $subscriptions = $tenant->relationLoaded('subscriptions')
            ? $tenant->subscriptions
            : $tenant->subscriptions()->orderByDesc('ends_at')->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->status === 'cancelled' || $subscription->starts_at?->isAfter($now)) {
                continue;
            }

            if (in_array($subscription->status, ['active', 'grace'], true) && $subscription->ends_at?->isAfter($now)) {
                return TenantAccessDecision::allow('active');
            }

            if ($subscription->grace_ends_at?->isAfter($now)) {
                return TenantAccessDecision::allow('grace');
            }
        }

        return TenantAccessDecision::deny('expired', 'The subscription is inactive or expired.');
    }
}
