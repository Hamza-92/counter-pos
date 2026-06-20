<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageDetailBatch extends Model
{
    protected $table = 'damage_detail_batches';

    protected $fillable = [
        'damage_detail_id', 'product_batch_id', 'qty', 'unit_cost',
    ];

    protected $casts = [
        'damage_detail_id' => 'integer',
        'product_batch_id' => 'integer',
        'qty' => 'double',
        'unit_cost' => 'double',
    ];

    public function damageDetail()
    {
        return $this->belongsTo(DamageDetail::class, 'damage_detail_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
