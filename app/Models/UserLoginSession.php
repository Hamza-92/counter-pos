<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLoginSession extends Model
{
    protected $table = 'user_login_sessions';

    protected $fillable = [
        'user_id',
        'access_token_id',
        'ip_address',
        'user_agent',
        'logged_in_at',
        'last_activity_at',
        'revoked_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'logged_in_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessToken()
    {
        return $this->belongsTo(OauthAccessToken::class, 'access_token_id', 'id');
    }
}



























































