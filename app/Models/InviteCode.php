<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    protected $fillable = [
        'code', 'created_by', 'max_uses', 'times_used', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'max_uses'   => 'integer',
        'times_used' => 'integer',
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    // ─── Scopes ──────────────────────────────────────────

    public function scopeUsable($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                  ->orWhereColumn('times_used', '<', 'max_uses');
            });
    }

    // ─── Helpers ─────────────────────────────────────────

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->times_used >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function redeem(): void
    {
        $this->increment('times_used');
    }

    // ─── Relations ───────────────────────────────────────

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations()
    {
        return $this->hasMany(EcommerceClient::class, 'invite_code_id');
    }
}
