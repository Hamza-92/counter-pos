<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Translate extends Model
{
    protected $table = 'translations';

    use HasFactory;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'locale', 'key', 'value',
    ];
}
