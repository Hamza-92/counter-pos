<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjustmentDetailBatch extends Model
{
    protected $table = 'adjustment_detail_batches';

    protected $fillable = [
        'adjustment_detail_id', 'product_batch_id', 'direction', 'qty',
    ];

    protected $casts = [
        'adjustment_detail_id' => 'integer',
        'product_batch_id' => 'integer',
        'qty' => 'double',
    ];

    public function adjustmentDetail()
    {
        return $this->belongsTo(AdjustmentDetail::class, 'adjustment_detail_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
