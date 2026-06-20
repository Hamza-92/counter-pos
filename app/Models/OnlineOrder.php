<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineOrder extends Model
{
    protected $table = 'online_orders';

    protected $fillable = [
        'date', 'time', 'ref',
        'client_id', 'warehouse_id',
        'total',
        'payment_method', 'payment_status', 'stripe_payment_intent_id',
        'status',
        'has_preorder_items',
    ];

    protected $casts = [
        'date' => 'date',
        'client_id' => 'integer',
        'warehouse_id' => 'integer',
        'total' => 'decimal:2',
        'has_preorder_items' => 'boolean',
    ];

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(OnlineOrderItem::class, 'order_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Auto-fill date, time, ref
    protected static function boot()
    {
        parent::boot();

        static::creating(function (OnlineOrder $order) {
            if (empty($order->date)) {
                $order->date = now()->toDateString();
            }
            if (empty($order->time)) {
                $order->time = now()->format('H:i:s');
            }
            if (empty($order->ref)) {
                $order->ref = static::generateRef();
            }
        });
    }

    public static function generateRef(): string
    {
        $prefix = 'SO-'.now()->format('Ymd').'-';
        // Simple daily sequence based on max id seen today
        $maxIdToday = static::whereDate('created_at', now()->toDateString())->max('id');
        $seq = ($maxIdToday ? $maxIdToday + 1 : 1);

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
