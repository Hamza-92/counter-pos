<?php

namespace App\Tenancy;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class TenantFilesystemManager
{
    public function publicPath(string $relative = ''): string
    {
        return $this->path('public', $relative);
    }

    public function privatePath(string $relative = ''): string
    {
        return $this->path('private', $relative);
    }

    public function temporaryPath(string $relative = ''): string
    {
        return $this->path('tmp', $relative);
    }

    private function path(string $area, string $relative): string
    {
        $tenantId = app(TenancyManager::class)->tenant()?->tenantId;
        if (! $tenantId || ! Str::isUuid($tenantId)) {
            throw new InvalidArgumentException('A valid tenant context is required for file access.');
        }

        $relative = str_replace('\\', '/', trim($relative));
        if ($relative !== '' && ($relative[0] === '/' || str_contains($relative, '..') || str_contains($relative, '://'))) {
            throw new InvalidArgumentException('Unsafe tenant file path.');
        }

        $base = $area === 'public'
            ? storage_path('app/public/tenants/'.$tenantId)
            : storage_path('app/tenants/'.$tenantId.'/'.$area);

        return $base.($relative === '' ? '' : '/'.$relative);
    }
}
