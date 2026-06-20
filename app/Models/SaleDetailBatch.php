<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleDetailBatch extends Model
{
    protected $table = 'sale_detail_batches';

    protected $fillable = [
        'sale_detail_id', 'product_batch_id', 'qty', 'unit_price',
    ];

    protected $casts = [
        'sale_detail_id' => 'integer',
        'product_batch_id' => 'integer',
        'qty' => 'double',
        'unit_price' => 'double',
    ];

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class, 'sale_detail_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
