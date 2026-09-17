<?php

namespace Tests\Unit;

use App\Support\SaleLineDiscount;
use PHPUnit\Framework\TestCase;

class SaleLineDiscountTest extends TestCase
{
    public function test_percentage_discount_scales_with_fractional_quantity(): void
    {
        $detail = (object) [
            'price' => 200,
            'quantity' => 1.5,
            'discount' => 10,
            'discount_method' => '1',
        ];

        $result = SaleLineDiscount::forDetail($detail);

        $this->assertSame('1', $result['discount_method']);
        $this->assertSame(10.0, $result['discount_value']);
        $this->assertSame(20.0, $result['discount_unit_amount']);
        $this->assertSame(30.0, $result['discount_line_amount']);
    }

    public function test_fixed_discount_is_per_unit_and_scales_with_fractional_quantity(): void
    {
        $detail = (object) [
            'price' => 200,
            'quantity' => 0.5,
            'discount' => 20,
            'discount_method' => 'fixed',
        ];

        $result = SaleLineDiscount::forDetail($detail);

        $this->assertSame('2', $result['discount_method']);
        $this->assertSame(20.0, $result['discount_unit_amount']);
        $this->assertSame(10.0, $result['discount_line_amount']);
    }

    public function test_missing_product_discount_method_uses_historical_percentage_default(): void
    {
        $detail = (object) [
            'price' => 80,
            'quantity' => 2,
            'discount' => 5,
            'discount_method' => null,
        ];

        $result = SaleLineDiscount::forDetail($detail);

        $this->assertSame('1', $result['discount_method']);
        $this->assertSame(4.0, $result['discount_unit_amount']);
        $this->assertSame(8.0, $result['discount_line_amount']);
    }
}
