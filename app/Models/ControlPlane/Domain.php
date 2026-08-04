<?php

namespace App\Models\ControlPlane;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use App\Tenancy\HostNormalizer;

class Domain extends ControlPlaneModel
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'immutable_datetime',
        'last_seen_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $domain): void {
            $normalized = app(HostNormalizer::class)->normalize($domain->host);
            if (hash_equals((string) config('tenancy.control_host'), $normalized)) {
                throw new InvalidArgumentException('The control-plane host cannot be assigned to a tenant.');
            }
            $domain->normalized_host = $normalized;
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
