<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\OnlineStorePrice;
use PHPUnit\Framework\TestCase;

class OnlineStorePriceTest extends TestCase
{
    public function test_product_online_price_falls_back_to_retail_price(): void
    {
        $product = new Product([
            'price' => 100,
            'online_store_price' => null,
            'discount' => 0,
            'discount_method' => '1',
            'TaxNet' => 0,
            'tax_method' => '1',
        ]);

        $this->assertSame(100.0, OnlineStorePrice::base($product));
        $this->assertSame(100.0, OnlineStorePrice::calculate($product)['final']);
    }

    public function test_explicit_zero_online_price_does_not_fall_back(): void
    {
        $product = new Product([
            'price' => 100,
            'online_store_price' => 0,
            'discount' => 0,
            'TaxNet' => 0,
        ]);

        $this->assertSame(0.0, OnlineStorePrice::base($product));
    }

    public function test_online_price_uses_existing_discount_and_tax_policy(): void
    {
        $product = new Product([
            'price' => 100,
            'online_store_price' => 120,
            'discount' => 10,
            'discount_method' => '1',
            'tax_method' => '1',
        ]);
        $product->TaxNet = 5;

        $calculation = OnlineStorePrice::calculate($product);

        $this->assertSame(120.0, $calculation['base']);
        $this->assertSame(12.0, $calculation['discount']);
        $this->assertSame(113.4, $calculation['final']);
    }

    public function test_variant_has_an_independent_online_price_with_retail_fallback(): void
    {
        $product = new Product([
            'price' => 0,
            'online_store_price' => null,
            'discount' => 0,
            'TaxNet' => 0,
        ]);
        $onlineVariant = new ProductVariant(['price' => 80, 'online_store_price' => 95]);
        $fallbackVariant = new ProductVariant(['price' => 80, 'online_store_price' => null]);

        $this->assertSame(95.0, OnlineStorePrice::base($product, $onlineVariant));
        $this->assertSame(80.0, OnlineStorePrice::base($product, $fallbackVariant));
    }
}
