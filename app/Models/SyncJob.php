<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncJob extends Model
{
    protected $table = 'sync_jobs';

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'status',
        'total_items',
        'processed_items',
        'success_items',
        'failed_items',
        'percentage',
        'stage',
        'current_product_id',
        'current_sku',
        'last_error',
        'started_at',
        'finished_at',
        'cancel_requested',
        'worker_heartbeat_at',
    ];

    protected $casts = [
        'cancel_requested' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'worker_heartbeat_at' => 'datetime',
    ];
}

