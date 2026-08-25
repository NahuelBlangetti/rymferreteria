<?php

namespace App\Support;

final class PriceRounding
{
    public const MODE_UP = 'up';

    public const MODE_NEAREST = 'nearest';

    /** @var array<int, string> */
    public const STEPS = [
        0 => 'Sin redondeo (centavos)',
        1 => 'A $1',
        5 => 'A $5',
        10 => 'A $10',
        50 => 'A $50',
        100 => 'A $100',
        500 => 'A $500',
        1000 => 'A $1.000',
    ];

    /** @var array<string, string> */
    public const MODES = [
        self::MODE_UP => 'Hacia arriba',
        self::MODE_NEAREST => 'Al más cercano',
    ];

    public static function saleFromCost(
        float $cost,
        float $margin,
        float $step = 0,
        string $mode = self::MODE_UP,
    ): float {
        if ($cost <= 0) {
            return 0.0;
        }

        $sale = round($cost * (1 + $margin / 100), 2);

        return self::round($sale, $step, $mode);
    }

    public static function round(float $price, float $step, string $mode = self::MODE_UP): float
    {
        $price = round($price, 2);

        if ($price <= 0) {
            return 0.0;
        }

        if ($step < 0.01) {
            return $price;
        }

        $ratio = $price / $step;
        $nearest = round($ratio);

        // Ya es múltiplo exacto (con tolerancia de float): no moverlo.
        if (abs($ratio - $nearest) < 1e-9) {
            return round($nearest * $step, 2);
        }

        if ($mode === self::MODE_NEAREST) {
            return round($nearest * $step, 2);
        }

        return round(ceil($ratio) * $step, 2);
    }

    public static function marginFromPrices(float $cost, float $sale): float
    {
        if ($cost <= 0) {
            return 0.0;
        }

        return round(($sale / $cost - 1) * 100, 2);
    }
}
