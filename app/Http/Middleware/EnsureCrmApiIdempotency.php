<?php

namespace App\Http\Middleware;

use App\Models\ControlPlane\CrmApiRequest;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCrmApiIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $key = (string) $request->header('Idempotency-Key', '');
        if (! Str::isUuid($key)) {
            return $this->reject('idempotency_key_required', 'A UUID Idempotency-Key header is required.', 422);
        }

        $method = strtoupper($request->method());
        $path = '/'.ltrim($request->getRequestUri(), '/');
        $requestHash = hash('sha256', $request->getContent());
        $record = CrmApiRequest::query()->where('idempotency_key', $key)->first();

        if ($record) {
            return $this->replayOrReject($record, $method, $path, $requestHash);
        }

        try {
            $record = CrmApiRequest::query()->create([
                'idempotency_key' => $key,
                'method' => $method,
                'path' => $path,
                'request_hash' => $requestHash,
            ]);
        } catch (UniqueConstraintViolationException) {
            $record = CrmApiRequest::query()->where('idempotency_key', $key)->firstOrFail();

            return $this->replayOrReject($record, $method, $path, $requestHash);
        }

        $response = $next($request);
        $decoded = json_decode((string) $response->getContent(), true);
        $record->forceFill([
            'status_code' => $response->getStatusCode(),
            'response' => is_array($decoded) ? $decoded : ['message' => 'Request completed.'],
            'completed_at' => now(),
        ])->save();

        return $response;
    }

    private function replayOrReject(CrmApiRequest $record, string $method, string $path, string $requestHash): JsonResponse
    {
        if (! hash_equals($record->method, $method)
            || ! hash_equals($record->path, $path)
            || ! hash_equals($record->request_hash, $requestHash)) {
            return $this->reject('idempotency_conflict', 'Idempotency key was already used for a different request.', 409);
        }

        if ($record->completed_at === null || $record->status_code === null) {
            return $this->reject('request_in_progress', 'A request with this idempotency key is still running.', 409);
        }

        return response()->json($record->response, $record->status_code)
            ->header('X-Idempotent-Replay', 'true');
    }

    private function reject(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
