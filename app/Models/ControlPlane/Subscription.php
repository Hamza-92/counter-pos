<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends ControlPlaneModel
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'grace_ends_at' => 'immutable_datetime',
        'agreed_amount' => 'decimal:2',
        'version' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ManualPayment::class);
    }
}
