<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationDetailBatch extends Model
{
    protected $table = 'quotation_detail_batches';

    protected $fillable = [
        'quotation_detail_id', 'product_batch_id', 'qty', 'unit_cost',
    ];

    protected $casts = [
        'quotation_detail_id' => 'integer',
        'product_batch_id' => 'integer',
        'qty' => 'double',
        'unit_cost' => 'double',
    ];

    public function quotationDetail()
    {
        return $this->belongsTo(QuotationDetail::class, 'quotation_detail_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
