<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TenantBackup extends ControlPlaneModel
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = ['size_bytes' => 'integer'];

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Backup history cannot be deleted through the application.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
