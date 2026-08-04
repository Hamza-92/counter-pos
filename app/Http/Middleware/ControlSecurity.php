<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ControlSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('control')->check()) {
            return redirect()->route('control.login');
        }

        $verifiedAt = (int) $request->session()->get('control_2fa_verified_at', 0);
        if ($verifiedAt < time() - (int) config('tenancy.control_auth_timeout_seconds', 1800)) {
            auth('control')->logout();
            $request->session()->invalidate();

            return redirect()->route('control.login')->withErrors(['email' => 'Your secure session expired.']);
        }

        $response = $next($request);

        return $response
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    }
}
