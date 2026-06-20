<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // If session is not valid, do not redirect
        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            return $next($request);
        }
    
        // Extra safety: ensure user is REALLY authenticated
        if (Auth::guard($guard)->check() && $request->user()) {
            switch ($guard) {
                case 'store':
                    return redirect('/online_store');
                default:
                    return redirect('/');
            }
        }
    
        return $next($request);
    }
    
}
