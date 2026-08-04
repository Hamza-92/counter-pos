<?php

namespace App\Http\Middleware;

use Closure;

class BlockUnsafeSharedOperations
{
    public function handle($request, Closure $next)
    {
        if (config('tenancy.enabled', false)) {
            foreach ((array) config('tenancy.unsafe_shared_paths', []) as $pattern) {
                if ($request->is($pattern)) {
                    abort(404);
                }
            }
        }

        return $next($request);
    }
}
