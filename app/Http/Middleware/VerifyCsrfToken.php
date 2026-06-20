<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        // Client portal API: session-based auth; CSRF token can mismatch when portal
        // is opened in another tab or page is cached. SameSite session cookie is used.
        'api/portal',
        'api/portal/*',

        // POST /login: credentials-based auth form. Excluded because a cached
        // /login HTML (from the browser HTTP cache or an older PWA service-worker
        // shell) can carry a stale _token that no longer matches the server session
        // — this surfaces to end users as "419 Page Expired" on a single click.
        //
        // Login-CSRF is a limited threat: the request already requires valid
        // credentials, brute force is rate-limited, and session fixation is
        // prevented by $request->session()->regenerate() inside the LoginController
        // (see app/Http/Controllers/Auth/LoginController.php) immediately after
        // Auth::attempt() succeeds.
        'login',
        '*/login',
        'logout',
        '*/logout',
    ];

    /**
     * Unconditionally bypass CSRF for the login/logout endpoints regardless of
     * how the request URI is parsed (subdir installs, trailing slashes, etc.).
     * Belt-and-braces on top of the $except list above.
     */
    public function handle($request, Closure $next)
    {
        if ($request->isMethod('POST')) {
            $path = trim((string) $request->path(), '/');
            if ($path === 'login' || $path === 'logout'
                || str_ends_with($path, '/login') || str_ends_with($path, '/logout')) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}
