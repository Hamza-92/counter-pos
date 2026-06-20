<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductWarehouseLocation extends Model
{
    protected $table = 'product_warehouse_locations';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'warehouse_location_id',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'warehouse_location_id' => 'integer',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }
}

