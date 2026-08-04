<?php

namespace App\Tenancy;

use App\Models\ControlPlane\Tenant;
use App\Tenancy\Exceptions\TenantDatabaseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

final class TenantQueueBootstrapper
{
    private array $previousCache = [];

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
        $this->previousCache = [
            'default' => config('cache.default'),
            'connection' => config('cache.stores.database.connection'),
            'prefix' => config('cache.prefix'),
        ];
        Config::set('cache.default', 'database');
        Config::set('cache.stores.database.connection', 'tenant');
        Config::set('cache.prefix', 'tenant_'.$tenantId.'_cache');
        Cache::forgetDriver('database');
    }

    public function reset(): void
    {
        if ($this->previousCache !== []) {
            Config::set('cache.default', $this->previousCache['default']);
            Config::set('cache.stores.database.connection', $this->previousCache['connection']);
            Config::set('cache.prefix', $this->previousCache['prefix']);
            Cache::forgetDriver('database');
            $this->previousCache = [];
        }
        $this->database->reset();
        $this->tenancy->reset();
    }
}
