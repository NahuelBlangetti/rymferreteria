<?php

namespace Tests\Feature;

use App\Filament\Pages\ValidarImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use App\Support\PriceRounding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImportPriceRoundingTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_suggests_sale_price_from_existing_margin_when_file_has_none(): void
    {
        [$user, $import] = $this->importWithExistingProduct(newCost: 1100, fileSale: 0);

        Livewire::actingAs($user)
            ->withQueryParams(['id' => $import->id])
            ->test(ValidarImport::class)
            ->assertSet('products.0.sale_price', 1430.0);
    }

    public function test_admin_can_round_sale_prices_of_existing_products_during_validation(): void
    {
        [$user, $import] = $this->importWithExistingProduct(newCost: 1100, fileSale: 1430);

        Livewire::actingAs($user)
            ->withQueryParams(['id' => $import->id])
            ->test(ValidarImport::class)
            ->set('roundingStep', 50)
            ->set('roundingMode', PriceRounding::MODE_UP)
            ->call('applySalePriceRounding')
            ->assertSet('products.0.sale_price', 1450.0)
            ->assertSet('products.1.sale_price', 280.0);
    }

    public function test_rounding_skips_unselected_existing_products(): void
    {
        [$user, $import] = $this->importWithExistingProduct(newCost: 1100, fileSale: 1430, selected: false);

        Livewire::actingAs($user)
            ->withQueryParams(['id' => $import->id])
            ->test(ValidarImport::class)
            ->set('roundingStep', 100)
            ->call('applySalePriceRounding')
            ->assertSet('products.0.sale_price', 1430.0);
    }

    public function test_create_products_persists_the_rounded_sale_price(): void
    {
        [$user, $import, $product] = $this->importWithExistingProduct(newCost: 1100, fileSale: 1430);

        Livewire::actingAs($user)
            ->withQueryParams(['id' => $import->id])
            ->test(ValidarImport::class)
            ->set('roundingStep', 50)
            ->call('applySalePriceRounding')
            ->call('createProducts');

        $product->refresh();

        $this->assertEquals(1100.0, (float) $product->cost_price);
        $this->assertEquals(1450.0, (float) $product->sale_price);
        $this->assertEquals('validated', $import->fresh()->status);
    }

    /**
     * @return array{0: User, 1: ProductImport, 2: Product}
     */
    private function importWithExistingProduct(float $newCost, float $fileSale, bool $selected = true): array
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Herramientas',
            'slug' => 'herramientas-'.uniqid(),
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Martillo',
            'cost_price' => 1000,
            'sale_price' => 1300,
            'margin_percentage' => 30,
            'stock' => 10,
            'unit' => 'unidad',
            'active' => true,
        ]);

        $import = ProductImport::create([
            'user_id' => $user->id,
            'filename' => 'lista.xlsx',
            'file_path' => 'imports/lista.xlsx',
            'status' => 'done',
            'products' => [
                [
                    'selected' => $selected,
                    'action' => 'update',
                    'name' => 'Martillo',
                    'sku' => null,
                    'barcode' => null,
                    'unit' => 'unidad',
                    'cost_price' => $newCost,
                    'sale_price' => $fileSale,
                    'stock' => 0,
                    'min_stock' => 0,
                    'category_id' => $category->id,
                    'existing_product_id' => $product->id,
                    'existing_cost' => 1000,
                    'existing_sale' => 1300,
                    'existing_margin' => 30,
                    'price_direction' => 'up',
                    'duplicate' => 'Ya existe un producto con este nombre',
                ],
                [
                    'selected' => true,
                    'action' => 'create',
                    'name' => 'Destornillador',
                    'sku' => null,
                    'barcode' => null,
                    'unit' => 'unidad',
                    'cost_price' => 200,
                    'sale_price' => 280,
                    'stock' => 0,
                    'min_stock' => 0,
                    'category_id' => $category->id,
                    'existing_product_id' => null,
                    'existing_cost' => null,
                    'existing_sale' => null,
                    'existing_margin' => null,
                    'price_direction' => null,
                    'duplicate' => null,
                ],
            ],
        ]);

        return [$user, $import, $product];
    }
}
