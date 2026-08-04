<?php

// app/Http/Middleware/SetSessionConfig.php

namespace App\Http\Middleware;

use Closure;
use Config;

class SetSessionConfig
{
    public function handle($request, Closure $next)
    {
        // Shared-mode host resolution has already selected an isolated control
        // or tenant cookie and database connection. Do not replace it with the
        // legacy standalone `web_session` cookie here.
        if (config('tenancy.enabled', false)) {
            return $next($request);
        }

        if ($request->is('online_store') || $request->is('online_store/*')) {
            Config::set('session.path', '/online_store');
            Config::set('session.cookie', 'store_session');
        } else {
            Config::set('session.path', '/');
            Config::set('session.cookie', 'web_session');
        }

        return $next($request);
    }
}
