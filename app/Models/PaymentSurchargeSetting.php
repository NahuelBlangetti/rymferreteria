<?php

namespace App\Models;

use App\Support\PaymentMethods;
use App\Support\PriceRounding;
use Illuminate\Database\Eloquent\Model;

class PaymentSurchargeSetting extends Model
{
    private static ?self $cached = null;

    protected $fillable = [
        'cash_percentage',
        'debit_percentage',
        'credit_percentage',
        'rounding_step',
        'rounding_mode',
    ];

    protected $casts = [
        'cash_percentage' => 'decimal:2',
        'debit_percentage' => 'decimal:2',
        'credit_percentage' => 'decimal:2',
        'rounding_step' => 'integer',
    ];

    public static function current(): self
    {
        return self::$cached ??= static::query()->firstOrCreate(
            ['id' => 1],
            [
                'cash_percentage' => 0,
                'debit_percentage' => 0,
                'credit_percentage' => 0,
                'rounding_step' => 0,
                'rounding_mode' => PriceRounding::MODE_UP,
            ]
        );
    }

    public static function forgetCache(): void
    {
        self::$cached = null;
    }

    /**
     * La transferencia se cobra igual que el efectivo (regla del negocio),
     * así que comparten porcentaje.
     */
    public function percentageFor(string $method): float
    {
        return match ($method) {
            PaymentMethods::CASH, PaymentMethods::TRANSFER => (float) $this->cash_percentage,
            PaymentMethods::DEBIT => (float) $this->debit_percentage,
            PaymentMethods::CREDIT, PaymentMethods::CARD => (float) $this->credit_percentage,
            default => 0.0,
        };
    }

    /**
     * @param  list<string>  $methods
     */
    public function percentageForMethods(array $methods): float
    {
        if ($methods === []) {
            return $this->percentageFor(PaymentMethods::CASH);
        }

        return max(array_map(fn (string $method): float => $this->percentageFor($method), $methods));
    }
}
