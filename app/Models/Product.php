<?php

namespace App\Models;

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
        'cost_price'        => 'decimal:2',
        'sale_price'        => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'stock'             => 'decimal:3',
        'min_stock'         => 'decimal:3',
        'active'            => 'boolean',
    ];

    public function isFractional(): bool
    {
        return in_array($this->unit, self::FRACTIONAL_UNITS, true);
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
