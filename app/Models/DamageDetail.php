<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageDetail extends Model
{
    protected $fillable = [
        'id', 'product_id', 'damage_id', 'quantity', 'product_variant_id',
    ];

    protected $casts = [
        'damage_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'double',
        'product_variant_id' => 'integer',
    ];

    public function damage()
    {
        return $this->belongsTo('App\Models\Damage');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}





