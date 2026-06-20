<?php

namespace App\Http\Middleware;

use App\Models\OauthAccessToken;
use App\Models\OauthRefreshToken;
use App\Models\UserLoginSession;
use Carbon\Carbon;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

class EnforceApiTokenTimeout
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('api');
        if (! $user) {
            return $next($request);
        }

        $now = now();

        // Passport may attach a TransientToken when authenticating via cookie.
        // In that case there's no token id, so we identify the session via the Passport cookie's CSRF claim.
        [$sessionKey, $loginAt, $isCookieSession] = $this->resolveSessionKey($request, (int) $user->id, $user->token(), $now);
        if (! $sessionKey) {
            return $next($request);
        }

        // Track/update current session activity (without affecting auth flow).
        // Wrap bookkeeping in try/catch — a DB race between concurrent API calls
        // (which happen right after login when the SPA fires several requests in
        // parallel) must never break authentication.
        try {
            $session = UserLoginSession::query()->firstOrNew(['access_token_id' => $sessionKey]);
            if (! $session->exists) {
                $session->user_id = (int) $user->id;
                $session->ip_address = $request->ip();
                $session->user_agent = Str::limit((string) ($request->userAgent() ?? ''), 2000, '');
                $session->logged_in_at = $loginAt ?? $now;
                $session->last_activity_at = $now;
                $session->revoked_at = null;
                try {
                    $session->save();
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Another concurrent request won the race and inserted the
                    // same row — fetch it and continue.
                    $session = UserLoginSession::query()->where('access_token_id', $sessionKey)->first();
                    if (! $session) {
                        return $next($request);
                    }
                }

                if (! $session->revoked_at) {
                    return $next($request);
                }
            }
        } catch (\Throwable $e) {
            return $next($request);
        }

        // If this session was explicitly revoked (logout device), block it.
        if ($session->revoked_at) {
            return $this->unauthorizedWithTokenClear('Session revoked.', 'SessionRevoked', $request);
        }

        // Update last activity (best effort; never break auth flow on write failure).
        try {
            $dirty = false;
            if (! $session->user_id) {
                $session->user_id = (int) $user->id;
                $dirty = true;
            }
            // refresh ip / ua (helps device list remain accurate)
            $session->ip_address = $request->ip();
            $session->user_agent = Str::limit((string) ($request->userAgent() ?? ''), 2000, '');
            $dirty = true;

            $shouldBumpActivity = (! $session->last_activity_at) || ($now->diffInSeconds($session->last_activity_at) >= 10);
            if ($shouldBumpActivity) {
                $session->last_activity_at = $now;
                $dirty = true;
            }

            if ($dirty) {
                $session->save();
            }
        } catch (\Throwable $e) {
            // best-effort bookkeeping — never fail the request
        }

        return $next($request);
    }

    /**
     * @return array{0:?string,1:?Carbon,2:bool} [sessionKey, loginAt, isCookieSession]
     */
    private function resolveSessionKey(Request $request, int $userId, $passportToken, Carbon $now): array
    {
        // Bearer token: Passport attaches an Eloquent token model with a primary key
        if ($passportToken instanceof EloquentModel) {
            $key = (string) $passportToken->getKey();
            $loginAt = $passportToken->created_at instanceof Carbon ? $passportToken->created_at : $now;
            return [$key !== '' ? $key : null, $loginAt, false];
        }

        // Cookie auth: decode Passport cookie JWT (sub/csrf/expiry)
        $cookieName = Passport::cookie();
        $jwtCookie = $request->cookie($cookieName);
        if (! $jwtCookie) {
            return [null, null, false];
        }

        try {
            /** @var Encrypter $encrypter */
            $encrypter = app(Encrypter::class);
            $raw = Passport::$decryptsCookies
                ? CookieValuePrefix::remove($encrypter->decrypt($jwtCookie, Passport::$unserializesCookies))
                : $jwtCookie;

            $decoded = (array) JWT::decode(
                $raw,
                new Key(Passport::tokenEncryptionKey($encrypter), 'HS256')
            );

            $sub = isset($decoded['sub']) ? (int) $decoded['sub'] : null;
            $csrf = isset($decoded['csrf']) ? (string) $decoded['csrf'] : null;
            $expiry = isset($decoded['expiry']) ? (int) $decoded['expiry'] : null;

            if ($sub !== $userId || ! $csrf || ! $expiry) {
                return [null, null, false];
            }

            // Use a non-reversible stable key (do not store CSRF raw)
            $key = 'cookie:'.hash('sha256', $csrf);

            // Best-effort login time approximation from expiry - session lifetime
            $lifetime = (int) (config('session.lifetime') ?? 120);
            $loginAt = Carbon::createFromTimestamp($expiry)->subMinutes($lifetime);

            return [$key, $loginAt, true];
        } catch (\Throwable $e) {
            return [null, null, false];
        }
    }

    private function unauthorizedWithTokenClear(string $message, string $status, Request $request)
    {
        $response = response()->json([
            'message' => $message,
            'status' => $status,
        ], 401);

        // Prevent frontend redirect loops: also clear the "Stocky_token" cookie (used by route guards).
        $serverName = $request->server('SERVER_NAME') ?: ($_SERVER['SERVER_NAME'] ?? null);
        $domain = null;
        if ($serverName) {
            $domain = $serverName !== 'localhost' ? $serverName : '.'.$serverName;
        }

        // Try to expire cookie with the same domain rule as BaseController::setCookie()
        if ($domain) {
            $response->headers->setCookie(cookie('Stocky_token', '', -2628000, '/', $domain));
        }
        $response->headers->setCookie(cookie('Stocky_token', '', -2628000, '/'));

        return $response;
    }
}

