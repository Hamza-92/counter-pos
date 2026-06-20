<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WooCommerceSetting extends Model
{
    protected $table = 'woocommerce_settings';

    protected $fillable = [
        'store_url',
        'consumer_key',
        'consumer_secret',
        'wp_username',
        'wp_app_password',
        'enable_auto_sync',
        'sync_interval',
        'last_sync_at',
    ];

    protected $casts = [
        'enable_auto_sync' => 'boolean',
        'last_sync_at' => 'datetime',
    ];
}
