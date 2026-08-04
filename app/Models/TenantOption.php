<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantOption extends Model
{
    protected $fillable = ['key', 'value'];

    protected $hidden = ['value'];

    protected $casts = [
        'value' => 'encrypted',
    ];
}
