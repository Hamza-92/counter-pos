<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineOrderItem extends Model
{
    protected $table = 'online_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id', // <-- added
        'qty',
        'price',
        'line_total',
        'TaxNet',
        'discount',
        'discount_method',
        'tax_method',
        'is_preorder',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'qty' => 'decimal:3',
        'price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'TaxNet' => 'double',
        'discount' => 'double',
        'is_preorder' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Variant relation (adjust namespace if your model lives elsewhere)
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    // Keep line_total in sync
    protected static function boot()
    {
        parent::boot();

        static::saving(function (OnlineOrderItem $item) {
            if ($item->isDirty('qty') || $item->isDirty('price') || empty($item->line_total)) {
                $item->line_total = (float) $item->qty * (float) $item->price;
            }
        });
    }
}
