<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'locale', 'flag', 'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'integer',
        'is_active' => 'integer',
    ];
}
