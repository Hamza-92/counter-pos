<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    /** Supported locales */
    protected array $supported = ['en', 'fr', 'es', 'ar'];

    public function handle($request, Closure $next)
    {
        // Prefer session, fall back to cookie, else app default
        $locale = Session::get('locale')
            ?: $request->cookie('locale')
            ?: config('app.locale');

        if (! in_array($locale, $this->supported, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
