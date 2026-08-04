<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ManualPayment extends ControlPlaneModel
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'immutable_datetime',
        'recorded_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Manual payment records are append-only.'));
        static::deleting(static fn () => throw new LogicException('Manual payment records are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
