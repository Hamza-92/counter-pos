<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnDetailBatch extends Model
{
    protected $table = 'sale_return_detail_batches';

    protected $fillable = [
        'sale_return_detail_id', 'product_batch_id', 'qty', 'unit_price',
    ];

    protected $casts = [
        'sale_return_detail_id' => 'integer',
        'product_batch_id' => 'integer',
        'qty' => 'double',
        'unit_price' => 'double',
    ];

    public function saleReturnDetail()
    {
        return $this->belongsTo(SaleReturnDetails::class, 'sale_return_detail_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
