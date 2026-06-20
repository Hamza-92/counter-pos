<?php

namespace App\Http\Middleware;

use App\Models\StoreSetting;
use Closure;
use Illuminate\Http\Request;

class EnsureStoreEnabled
{
    /**
     * Abort with 404 if the online store is disabled in settings.
     */
    public function handle(Request $request, Closure $next)
    {
        $settings = StoreSetting::first();

        if (! $settings || ! $settings->enabled) {
            abort(404);
        }

        return $next($request);
    }
}













