<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Ensures the portal is connected only with clients (PortalClient), never with admin (User).
 * - Sets default guard to 'portal' so Auth::user() is the portal client only.
 * - On protected portal routes: if admin (web) is logged in but portal client is not,
 *   reject with 403 so admin session never grants portal access.
 */
class EnsurePortalAuth
{
    /** Portal paths that require portal client auth (not admin). */
    protected $protectedPaths = [
        'api/portal/me',
        'api/portal/logout',
        'api/portal/dashboard',
        'api/portal/invoices',
        'api/portal/payments',
        'api/portal/statement',
        'api/portal/profile',
    ];

    public function handle($request, Closure $next)
    {
        // Use only the portal guard for this request lifecycle.
        Auth::shouldUse('portal');

        $path = $request->path();

        // Protected portal route: must be authenticated as portal client only, never as admin.
        if ($this->isProtectedPortalPath($path)) {
            $isAdmin = Auth::guard('web')->check();
            $isPortalClient = Auth::guard('portal')->check();

            if ($isAdmin && ! $isPortalClient) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Portal access is for clients only. Please log in with your portal account.'], 403)
                    : abort(403, 'Portal access is for clients only.');
            }
        }

        return $next($request);
    }

    protected function isProtectedPortalPath($path)
    {
        foreach ($this->protectedPaths as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }
        // Any other path under api/portal (e.g. invoices/1, profile/password) is protected
        if (preg_match('#^api/portal/([^/]+)#', $path, $m)) {
            $firstSegment = $m[1];
            if (! in_array($firstSegment, ['login', 'set-password', 'validate-invite'], true)) {
                return true;
            }
        }
        return false;
    }
}
