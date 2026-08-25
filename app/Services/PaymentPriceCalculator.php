<?php

namespace App\Services;

use App\Models\PaymentSurchargeSetting;
use App\Support\PaymentMethods;
use App\Support\PriceRounding;

class PaymentPriceCalculator
{
    public function apply(float $salePrice, string $method): float
    {
        return $this->applyPercentage($salePrice, $this->settings()->percentageFor($method));
    }

    /**
     * @param  list<string>  $methods
     */
    public function applyForMethods(float $salePrice, array $methods): float
    {
        return $this->applyPercentage($salePrice, $this->settings()->percentageForMethods($methods));
    }

    public function applyPercentage(float $salePrice, float $percentage): float
    {
        $settings = $this->settings();
        $price = round($salePrice * (1 + ($percentage / 100)), 2);

        return PriceRounding::round(
            $price,
            (float) $settings->rounding_step,
            $settings->rounding_mode ?: PriceRounding::MODE_UP,
        );
    }

    /**
     * @return array<string, float>
     */
    public function pricesFor(float $salePrice): array
    {
        $prices = [];

        foreach (PaymentMethods::METHODS as $method) {
            $prices[$method] = $this->apply($salePrice, $method);
        }

        return $prices;
    }

    public function settings(): PaymentSurchargeSetting
    {
        return PaymentSurchargeSetting::current();
    }
}
