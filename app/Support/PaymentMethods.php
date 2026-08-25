<?php

namespace App\Support;

class PaymentMethods
{
    public const CASH = 'cash';

    public const TRANSFER = 'transfer';

    public const CARD = 'card';

    public const MIXED = 'mixed';

    public const METHODS = [
        self::CASH,
        self::TRANSFER,
        self::CARD,
    ];

    public static function labels(): array
    {
        return [
            self::CASH => 'Efectivo',
            self::TRANSFER => 'Transferencia',
            self::CARD => 'Tarjeta',
            self::MIXED => 'Mixto',
        ];
    }

    public static function options(): array
    {
        return [
            self::CASH => 'Efectivo',
            self::TRANSFER => 'Transferencia',
            self::CARD => 'Tarjeta',
        ];
    }

    public static function label(string $method): string
    {
        return self::labels()[$method] ?? $method;
    }

    public static function color(string $method): string
    {
        return match ($method) {
            self::CASH => 'success',
            self::TRANSFER => 'info',
            self::CARD => 'warning',
            self::MIXED => 'gray',
            default => 'gray',
        };
    }
}
