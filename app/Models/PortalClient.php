<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class PortalClient extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $fillable = [
        'client_id',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'status' => 'integer',
        'invitation_sent_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Check if portal access is enabled and account is active.
     */
    public function canAccessPortal(): bool
    {
        return (int) $this->status === 1 && $this->password !== null;
    }
}
