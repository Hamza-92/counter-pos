<?php

namespace App\Services\Webhooks;

use App\Jobs\Webhooks\WebhookDeliveryJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;
use Throwable;

class WebhookDispatcher
{
    /**
     * Dispatch an event payload to every active webhook subscribed to it.
     * Always returns gracefully — must never throw into the caller (events).
     */
    public function dispatch(string $event, array $payload): void
    {
        try {
            $webhooks = Webhook::query()
                ->where('is_active', true)
                ->get()
                ->filter(fn (Webhook $w) => $w->subscribesTo($event));

            foreach ($webhooks as $webhook) {
                $this->queue($webhook, $event, $payload);
            }
        } catch (Throwable $e) {
            // Never let webhook dispatch break the originating business flow.
            report($e);
        }
    }

    protected function queue(Webhook $webhook, string $event, array $payload): void
    {
        try {
            $envelope = $this->buildEnvelope($event, $payload);

            $delivery = WebhookDelivery::create([
                'webhook_id' => $webhook->id,
                'event'      => $event,
                'status'     => WebhookDelivery::STATUS_PENDING,
                'attempt'    => 0,
                'payload'    => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            WebhookDeliveryJob::dispatch($delivery->id)->onQueue('webhooks');
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function buildEnvelope(string $event, array $payload): array
    {
        return [
            'id'         => (string) Str::uuid(),
            'event'      => $event,
            'created_at' => now()->toIso8601String(),
            'data'       => $payload,
        ];
    }

    public static function sign(string $body, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }
}
