<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use LogicException;

class ControlAuditLog extends ControlPlaneModel
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Control audit logs are append-only.'));
        static::deleting(static fn () => throw new LogicException('Control audit logs are append-only.'));
    }
}
