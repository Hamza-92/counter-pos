<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceJobItem extends Model
{
    use HasFactory;

    protected $table = 'service_job_items';

    protected $dates = [
        'deleted_at',
    ];

    protected $fillable = [
        'service_job_id',
        'type',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'discount_method',
        'tax_rate',
        'tax_method',
        'total',
        'stock_deducted',
        'notes',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'tax_rate' => 'float',
        'total' => 'float',
        'stock_deducted' => 'boolean',
        'service_job_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'warehouse_id' => 'integer',
    ];

    public function job()
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function product_variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
