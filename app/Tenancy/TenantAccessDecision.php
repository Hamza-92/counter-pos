<?php

namespace App\Tenancy;

final class TenantAccessDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $state,
        public readonly ?string $reason = null,
    ) {
    }

    public static function allow(string $state = 'active'): self
    {
        return new self(true, $state);
    }

    public static function deny(string $state, string $reason): self
    {
        return new self(false, $state, $reason);
    }
}
