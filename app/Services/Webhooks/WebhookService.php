<?php

namespace App\Services\Webhooks;

use App\Models\Webhook;
use Illuminate\Support\Str;

class WebhookService
{
    /**
     * Canonical list of outbound events the system can emit.
     * Extend here when new domain events are added — no other changes needed.
     */
    public static function availableEvents(): array
    {
        return [
            'sale.created', 'sale.updated', 'sale.deleted',
            'sale_return.created', 'sale_return.updated', 'sale_return.deleted',
            'purchase.created', 'purchase.updated', 'purchase.deleted',
            'purchase_return.created', 'purchase_return.updated', 'purchase_return.deleted',
            'payment.created', 'payment.deleted',
            'payment_purchase.created', 'payment_purchase.deleted',
            'payment_sale_return.created', 'payment_sale_return.deleted',
            'payment_purchase_return.created', 'payment_purchase_return.deleted',
            'expense.created', 'expense.updated', 'expense.deleted',
            'cash_register.opened',
        ];
    }

    public function create(array $attributes): Webhook
    {
        $attributes['secret'] = $attributes['secret'] ?? $this->generateSecret();
        $attributes['events'] = $this->sanitizeEvents($attributes['events'] ?? []);
        $attributes['is_active'] = array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true;
        $attributes['timeout_seconds'] = (int) ($attributes['timeout_seconds'] ?? 15);

        return Webhook::create($attributes);
    }

    public function update(Webhook $webhook, array $attributes): Webhook
    {
        if (array_key_exists('events', $attributes)) {
            $attributes['events'] = $this->sanitizeEvents($attributes['events']);
        }
        if (array_key_exists('is_active', $attributes)) {
            $attributes['is_active'] = (bool) $attributes['is_active'];
        }
        if (array_key_exists('timeout_seconds', $attributes)) {
            $attributes['timeout_seconds'] = (int) $attributes['timeout_seconds'];
        }

        $webhook->fill($attributes)->save();
        return $webhook;
    }

    public function regenerateSecret(Webhook $webhook): Webhook
    {
        $webhook->secret = $this->generateSecret();
        $webhook->save();
        return $webhook;
    }

    public function toggle(Webhook $webhook, ?bool $active = null): Webhook
    {
        $webhook->is_active = $active ?? !$webhook->is_active;
        $webhook->save();
        return $webhook;
    }

    public function generateSecret(): string
    {
        return 'whsec_' . Str::random(48);
    }

    protected function sanitizeEvents($events): array
    {
        if (!is_array($events)) {
            return [];
        }
        $allowed = self::availableEvents();
        return array_values(array_unique(array_filter($events, function ($e) use ($allowed) {
            return $e === '*' || in_array($e, $allowed, true);
        })));
    }
}
