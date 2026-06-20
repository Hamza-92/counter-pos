<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';

    const STATUS_PENDING  = 'pending';
    const STATUS_SUCCESS  = 'success';
    const STATUS_FAILED   = 'failed';
    const STATUS_RETRYING = 'retrying';

    protected $fillable = [
        'webhook_id',
        'event',
        'status',
        'attempt',
        'response_code',
        'payload',
        'response_body',
        'error_message',
        'duration_ms',
        'delivered_at',
        'next_attempt_at',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'response_code' => 'integer',
        'duration_ms' => 'integer',
        'delivered_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];

    public function webhook()
    {
        return $this->belongsTo(Webhook::class, 'webhook_id');
    }
}
