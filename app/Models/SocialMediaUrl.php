<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialMediaUrl extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'facebook_url', 'twitter_url', 'instagram_url', 'google_url', 'youtube_url',
    ];
}
