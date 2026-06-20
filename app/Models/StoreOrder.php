<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOrder extends Model
{
    protected $fillable = [
        'code', 'customer_id', 'customer_name', 'customer_email', 'customer_phone',
        'status', 'subtotal', 'shipping', 'discount', 'total',
        'shipping_address', 'billing_address',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'billing_address' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(StoreOrderItem::class, 'order_id');
    }
}
