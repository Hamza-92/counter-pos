<?php

namespace App\Models\ControlPlane;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SuperAdmin extends Authenticatable
{
    use HasUuids, Notifiable;

    protected $connection = 'control';

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'totp_secret', 'recovery_code_hashes'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'immutable_datetime',
        'totp_secret' => 'encrypted',
        'recovery_code_hashes' => 'encrypted:array',
    ];
}
