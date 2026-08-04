<?php

namespace App\Tenancy;

use App\Models\ControlPlane\Tenant;
use App\Tenancy\Exceptions\TenantDatabaseException;

final class TenantQueueBootstrapper
{
    public function __construct(
        private readonly TenantDatabaseManager $database,
        private readonly TenantAccessEvaluator $access,
        private readonly TenancyManager $tenancy,
    ) {
    }

    public function initialize(string $tenantId): void
    {
        $tenant = Tenant::query()->with(['primaryDomain', 'subscriptions'])->find($tenantId);
        if (! $tenant || ! $tenant->primaryDomain) {
            throw new TenantDatabaseException('Queued tenant context is invalid.');
        }

        $decision = $this->access->evaluate($tenant);
        if (! $decision->allowed) {
            throw new TenantDatabaseException('Queued tenant is not active.');
        }

        $context = new TenantContext(
            $tenant->id,
            $tenant->name,
            $tenant->primaryDomain->normalized_host,
            $tenant->primaryDomain->normalized_host,
            $tenant->status,
            $decision,
        );
        $this->database->initialize($context);
        $this->tenancy->enterTenant($context);
    }

    public function reset(): void
    {
        $this->database->reset();
        $this->tenancy->reset();
    }
}
