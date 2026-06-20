<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class SetStoreSessionConfig
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Set the session configuration for the store module
        Config::set('session', config('session.store'));

        // Start the session
        Session::start();

        // Set the default guard to 'store'
        Auth::setDefaultDriver('store');

        return $next($request);
    }
}
