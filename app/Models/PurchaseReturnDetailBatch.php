<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnDetailBatch extends Model
{
    protected $table = 'purchase_return_detail_batches';

    protected $fillable = [
        'purchase_return_detail_id', 'product_batch_id', 'qty', 'unit_cost',
    ];

    protected $casts = [
        'purchase_return_detail_id' => 'integer',
        'product_batch_id' => 'integer',
        'qty' => 'double',
        'unit_cost' => 'double',
    ];

    public function purchaseReturnDetail()
    {
        return $this->belongsTo(PurchaseReturnDetails::class, 'purchase_return_detail_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
