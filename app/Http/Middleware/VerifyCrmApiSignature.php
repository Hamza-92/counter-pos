<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class VerifyCrmApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('tenancy.crm_api.key');
        $secret = (string) config('tenancy.crm_api.secret');
        $providedKey = (string) $request->header('X-CRM-Key', '');
        $timestamp = (string) $request->header('X-CRM-Timestamp', '');
        $nonce = (string) $request->header('X-CRM-Nonce', '');
        $providedSignature = strtolower((string) $request->header('X-CRM-Signature', ''));

        if ($configuredKey === '' || $secret === '' || $providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return $this->reject('crm_auth_invalid', 'Invalid CRM API credentials.');
        }

        if (! ctype_digit($timestamp)) {
            return $this->reject('crm_timestamp_invalid', 'Invalid CRM API timestamp.');
        }

        $clockSkew = max(30, (int) config('tenancy.crm_api.clock_skew_seconds', 300));
        if (abs(time() - (int) $timestamp) > $clockSkew) {
            return $this->reject('crm_timestamp_expired', 'CRM API request has expired.');
        }

        if (! preg_match('/^[A-Za-z0-9._-]{16,128}$/', $nonce)) {
            return $this->reject('crm_nonce_invalid', 'Invalid CRM API nonce.');
        }

        if (! preg_match('/^[a-f0-9]{64}$/', $providedSignature)) {
            return $this->reject('crm_signature_invalid', 'Invalid CRM API signature.');
        }

        $canonical = implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($request->method()),
            '/'.ltrim($request->getRequestUri(), '/'),
            hash('sha256', $request->getContent()),
        ]);
        $expectedSignature = hash_hmac('sha256', $canonical, $secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            return $this->reject('crm_signature_invalid', 'Invalid CRM API signature.');
        }

        $replayKey = 'crm-api-nonce:'.hash('sha256', $providedKey.'|'.$nonce);
        if (! Cache::store('control_database')->add($replayKey, true, $clockSkew * 2)) {
            return $this->reject('crm_request_replayed', 'CRM API request has already been used.', 409);
        }

        return $next($request);
    }

    private function reject(string $code, string $message, int $status = 401): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
