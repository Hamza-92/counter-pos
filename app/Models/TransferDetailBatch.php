<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferDetailBatch extends Model
{
    protected $table = 'transfer_detail_batches';

    protected $fillable = [
        'transfer_detail_id', 'source_batch_id', 'dest_batch_id', 'qty', 'unit_cost',
    ];

    protected $casts = [
        'transfer_detail_id' => 'integer',
        'source_batch_id' => 'integer',
        'dest_batch_id' => 'integer',
        'qty' => 'double',
        'unit_cost' => 'double',
    ];

    public function transferDetail()
    {
        return $this->belongsTo(TransferDetail::class, 'transfer_detail_id');
    }

    public function sourceBatch()
    {
        return $this->belongsTo(ProductBatch::class, 'source_batch_id');
    }

    public function destBatch()
    {
        return $this->belongsTo(ProductBatch::class, 'dest_batch_id');
    }
}
