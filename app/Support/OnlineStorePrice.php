<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

class OnlineStorePrice
{
    /**
     * Online prices are optional. A null value inherits the POS retail price;
     * zero is kept as an explicit price.
     */
    public static function base(Product $product, ?ProductVariant $variant = null): float
    {
        if ($variant) {
            $value = $variant->online_store_price;
            if ($value === null || $value === '') {
                $value = $variant->price;
            }
        } else {
            $value = $product->online_store_price;
            if ($value === null || $value === '') {
                $value = $product->price;
            }
        }

        return round(max(0, (float) $value), 2);
    }

    /**
     * Apply the product's existing discount and tax policy to the online base price.
     *
     * @return array{base:float,discount:float,after_discount:float,tax:float,final:float}
     */
    public static function calculate(Product $product, ?ProductVariant $variant = null): array
    {
        return $product->computeFinalPrice(null, self::base($product, $variant));
    }

    /**
     * SQL expressions shared by storefront home/shop sorting and filtering.
     * The pvmin subquery must expose min_variant_store_price.
     *
     * @return array{base:string,after_discount:string,final:string}
     */
    public static function sqlExpressions(): array
    {
        $base = 'COALESCE(pvmin.min_variant_store_price, products.online_store_price, products.price)';
        $discount = 'IFNULL(products.discount, 0)';
        $afterDiscount = "GREATEST(0,
            CASE
                WHEN products.discount_method = '1' THEN {$base} - ({$base} * ({$discount}/100))
                WHEN products.discount_method = '2' THEN {$base} - LEAST({$discount}, {$base})
                ELSE {$base}
            END
        )";
        $tax = 'COALESCE(products.TaxNet, 0)';
        $final = "ROUND(
            CASE
                WHEN products.tax_method = '2' THEN {$afterDiscount}
                ELSE {$afterDiscount} * (1 + ({$tax}/100))
            END, 2
        )";

        return compact('base', 'afterDiscount', 'final');
    }
}
