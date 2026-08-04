<?php

namespace App\Tenancy;

final class TenantContext
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $tenantName,
        public readonly string $host,
        public readonly string $primaryHost,
        public readonly string $status,
        public readonly TenantAccessDecision $access,
    ) {
    }
}
