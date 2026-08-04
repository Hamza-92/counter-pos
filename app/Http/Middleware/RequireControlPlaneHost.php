<?php

namespace App\Http\Middleware;

use App\Tenancy\TenancyManager;
use Closure;

class RequireControlPlaneHost
{
    public function __construct(private readonly TenancyManager $tenancy)
    {
    }

    public function handle($request, Closure $next)
    {
        if (! config('tenancy.enabled', false) || ! $this->tenancy->isControlPlane()) {
            abort(404);
        }

        return $next($request);
    }
}
