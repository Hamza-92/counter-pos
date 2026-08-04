<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDatabase extends ControlPlaneModel
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'migration_password'];

    protected $casts = [
        'password' => 'encrypted',
        'migration_password' => 'encrypted',
        'port' => 'integer',
        'credential_version' => 'integer',
        'last_connection_test_at' => 'immutable_datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
