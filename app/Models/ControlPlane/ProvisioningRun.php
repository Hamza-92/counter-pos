<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningRun extends ControlPlaneModel
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'result' => 'array',
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
