<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WooCommerceLog extends Model
{
    protected $table = 'woocommerce_logs';

    protected $fillable = [
        'action',
        'level',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
