<?php

namespace App\Tenancy;

use App\Models\ControlPlane\Domain;
use Illuminate\Support\Facades\Cache;

final class TenantResolver
{
    public function __construct(
        private readonly HostNormalizer $normalizer,
        private readonly TenantAccessEvaluator $accessEvaluator,
    ) {
    }

    public function resolve(string $host): ?TenantContext
    {
        $normalizedHost = $this->normalizer->normalize($host);
        $cacheKey = 'tenancy:domain:'.hash('sha256', $normalizedHost);

        $cache = Cache::store(config('tenancy.resolution_cache_store', 'file'));
        $domainId = $cache->remember(
            $cacheKey,
            max(1, (int) config('tenancy.resolution_cache_seconds', 30)),
            static fn () => Domain::query()->where('normalized_host', $normalizedHost)->value('id') ?: '__missing__'
        );

        if ($domainId === '__missing__') {
            return null;
        }

        $domain = Domain::query()
            ->with(['tenant.subscriptions', 'tenant.primaryDomain'])
            ->find($domainId);

        if ($domain === null || $domain->tenant === null) {
            return null;
        }

        if (config('tenancy.require_verified_domain', true) && $domain->verified_at === null) {
            return null;
        }

        $tenant = $domain->tenant;

        return new TenantContext(
            tenantId: $tenant->id,
            tenantName: $tenant->name,
            host: $normalizedHost,
            primaryHost: $tenant->primaryDomain?->normalized_host ?? $normalizedHost,
            status: $tenant->status,
            access: $this->accessEvaluator->evaluate($tenant),
        );
    }

    public function forget(string $host): void
    {
        $normalizedHost = $this->normalizer->normalize($host);
        Cache::store(config('tenancy.resolution_cache_store', 'file'))
            ->forget('tenancy:domain:'.hash('sha256', $normalizedHost));
    }
}
