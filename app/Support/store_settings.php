<?php

use App\Tenancy\TenantOptionStore;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('store_settings')) {
    function store_settings(): StoreSetting
    {
        return Cache::remember('store_settings', 600, function () {
            return StoreSetting::query()->first() ?? new StoreSetting;
        });
    }
}

if (! function_exists('tenant_public_path')) {
    function tenant_public_path(string $relative = ''): string
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        if (str_contains($relative, '..') || str_contains($relative, '://')) {
            throw new InvalidArgumentException('Unsafe public file path.');
        }

        if (config('tenancy.enabled', false) && app(\App\Tenancy\TenancyManager::class)->hasTenant()) {
            $tenantId = app(\App\Tenancy\TenancyManager::class)->tenant()->tenantId;
            $root = storage_path('app/public/tenants/'.$tenantId);
            if (! is_dir($root) && ! mkdir($root, 0750, true) && ! is_dir($root)) {
                throw new RuntimeException('Unable to create the tenant public directory.');
            }

            $target = $root.($relative === '' ? '' : '/'.$relative);
            $directory = $relative !== '' && pathinfo($target, PATHINFO_EXTENSION) === '' ? $target : dirname($target);
            if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to create the tenant media directory.');
            }

            return $target;
        }

        return public_path($relative);
    }
}

if (! function_exists('tenant_option')) {
    /**
     * Read a customer-specific encrypted option in shared mode, retaining the
     * historical environment fallback only for standalone installations.
     */
    function tenant_option(string $key, mixed $default = null): mixed
    {
        return app(TenantOptionStore::class)->get($key, $default);
    }
}
