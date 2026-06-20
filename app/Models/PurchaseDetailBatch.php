<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseDetailBatch extends Model
{
    protected $table = 'purchase_detail_batches';

    protected $fillable = [
        'purchase_detail_id', 'product_batch_id', 'qty', 'unit_cost',
    ];

    protected $casts = [
        'purchase_detail_id' => 'integer',
        'product_batch_id' => 'integer',
        'qty' => 'double',
        'unit_cost' => 'double',
    ];

    public function purchaseDetail()
    {
        return $this->belongsTo(PurchaseDetail::class, 'purchase_detail_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
