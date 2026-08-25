<?php

namespace App\Support;

class PaymentMethods
{
    public const CASH = 'cash';

    public const TRANSFER = 'transfer';

    public const DEBIT = 'debit';

    public const CREDIT = 'credit';

    /** Ventas anteriores a la separación débito/crédito. */
    public const CARD = 'card';

    public const MIXED = 'mixed';

    public const METHODS = [
        self::CASH,
        self::TRANSFER,
        self::DEBIT,
        self::CREDIT,
    ];

    public static function labels(): array
    {
        return [
            self::CASH => 'Efectivo',
            self::TRANSFER => 'Transferencia',
            self::DEBIT => 'Débito',
            self::CREDIT => 'Crédito',
            self::CARD => 'Tarjeta',
            self::MIXED => 'Mixto',
        ];
    }

    public static function options(): array
    {
        return [
            self::CASH => 'Efectivo',
            self::TRANSFER => 'Transferencia',
            self::DEBIT => 'Débito',
            self::CREDIT => 'Crédito',
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
            self::DEBIT => 'warning',
            self::CREDIT => 'danger',
            self::CARD => 'warning',
            self::MIXED => 'gray',
            default => 'gray',
        };
    }
}
