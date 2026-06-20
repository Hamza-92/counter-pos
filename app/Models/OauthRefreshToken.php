<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OauthRefreshToken extends Model
{
    protected $table = 'oauth_refresh_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public function oauthAccessToken()
    {
        return $this->belongsTo(\App\Models\OauthAccessToken::class, 'access_token_id', 'id');
    }
}
