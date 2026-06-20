<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftSaleDetail extends Model
{
    protected $fillable = [
        'id', 'date', 'draft_sale_id', 'sale_unit_id', 'quantity', 'product_id', 'total', 'product_variant_id',
        'price', 'TaxNet', 'discount', 'discount_method', 'tax_method', 'price_type',
    ];

    protected $casts = [
        'id' => 'integer',
        'total' => 'double',
        'quantity' => 'double',
        'draft_sale_id' => 'integer',
        'sale_unit_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'price' => 'double',
        'TaxNet' => 'double',
        'discount' => 'double',
        'price_type' => 'string',
    ];

    public function draftsale()
    {
        return $this->belongsTo('App\Models\DraftSale');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
