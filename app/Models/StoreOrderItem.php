<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'name', 'price', 'qty', 'total'];

    public function order()
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }
}
