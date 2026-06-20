<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Tests\TestCase;

class SaleDetailWarrantyGuaranteeTest extends TestCase
{
    /**
     * When product has no warranty/guarantee, dates are null.
     */
    public function test_compute_returns_null_dates_when_product_has_no_warranty_or_guarantee(): void
    {
        $product = new Product;
        $product->warranty_period = null;
        $product->warranty_unit = null;
        $product->has_guarantee = false;
        $product->guarantee_period = null;
        $product->guarantee_unit = null;

        $result = SaleDetail::computeWarrantyGuaranteeDates($product, Carbon::parse('2026-02-13'));

        $this->assertNull($result['warranty_date']);
        $this->assertNull($result['guarantee_date']);
    }

    /**
     * Warranty duration in days is added correctly.
     */
    public function test_compute_warranty_date_with_days(): void
    {
        $product = new Product;
        $product->warranty_period = 30;
        $product->warranty_unit = 'days';
        $product->has_guarantee = false;
        $product->guarantee_period = null;
        $product->guarantee_unit = null;

        $base = Carbon::parse('2026-02-13');
        $result = SaleDetail::computeWarrantyGuaranteeDates($product, $base);

        $this->assertSame('2026-03-15', $result['warranty_date']);
        $this->assertNull($result['guarantee_date']);
    }

    /**
     * Guarantee duration in months is added correctly.
     */
    public function test_compute_guarantee_date_with_months(): void
    {
        $product = new Product;
        $product->warranty_period = null;
        $product->warranty_unit = null;
        $product->has_guarantee = true;
        $product->guarantee_period = 12;
        $product->guarantee_unit = 'months';

        $base = Carbon::parse('2026-02-13');
        $result = SaleDetail::computeWarrantyGuaranteeDates($product, $base);

        $this->assertNull($result['warranty_date']);
        $this->assertSame('2027-02-13', $result['guarantee_date']);
    }

    /**
     * Both warranty and guarantee can be set.
     */
    public function test_compute_both_warranty_and_guarantee_dates(): void
    {
        $product = new Product;
        $product->warranty_period = 1;
        $product->warranty_unit = 'years';
        $product->has_guarantee = true;
        $product->guarantee_period = 2;
        $product->guarantee_unit = 'years';

        $base = Carbon::parse('2026-02-13');
        $result = SaleDetail::computeWarrantyGuaranteeDates($product, $base);

        $this->assertSame('2027-02-13', $result['warranty_date']);
        $this->assertSame('2028-02-13', $result['guarantee_date']);
    }

    /**
     * Base date can be passed as string.
     */
    public function test_compute_accepts_string_base_date(): void
    {
        $product = new Product;
        $product->warranty_period = 7;
        $product->warranty_unit = 'days';
        $product->has_guarantee = false;
        $product->guarantee_period = null;
        $product->guarantee_unit = null;

        $result = SaleDetail::computeWarrantyGuaranteeDates($product, '2026-02-13');

        $this->assertSame('2026-02-20', $result['warranty_date']);
    }
}
