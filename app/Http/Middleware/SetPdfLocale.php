<?php

namespace App\Http\Middleware;

use App\Http\Controllers\LocaleSyncController;
use Closure;
use Illuminate\Support\Facades\App;

/**
 * Sets the application locale from the app_locale cookie when the request is for a PDF route,
 * so Blade PDF views use the same language as the Vue app (ar or en).
 * Uses cookie so API can stay stateless and auth (Passport) is not broken.
 */
class SetPdfLocale
{
    public function handle($request, Closure $next)
    {
        $path = $request->path();
        $isPdf = str_contains($path, '_pdf') || str_contains($path, '/pdf');

        if ($isPdf) {
            $locale = $request->cookie(LocaleSyncController::COOKIE_NAME, config('app.locale'));
            $locale = ($locale === 'ar') ? 'ar' : 'en';
            App::setLocale($locale);
        }

        return $next($request);
    }
}
