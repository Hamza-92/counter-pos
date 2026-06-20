<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorePage extends Model
{
    protected $fillable = ['title', 'slug', 'content', 'published'];

    protected $casts = ['published' => 'boolean'];
}
