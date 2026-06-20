<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseClientDetail extends Model
{
    protected $fillable = [
        'id', 'purchase_client_id', 'quantity', 'sale_unit_id', 'product_id', 'product_variant_id', 'price', 'total',
    ];

    protected $casts = [
        'quantity' => 'double',
        'price' => 'double',
        'total' => 'double',
        'purchase_client_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'sale_unit_id' => 'integer',
    ];

    public function purchaseClient()
    {
        return $this->belongsTo(PurchaseClientDetail::class, 'purchase_client_id');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
