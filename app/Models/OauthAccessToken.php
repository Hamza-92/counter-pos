<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OauthAccessToken extends Model
{
    protected $table = 'oauth_access_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function oauthRefreshToken()
    {
        return $this->hasMany('\App\Models\OauthRefreshToken', 'access_token_id');
    }
}
