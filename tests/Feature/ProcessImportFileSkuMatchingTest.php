<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportFile;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImportFileSkuMatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * No todos los proveedores mandan código de barras (EAN); muchos solo traen
     * un código interno (sku). El import tiene que reconocer ese producto como
     * ya existente por sku, no solo por barcode.
     */
    public function test_products_are_matched_against_existing_stock_by_sku_when_there_is_no_barcode(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'test-key']);

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

        $csv = "Código,Descripción,Precio de costo,Precio de venta\nMART-01,Martillo de acero,1200,1560\n";
        Storage::disk('local')->put('imports/con-sku.csv', $csv);

        $import = ProductImport::create([
            'user_id' => $user->id,
            'filename' => 'con-sku.csv',
            'file_path' => 'imports/con-sku.csv',
            'file_hash' => hash('sha256', $csv),
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'products' => [
                            [
                                'name' => 'Martillo de acero',
                                'sku' => 'MART-01',
                                'barcode' => null,
                                'unit' => 'unidad',
                                'cost_price' => 1200,
                                'sale_price' => 1560,
                                'category' => 'Herramientas',
                            ],
                        ],
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        (new ProcessImportFile($import->id))->handle();

        $import->refresh();

        $this->assertSame('done', $import->status);

        $row = $import->products[0];
        $this->assertSame('MART-01', $row['sku']);
        $this->assertSame('update', $row['action']);
        $this->assertSame($existing->id, $row['existing_product_id']);
        $this->assertSame('Ya existe con este SKU: "Martillo viejo"', $row['duplicate']);
    }
}
