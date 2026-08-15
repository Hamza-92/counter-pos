<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductsController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ProductInputNormalizationTest extends TestCase
{
    public function test_legacy_null_strings_are_normalized_before_product_validation(): void
    {
        $request = Request::create('/api/products', 'POST', [
            'brand_id' => 'null',
            'sub_category_id' => '',
            'warranty_period' => 'undefined',
            'guarantee_period' => '12',
        ]);

        $controller = new class extends ProductsController
        {
            public function normalize(Request $request): void
            {
                $this->normalizeOptionalProductInputs($request);
            }
        };
        $controller->normalize($request);

        $this->assertNull($request->input('brand_id'));
        $this->assertNull($request->input('sub_category_id'));
        $this->assertNull($request->input('warranty_period'));
        $this->assertSame('12', $request->input('guarantee_period'));
        $this->assertFalse($request->boolean('has_guarantee'));
    }
}
