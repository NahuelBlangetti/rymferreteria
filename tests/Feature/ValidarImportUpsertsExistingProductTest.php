<?php

namespace Tests\Feature;

use App\Filament\Pages\ValidarImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValidarImportUpsertsExistingProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduce una recarga de lista de precios: el producto ya existía en el
     * catálogo (sku "MART-01"), pero el nombre cambió levemente en el nuevo
     * archivo, así que al revisar no quedó vinculado a "existing_product_id"
     * (el matching por nombre no dio). Antes esto se insertaba como producto
     * nuevo — createProducts() ahora vuelve a chequear contra la base antes
     * de guardar y lo actualiza en vez de duplicarlo.
     */
    public function test_a_row_without_existing_product_id_updates_the_product_matched_by_sku(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas-'.uniqid()]);

        $existing = Product::create([
            'name' => 'Martillo viejo',
            'sku' => 'MART-01',
            'barcode' => null,
            'unit' => 'unidad',
            'cost_price' => 1000,
            'sale_price' => 1300,
            'margin_percentage' => 30,
            'stock' => 5,
            'category_id' => $category->id,
            'active' => true,
        ]);

        $import = ProductImport::create([
            'user_id' => $user->id,
            'filename' => 'lista.xlsx',
            'file_path' => 'imports/lista.xlsx',
            'status' => 'done',
            'products' => [
                [
                    'selected' => true,
                    'action' => 'create',
                    'existing_product_id' => null,
                    'name' => 'Martillo de acero (nuevo modelo)',
                    'sku' => 'MART-01',
                    'barcode' => null,
                    'unit' => 'unidad',
                    'cost_price' => 1200,
                    'sale_price' => 1560,
                    'stock' => 8,
                    'min_stock' => 0,
                    'category_id' => $category->id,
                ],
            ],
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['id' => $import->id])
            ->test(ValidarImport::class)
            ->call('createProducts');

        $this->assertSame(1, Product::count());

        $existing->refresh();
        $this->assertSame('Martillo de acero (nuevo modelo)', $existing->name);
        $this->assertSame(1560.0, (float) $existing->sale_price);
        $this->assertSame('validated', $import->fresh()->status);
    }
}
