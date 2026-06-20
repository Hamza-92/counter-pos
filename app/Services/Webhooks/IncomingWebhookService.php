<?php

namespace App\Services\Webhooks;

use App\Models\WebhookIncomingLog;
use Illuminate\Http\Request;
use Throwable;

class IncomingWebhookService
{
    /**
     * Log every incoming webhook attempt, verify signature when a secret is
     * configured for the source, and return the persisted log record.
     */
    public function receive(Request $request, string $source): WebhookIncomingLog
    {
        $rawBody = $request->getContent();
        $headers = $this->sanitizeHeaders($request->headers->all());
        $signatureValid = $this->verifySignature($source, $request, $rawBody);

        $log = WebhookIncomingLog::create([
            'source'          => $source,
            'event'           => $request->header('X-Webhook-Event') ?? $request->input('event'),
            'ip'              => $request->ip(),
            'headers'         => $headers,
            'payload'         => $rawBody,
            'signature_valid' => $signatureValid,
            'status'          => $signatureValid
                ? WebhookIncomingLog::STATUS_RECEIVED
                : WebhookIncomingLog::STATUS_FAILED,
            'error_message'   => $signatureValid ? null : 'Signature verification failed or missing.',
        ]);

        if ($signatureValid) {
            $this->process($log);
        }

        return $log;
    }

    protected function process(WebhookIncomingLog $log): void
    {
        try {
            $log->status = WebhookIncomingLog::STATUS_PROCESSED;
            $log->processed_at = now();
            $log->save();
        } catch (Throwable $e) {
            $log->status = WebhookIncomingLog::STATUS_FAILED;
            $log->error_message = $e->getMessage();
            $log->save();
            report($e);
        }
    }

    protected function verifySignature(string $source, Request $request, string $rawBody): bool
    {
        $secret = $this->getSourceSecret($source);
        if (!$secret) {
            return true;
        }

        $header = $request->header('X-Webhook-Signature');
        if (!$header) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $header);
    }

    protected function getSourceSecret(string $source): ?string
    {
        $secrets = config('webhooks.incoming_secrets', []);
        return $secrets[$source] ?? null;
    }

    protected function sanitizeHeaders(array $headers): array
    {
        $blocked = ['authorization', 'cookie', 'x-api-key'];
        return collect($headers)
            ->reject(fn ($v, $k) => in_array(strtolower($k), $blocked, true))
            ->toArray();
    }
}
