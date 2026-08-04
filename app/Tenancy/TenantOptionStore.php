<?php

namespace App\Tenancy;

use App\Models\TenantOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class TenantOptionStore
{
    private array $resolved = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->hasTenantContext()) {
            return env($key, $default);
        }

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        if (! Schema::hasTable('tenant_options')) {
            return $default;
        }

        $option = TenantOption::query()->where('key', $key)->first();

        return $this->resolved[$key] = $option?->value ?? $default;
    }

    public function putMany(array $values): void
    {
        if (! $this->hasTenantContext()) {
            throw new RuntimeException('Tenant options require an active tenant context.');
        }

        if (! Schema::hasTable('tenant_options')) {
            throw new RuntimeException('Tenant options are not migrated. Run tenant:migrate for this customer.');
        }

        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                TenantOption::query()->updateOrCreate(
                    ['key' => (string) $key],
                    ['value' => $value === null ? null : (string) $value],
                );
                $this->resolved[(string) $key] = $value;
            }
        });
    }

    private function hasTenantContext(): bool
    {
        return config('tenancy.enabled', false)
            && app(TenancyManager::class)->hasTenant();
    }
}
