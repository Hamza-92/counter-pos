<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    protected $fillable = [
        'id', 'date', 'sale_id', 'sale_unit_id', 'quantity', 'product_id', 'total', 'product_variant_id',
        'price', 'TaxNet', 'discount', 'discount_method', 'tax_method', 'price_type',
        'warranty_date', 'guarantee_date',
    ];

    protected $casts = [
        'id' => 'integer',
        'total' => 'double',
        'quantity' => 'double',
        'sale_id' => 'integer',
        'sale_unit_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'price' => 'double',
        'TaxNet' => 'double',
        'discount' => 'double',
        'price_type' => 'string',
        'warranty_date' => 'date',
        'guarantee_date' => 'date',
    ];

    public function sale()
    {
        return $this->belongsTo('App\Models\Sale');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }

    /**
     * Compute warranty_date and guarantee_date from product warranty/guarantee duration and a base date.
     * Uses product's warranty_period + warranty_unit and (if has_guarantee) guarantee_period + guarantee_unit.
     *
     * @param  Product  $product  Product (or product from variant) with warranty_period, warranty_unit, has_guarantee, guarantee_period, guarantee_unit
     * @param  \Carbon\Carbon|string  $baseDate  Sale date or created_at to add duration to
     * @return array{ warranty_date: ?string, guarantee_date: ?string }  Y-m-d or null
     */
    public static function computeWarrantyGuaranteeDates(Product $product, $baseDate): array
    {
        $base = $baseDate instanceof Carbon ? $baseDate : Carbon::parse($baseDate);

        $warrantyDate = null;
        if ($product->warranty_period !== null && $product->warranty_period > 0 && ! empty($product->warranty_unit)) {
            $warrantyDate = static::addDuration($base, (int) $product->warranty_period, $product->warranty_unit);
        }

        $guaranteeDate = null;
        if (! empty($product->has_guarantee) && $product->guarantee_period !== null && $product->guarantee_period > 0 && ! empty($product->guarantee_unit)) {
            $guaranteeDate = static::addDuration($base, (int) $product->guarantee_period, $product->guarantee_unit);
        }

        return [
            'warranty_date' => $warrantyDate?->format('Y-m-d'),
            'guarantee_date' => $guaranteeDate?->format('Y-m-d'),
        ];
    }

    /**
     * Add a duration to a date using unit (days, months, years).
     */
    protected static function addDuration(Carbon $date, int $period, string $unit): ?Carbon
    {
        $unit = strtolower($unit);
        if ($unit === 'days') {
            return $date->copy()->addDays($period);
        }
        if ($unit === 'months') {
            return $date->copy()->addMonths($period);
        }
        if ($unit === 'years') {
            return $date->copy()->addYears($period);
        }

        return null;
    }
}
