<?php

namespace Tests\Unit;

use App\Models\PaymentSurchargeSetting;
use App\Services\PaymentPriceCalculator;
use App\Support\PaymentMethods;
use App\Support\PriceRounding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_base_price_when_percentages_are_zero(): void
    {
        $calculator = app(PaymentPriceCalculator::class);

        $this->assertSame(1000.0, $calculator->apply(1000, PaymentMethods::CASH));
        $this->assertSame(1000.0, $calculator->apply(1000, PaymentMethods::CREDIT));
    }

    public function test_applies_the_configured_percentage_and_shows_only_the_final_price(): void
    {
        PaymentSurchargeSetting::current()->update([
            'debit_percentage' => 8,
            'credit_percentage' => 15,
        ]);
        PaymentSurchargeSetting::forgetCache();

        $calculator = app(PaymentPriceCalculator::class);

        $this->assertSame(1000.0, $calculator->apply(1000, PaymentMethods::CASH));
        $this->assertSame(1080.0, $calculator->apply(1000, PaymentMethods::DEBIT));
        $this->assertSame(1150.0, $calculator->apply(1000, PaymentMethods::CREDIT));
    }

    public function test_mixed_methods_use_the_highest_percentage(): void
    {
        PaymentSurchargeSetting::current()->update([
            'transfer_percentage' => 5,
            'credit_percentage' => 15,
        ]);
        PaymentSurchargeSetting::forgetCache();

        $calculator = app(PaymentPriceCalculator::class);

        $this->assertSame(
            1150.0,
            $calculator->applyForMethods(1000, [PaymentMethods::CASH, PaymentMethods::CREDIT]),
        );
        $this->assertSame(
            1050.0,
            $calculator->applyForMethods(1000, [PaymentMethods::CASH, PaymentMethods::TRANSFER]),
        );
    }

    public function test_rounds_the_final_price_after_applying_the_percentage(): void
    {
        PaymentSurchargeSetting::current()->update([
            'credit_percentage' => 15,
            'rounding_step' => 10,
            'rounding_mode' => PriceRounding::MODE_UP,
        ]);
        PaymentSurchargeSetting::forgetCache();

        $calculator = app(PaymentPriceCalculator::class);

        // 999 * 1.15 = 1148.85 → $1.150
        $this->assertSame(1150.0, $calculator->apply(999, PaymentMethods::CREDIT));
    }

    public function test_legacy_card_method_uses_the_credit_percentage(): void
    {
        PaymentSurchargeSetting::current()->update([
            'credit_percentage' => 15,
        ]);
        PaymentSurchargeSetting::forgetCache();

        $this->assertSame(
            1150.0,
            app(PaymentPriceCalculator::class)->apply(1000, PaymentMethods::CARD),
        );
    }
}
