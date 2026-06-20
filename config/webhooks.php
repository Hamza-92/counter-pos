<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Outgoing Delivery Queue
    |--------------------------------------------------------------------------
    | The queue used by WebhookDeliveryJob and the DispatchWebhooks listener.
    | Defaults to a dedicated "webhooks" queue so it can be monitored/scaled
    | independently. Workers must include --queue=webhooks (or default).
    */
    'queue' => env('WEBHOOKS_QUEUE', 'webhooks'),

    /*
    |--------------------------------------------------------------------------
    | Per-source HMAC Secrets for Incoming Webhooks
    |--------------------------------------------------------------------------
    | Map from source name (used in /api/webhooks/incoming/{source}) to the
    | shared secret. Requests must include X-Webhook-Signature header.
    | If a source is omitted here, incoming requests for it are accepted
    | without verification (useful during initial integration).
    */
    'incoming_secrets' => [
        // 'generic'     => env('WEBHOOKS_INCOMING_SECRET_GENERIC'),
        // 'woocommerce' => env('WEBHOOKS_INCOMING_SECRET_WOOCOMMERCE'),
        // 'quickbooks'  => env('WEBHOOKS_INCOMING_SECRET_QUICKBOOKS'),
    ],
];
