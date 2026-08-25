<?php

namespace App\Models;

use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'cash_register_id',
        'sale_number',
        'payment_method',
        'subtotal',
        'discount',
        'total',
        'notes',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (empty($sale->sale_number)) {
                // Placeholder único mientras la BD asigna el ID. Se reemplaza en 'created'.
                $sale->sale_number = 'V-TEMP-'.uniqid('', true);
            }
        });

        static::created(function (Sale $sale) {
            // Usa el ID auto-increment como fuente del número: único por definición, sin race condition.
            $sale->updateQuietly([
                'sale_number' => 'V-'.str_pad($sale->id, 6, '0', STR_PAD_LEFT),
            ]);

            if ($sale->payment_method !== PaymentMethods::MIXED && $sale->payments()->doesntExist()) {
                $sale->payments()->create([
                    'method' => $sale->payment_method,
                    'amount' => $sale->total,
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @param  list<array{method: string, amount: float|int|string}>  $parts
     */
    public function storePayments(array $parts): void
    {
        $this->payments()->delete();

        foreach ($parts as $part) {
            $this->payments()->create([
                'method' => $part['method'],
                'amount' => $part['amount'],
            ]);
        }

        $methods = collect($parts)->pluck('method')->unique()->values();

        $this->updateQuietly([
            'payment_method' => $methods->count() > 1
                ? PaymentMethods::MIXED
                : ($methods->first() ?? $this->payment_method),
        ]);
    }

    public function paymentLabel(): string
    {
        return PaymentMethods::label($this->payment_method);
    }

    public function paymentSummary(): string
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        if ($payments->isEmpty()) {
            return $this->paymentLabel();
        }

        if ($payments->count() === 1) {
            return PaymentMethods::label($payments->first()->method);
        }

        return $payments
            ->map(fn (SalePayment $payment) => PaymentMethods::label($payment->method)
                .' '.CashRegister::formatMoney((float) $payment->amount))
            ->implode(' · ');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
