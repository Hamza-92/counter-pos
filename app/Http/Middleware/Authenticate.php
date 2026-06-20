<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Storage;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // If setup not completed, always redirect to setup
        if (! Storage::disk('public')->exists('installed')) {
            return route('setup');
        }

        // Handle Online Store routes
        if ($request->is('online_store') || $request->is('online_store/*')) {
            // If store expects JSON (API calls), don’t redirect — return 401
            if ($request->expectsJson()) {
                return null;
            }

            // Redirect to the store login page
            return url('/online_store/login');
        }

        // Client portal API: separate auth — never redirect to admin login; return 401
        if ($request->is('api/portal') || $request->is('api/portal/*')) {
            return null;
        }

        // Default for admin panel or web
        if (! $request->expectsJson()) {
            return route('login');
        }

        return null; // For API calls (JSON)
    }
}
