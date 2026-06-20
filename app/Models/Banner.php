<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $table = 'banners';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'banner1_path', 'banner2_path',
    ];
}
