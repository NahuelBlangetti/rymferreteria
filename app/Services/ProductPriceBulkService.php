<?php

namespace App\Services;

use App\Models\Product;
use App\Support\PriceRounding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductPriceBulkService
{
    public const MODE_COST_KEEP_MARGIN = 'cost_keep_margin';

    public const MODE_SALE_ONLY = 'sale_only';

    public const MODE_BOTH = 'both';

    /**
     * @param  Builder<Product>|Collection<int, Product>  $products
     */
    public function applyPercentage(
        Builder|Collection $products,
        float $percentage,
        string $mode,
        float $roundingStep = 0,
        string $roundingMode = PriceRounding::MODE_UP,
    ): int {
        if ($products instanceof Builder) {
            $products = $products->get();
        }

        if ($products->isEmpty()) {
            return 0;
        }

        $multiplier = 1 + ($percentage / 100);
        $updated = 0;

        DB::transaction(function () use ($products, $multiplier, $mode, $roundingStep, $roundingMode, &$updated): void {
            foreach ($products as $product) {
                $changes = $this->calculateChanges($product, $multiplier, $mode, $roundingStep, $roundingMode);

                if ($changes === null) {
                    continue;
                }

                $product->update($changes);
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * @return array<string, float>|null
     */
    private function calculateChanges(
        Product $product,
        float $multiplier,
        string $mode,
        float $roundingStep,
        string $roundingMode,
    ): ?array {
        $cost = (float) $product->cost_price;
        $sale = (float) $product->sale_price;
        $margin = (float) $product->margin_percentage;
        $newCost = round($cost * $multiplier, 2);
        $newSale = round($sale * $multiplier, 2);

        $changes = match ($mode) {
            self::MODE_COST_KEEP_MARGIN => [
                'cost_price' => $newCost,
                'sale_price' => PriceRounding::saleFromCost($newCost, $margin, $roundingStep, $roundingMode),
            ],
            self::MODE_SALE_ONLY => [
                'sale_price' => PriceRounding::round($newSale, $roundingStep, $roundingMode),
            ],
            self::MODE_BOTH => [
                'cost_price' => $newCost,
                'sale_price' => PriceRounding::round($newSale, $roundingStep, $roundingMode),
            ],
            default => null,
        };

        if ($changes === null) {
            return null;
        }

        $newCost = (float) ($changes['cost_price'] ?? $cost);
        $newSale = (float) $changes['sale_price'];

        if ($newCost > 0) {
            $changes['margin_percentage'] = PriceRounding::marginFromPrices($newCost, $newSale);
        }

        return $changes;
    }

    public function countForSupplier(int $supplierId): int
    {
        return Product::query()
            ->where('supplier_id', $supplierId)
            ->count();
    }
}
