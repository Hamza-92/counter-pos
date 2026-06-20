<?php

namespace App\Http\Middleware;

use App\Models\UserLoginSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutRevokedWebSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();

        // Passport cookie-auth sessions are keyed as "cookie:<sha256(csrf)>"
        // (same scheme used by SecuritySettingsController / EnforceApiTokenTimeout)
        $cookieSessionKey = null;
        try {
            $csrf = $request->session()->token();
            if ($csrf) {
                $cookieSessionKey = 'cookie:'.hash('sha256', (string) $csrf);
            }
        } catch (\Throwable $e) {
            // ignore; best-effort only
        }

        $revoked = UserLoginSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('revoked_at')
            ->where(function ($q) use ($sessionId, $cookieSessionKey) {
                // Web session correlation
                $q->where('session_id', $sessionId);

                // Legacy marker (some code stores the web session id as access_token_id)
                $q->orWhere('access_token_id', $sessionId);

                // Passport SPA cookie session correlation
                if ($cookieSessionKey) {
                    $q->orWhere('access_token_id', $cookieSessionKey);
                }
            })
            ->exists();

        if (! $revoked) {
            return $next($request);
        }

        // 🔥 HARD LOGOUT
        // Prevent "remember me" from re-authenticating and causing redirect loops.
        try {
            $user->setRememberToken(null);
            $user->save();
        } catch (\Throwable $e) {
            // ignore
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // For AJAX/XHR requests, signal the SPA to do a hard reload.
        // For normal browser navigation, redirect to login.
        if ($request->expectsJson() || $request->ajax()) {
            return response('', 409)->header('X-Session-Revoked', '1');
        }

        return redirect()->route('login');
    }
}
