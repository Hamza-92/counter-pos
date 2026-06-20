<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBatch extends Model
{
    use SoftDeletes;

    protected $table = 'product_batches';

    protected $dates = ['deleted_at', 'expiry_date', 'mfg_date'];

    protected $fillable = [
        'product_id', 'product_variant_id', 'warehouse_id',
        'batch_no', 'expiry_date', 'mfg_date',
        'qty', 'unit_cost',
        'provider_id', 'source_purchase_id',
        'status', 'barcode', 'notes',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'warehouse_id' => 'integer',
        'provider_id' => 'integer',
        'source_purchase_id' => 'integer',
        'qty' => 'double',
        'unit_cost' => 'double',
        'expiry_date' => 'date',
        'mfg_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'source_purchase_id');
    }

    public function purchaseDetailBatches()
    {
        return $this->hasMany(PurchaseDetailBatch::class, 'product_batch_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeExpired($q)
    {
        return $q->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString());
    }

    public function scopeExpiringWithin($q, int $days)
    {
        $today = now()->toDateString();
        $end = now()->addDays(max(0, $days))->toDateString();

        return $q->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $end);
    }

    public function scopeForWarehouse($q, $warehouseId)
    {
        return $q->where('warehouse_id', $warehouseId);
    }

    public function scopeForProduct($q, $productId)
    {
        return $q->where('product_id', $productId);
    }

    public function scopeFefo($q)
    {
        return $q->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc');
    }
}
