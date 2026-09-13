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

class ValidarImportUpdatesFirstTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las actualizaciones de productos ya conocidos van primero; las altas
     * nuevas (que requieren revisar nombre, precio y categoría desde cero)
     * quedan al final, para que el administrador no tenga que scrollear
     * entre las altas para llegar a las actualizaciones.
     */
    public function test_updates_are_shown_before_new_products_regardless_of_file_order(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'General', 'slug' => 'general-'.uniqid()]);

        $productA = Product::create([
            'name' => 'Actualizar A', 'unit' => 'unidad', 'cost_price' => 100, 'sale_price' => 150,
            'margin_percentage' => 50, 'stock' => 1, 'category_id' => $category->id, 'active' => true,
        ]);
        $productB = Product::create([
            'name' => 'Actualizar B', 'unit' => 'unidad', 'cost_price' => 200, 'sale_price' => 250,
            'margin_percentage' => 25, 'stock' => 1, 'category_id' => $category->id, 'active' => true,
        ]);

        $row = fn (string $name, string $action, ?int $existingId) => [
            'name' => $name,
            'action' => $action,
            'existing_product_id' => $existingId,
            'selected' => true,
            'duplicate' => null,
            'sku' => null,
            'barcode' => null,
            'unit' => 'unidad',
            'cost_price' => 100,
            'sale_price' => 150,
            'stock' => 1,
            'min_stock' => 0,
            'category_id' => $category->id,
            'existing_cost' => null,
            'existing_sale' => null,
            'existing_margin' => null,
            'price_direction' => null,
        ];

        $import = ProductImport::create([
            'user_id' => $user->id,
            'filename' => 'lista.xlsx',
            'file_path' => 'imports/lista.xlsx',
            'status' => 'done',
            'products' => [
                $row('Nuevo A', 'create', null),
                $row('Actualizar A', 'update', $productA->id),
                $row('Nuevo B', 'create', null),
                $row('Actualizar B', 'update', $productB->id),
            ],
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['id' => $import->id])
            ->test(ValidarImport::class)
            ->assertSet('products.0.name', 'Actualizar A')
            ->assertSet('products.1.name', 'Actualizar B')
            ->assertSet('products.2.name', 'Nuevo A')
            ->assertSet('products.3.name', 'Nuevo B');
    }
}
