<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductPriceBulkService;
use App\Support\PriceRounding;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductPriceBulkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_cost_keep_margin_rounds_sale_price_up_to_the_chosen_step(): void
    {
        $product = $this->product(cost: 1000, sale: 1300, margin: 30);

        $updated = app(ProductPriceBulkService::class)->applyPercentage(
            collect([$product]),
            10,
            ProductPriceBulkService::MODE_COST_KEEP_MARGIN,
            50,
            PriceRounding::MODE_UP,
        );

        $product->refresh();

        $this->assertSame(1, $updated);
        $this->assertEquals(1100.0, (float) $product->cost_price);
        $this->assertEquals(1450.0, (float) $product->sale_price);
    }

    public function test_sale_only_rounds_the_new_sale_price(): void
    {
        $product = $this->product(cost: 1000, sale: 1300, margin: 30);

        app(ProductPriceBulkService::class)->applyPercentage(
            collect([$product]),
            10,
            ProductPriceBulkService::MODE_SALE_ONLY,
            100,
            PriceRounding::MODE_UP,
        );

        $product->refresh();

        $this->assertEquals(1000.0, (float) $product->cost_price);
        $this->assertEquals(1500.0, (float) $product->sale_price);
    }

    public function test_products_bulk_action_applies_rounding_from_the_modal(): void
    {
        $product = $this->product(cost: 1000, sale: 1300, margin: 30);

        Livewire::actingAs(auth()->user())
            ->test(ListProducts::class)
            ->selectTableRecords([$product->id])
            ->callAction(
                TestAction::make('adjustPrices')->table()->bulk(),
                [
                    'percentage' => 10,
                    'mode' => ProductPriceBulkService::MODE_COST_KEEP_MARGIN,
                    'rounding_step' => 50,
                    'rounding_mode' => PriceRounding::MODE_UP,
                ],
            );

        $product->refresh();

        $this->assertEquals(1100.0, (float) $product->cost_price);
        $this->assertEquals(1450.0, (float) $product->sale_price);
    }

    public function test_without_rounding_keeps_cent_precision(): void
    {
        $product = $this->product(cost: 1000, sale: 1300, margin: 30);

        app(ProductPriceBulkService::class)->applyPercentage(
            collect([$product]),
            10,
            ProductPriceBulkService::MODE_COST_KEEP_MARGIN,
        );

        $product->refresh();

        $this->assertEquals(1100.0, (float) $product->cost_price);
        $this->assertEquals(1430.0, (float) $product->sale_price);
    }

    private function product(float $cost, float $sale, float $margin): Product
    {
        $category = Category::create([
            'name' => 'Herramientas',
            'slug' => 'herramientas-'.uniqid(),
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Martillo',
            'cost_price' => $cost,
            'sale_price' => $sale,
            'margin_percentage' => $margin,
            'stock' => 10,
            'unit' => 'unidad',
            'active' => true,
        ]);
    }
}
