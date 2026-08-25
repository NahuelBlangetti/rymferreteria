<?php

namespace Tests\Unit;

use App\Support\PriceRounding;
use PHPUnit\Framework\TestCase;

class PriceRoundingTest extends TestCase
{
    public function test_sale_from_cost_keeps_margin_without_commercial_rounding(): void
    {
        // 1000 * 1.30 = 1300
        $this->assertSame(1300.0, PriceRounding::saleFromCost(1000, 30));
    }

    public function test_rounds_up_to_the_next_step(): void
    {
        $this->assertSame(1350.0, PriceRounding::round(1301.0, 50, PriceRounding::MODE_UP));
        $this->assertSame(1400.0, PriceRounding::round(1301.0, 100, PriceRounding::MODE_UP));
        $this->assertSame(10.0, PriceRounding::round(9.2, 1, PriceRounding::MODE_UP));
    }

    public function test_leaves_an_exact_multiple_unchanged(): void
    {
        $this->assertSame(1300.0, PriceRounding::round(1300.0, 50, PriceRounding::MODE_UP));
        $this->assertSame(1000.0, PriceRounding::round(1000.0, 100, PriceRounding::MODE_UP));
    }

    public function test_rounds_to_nearest_step(): void
    {
        $this->assertSame(1300.0, PriceRounding::round(1320.0, 100, PriceRounding::MODE_NEAREST));
        $this->assertSame(1400.0, PriceRounding::round(1360.0, 100, PriceRounding::MODE_NEAREST));
    }

    public function test_zero_step_only_keeps_cents(): void
    {
        $this->assertSame(1301.45, PriceRounding::round(1301.449, 0, PriceRounding::MODE_UP));
    }

    public function test_margin_from_prices(): void
    {
        $this->assertSame(30.0, PriceRounding::marginFromPrices(1000, 1300));
    }
}
