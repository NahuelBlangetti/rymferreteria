<?php

namespace Tests\Unit;

use App\Models\Product;
use Tests\TestCase;

class ProductSuggestedOrderQuantityTest extends TestCase
{
    public function test_suggests_the_gap_up_to_min_stock(): void
    {
        $product = new Product([
            'unit' => 'unidad',
            'stock' => 5,
            'min_stock' => 20,
        ]);

        $this->assertSame(15.0, $product->suggestedOrderQuantity());
    }

    public function test_suggests_one_when_stock_already_covers_the_minimum(): void
    {
        $product = new Product([
            'unit' => 'unidad',
            'stock' => 20,
            'min_stock' => 10,
        ]);

        $this->assertSame(1.0, $product->suggestedOrderQuantity());
    }

    public function test_keeps_decimals_for_fractional_units(): void
    {
        $product = new Product([
            'unit' => 'metro',
            'stock' => 1.5,
            'min_stock' => 5,
        ]);

        $this->assertSame(3.5, $product->suggestedOrderQuantity());
    }

    public function test_format_quantity_strips_trailing_zeros(): void
    {
        $this->assertSame('12', Product::formatQuantity(12));
        $this->assertSame('12,5', Product::formatQuantity(12.5));
        $this->assertSame('1,25', Product::formatQuantity(1.250));
    }
}
