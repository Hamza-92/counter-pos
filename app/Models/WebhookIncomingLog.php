<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookIncomingLog extends Model
{
    protected $table = 'webhook_incoming_logs';

    const STATUS_RECEIVED  = 'received';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED    = 'failed';
    const STATUS_IGNORED   = 'ignored';

    protected $fillable = [
        'source',
        'event',
        'ip',
        'headers',
        'payload',
        'signature_valid',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
    ];
}
