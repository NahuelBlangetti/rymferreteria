<?php

namespace App\Models;

use App\Services\PaymentPriceCalculator;
use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    /**
     * Unidades que se venden fraccionadas (ej. manguera por metro) en vez
     * de en cantidades enteras.
     */
    public const FRACTIONAL_UNITS = ['metro', 'm2', 'kg', 'g', 'litro'];

    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'sku',
        'barcode',
        'unit',
        'description',
        'image',
        'cost_price',
        'sale_price',
        'margin_percentage',
        'stock',
        'min_stock',
        'active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'stock' => 'decimal:3',
        'min_stock' => 'decimal:3',
        'active' => 'boolean',
    ];

    public function isFractional(): bool
    {
        return in_array($this->unit, self::FRACTIONAL_UNITS, true);
    }

    /**
     * Cantidad sugerida para reponer hasta el stock mínimo.
     * Si el stock ya cubre el mínimo, sugiere 1.
     */
    public function suggestedOrderQuantity(): float
    {
        $needed = (float) $this->min_stock - (float) $this->stock;

        if ($needed <= 0) {
            return 1.0;
        }

        if ($this->isFractional()) {
            return round($needed, 3);
        }

        return (float) (int) ceil($needed);
    }

    public static function formatQuantity(float|int|string|null $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 3, ',', '.'), '0'), ',');
    }

    public function priceFor(?string $method = null): float
    {
        return app(PaymentPriceCalculator::class)->apply(
            (float) $this->sale_price,
            $method ?? PaymentMethods::CASH,
        );
    }

    /**
     * @return array<string, float>
     */
    public function pricesByPaymentMethod(): array
    {
        return app(PaymentPriceCalculator::class)->pricesFor((float) $this->sale_price);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
