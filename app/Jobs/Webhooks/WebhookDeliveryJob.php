<?php

namespace App\Jobs\Webhooks;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum attempts including the initial send. */
    public int $tries = 5;

    /** Job timeout — must be higher than the per-request HTTP timeout. */
    public int $timeout = 60;

    public function __construct(public int $deliveryId)
    {
    }

    /**
     * Exponential backoff schedule (seconds): 1m, 5m, 30m, 2h.
     */
    public function backoff(): array
    {
        return [60, 300, 1800, 7200];
    }

    public function handle(): void
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::find($this->deliveryId);
        if (!$delivery) {
            return;
        }

        /** @var Webhook|null $webhook */
        $webhook = Webhook::withTrashed()->find($delivery->webhook_id);
        if (!$webhook || $webhook->trashed() || !$webhook->is_active) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_FAILED,
                'error_message' => 'Webhook disabled or deleted before delivery.',
            ]);
            return;
        }

        $attempt = $this->attempts();
        $body = $delivery->payload ?? '';
        $signature = WebhookDispatcher::sign($body, $webhook->secret);

        $headers = array_merge([
            'Content-Type'        => 'application/json',
            'User-Agent'          => 'Stocky-Webhooks/1.0',
            'X-Webhook-Event'     => $delivery->event,
            'X-Webhook-Id'        => (string) $webhook->id,
            'X-Webhook-Delivery'  => (string) $delivery->id,
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Attempt'   => (string) $attempt,
        ], is_array($webhook->headers) ? $webhook->headers : []);

        $start = microtime(true);
        $status = WebhookDelivery::STATUS_FAILED;
        $responseCode = null;
        $responseBody = null;
        $errorMessage = null;

        try {
            $response = Http::timeout($webhook->timeout_seconds ?: 15)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $responseCode = $response->status();
            $responseBody = mb_substr((string) $response->body(), 0, 8000);

            if ($response->successful()) {
                $status = WebhookDelivery::STATUS_SUCCESS;
            } else {
                $errorMessage = 'HTTP ' . $responseCode;
                $status = $attempt >= $this->tries
                    ? WebhookDelivery::STATUS_FAILED
                    : WebhookDelivery::STATUS_RETRYING;
            }
        } catch (Throwable $e) {
            $errorMessage = mb_substr($e->getMessage(), 0, 2000);
            $status = $attempt >= $this->tries
                ? WebhookDelivery::STATUS_FAILED
                : WebhookDelivery::STATUS_RETRYING;
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        $delivery->update([
            'status'        => $status,
            'attempt'       => $attempt,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
            'duration_ms'   => $durationMs,
            'delivered_at'  => $status === WebhookDelivery::STATUS_SUCCESS ? now() : null,
            'next_attempt_at' => $status === WebhookDelivery::STATUS_RETRYING
                ? now()->addSeconds($this->backoff()[min($attempt - 1, count($this->backoff()) - 1)] ?? 7200)
                : null,
        ]);

        $webhook->forceFill(['last_fired_at' => now()])->save();

        if ($status === WebhookDelivery::STATUS_RETRYING) {
            // Trigger Laravel's retry mechanism with backoff.
            $this->release($this->backoff()[min($attempt - 1, count($this->backoff()) - 1)] ?? 7200);
        }
    }

    public function failed(Throwable $e): void
    {
        WebhookDelivery::where('id', $this->deliveryId)->update([
            'status' => WebhookDelivery::STATUS_FAILED,
            'error_message' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
