<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use SoftDeletes;

    protected $table = 'webhooks';

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'headers',
        'is_active',
        'timeout_seconds',
        'last_fired_at',
        'user_id',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
        'timeout_seconds' => 'integer',
        'last_fired_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id');
    }

    public function subscribesTo(string $event): bool
    {
        $events = $this->events ?? [];
        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
