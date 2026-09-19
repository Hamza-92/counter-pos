<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

final class CrmApiRequest extends ControlPlaneModel
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'response' => 'array',
        'completed_at' => 'immutable_datetime',
    ];
}
