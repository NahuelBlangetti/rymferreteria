<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Actions\ExportProductsPdfAction;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierOrderPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_pdf_includes_the_requested_quantity(): void
    {
        $product = $this->product(stock: 4, minStock: 10);
        $product->setAttribute('requested_quantity', 12);

        $html = view('pdf.products-inventory', [
            'products' => collect([$product]),
            'store' => config('store'),
            'pdfTitle' => 'Pedido a proveedor',
        ])->render();

        $this->assertStringContainsString('Cant. solicitada', $html);
        $this->assertStringContainsString('class="badge qty-requested"', $html);
        $this->assertStringContainsString('>12</span>', $html);
        $this->assertStringContainsString('Martillo', $html);
    }

    public function test_inventory_pdf_shows_a_placeholder_when_quantity_is_missing(): void
    {
        $html = view('pdf.products-inventory', [
            'products' => collect([$this->product()]),
            'store' => config('store'),
            'pdfTitle' => 'Pedido a proveedor',
        ])->render();

        $this->assertStringContainsString('Cant. solicitada', $html);
        $this->assertStringContainsString('class="no-data"', $html);
        $this->assertStringNotContainsString('class="badge qty-requested"', $html);
    }

    public function test_inventory_pdf_renders_with_requested_quantities(): void
    {
        $product = $this->product();
        ExportProductsPdfAction::applyRequestedQuantities(
            collect([$product]),
            [$product->id => 8],
        );

        $pdf = Pdf::loadView('pdf.products-inventory', [
            'products' => collect([$product]),
            'store' => config('store'),
            'pdfTitle' => 'Pedido a proveedor',
        ])->setPaper('a4', 'landscape');

        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_bulk_action_prefill_uses_the_suggested_order_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->product(stock: 4, minStock: 10);

        Livewire::actingAs($user)
            ->test(ListProducts::class)
            ->selectTableRecords([$product->id])
            ->mountAction(TestAction::make('exportPdf_inventory')->table()->bulk())
            ->assertSchemaStateSet(function (array $state) use ($product): array {
                $items = array_values($state['items'] ?? []);

                $this->assertCount(1, $items);
                $this->assertSame($product->id, (int) $items[0]['product_id']);
                $this->assertEquals(6, $items[0]['quantity']);

                return [];
            });
    }

    public function test_bulk_action_downloads_a_pdf_with_the_typed_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->product(stock: 4, minStock: 10);

        Livewire::actingAs($user)
            ->test(ListProducts::class)
            ->selectTableRecords([$product->id])
            ->callAction(
                TestAction::make('exportPdf_inventory')->table()->bulk(),
                [
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'unit' => 'unidad',
                            'quantity' => 18,
                        ],
                    ],
                ],
            )
            ->assertFileDownloaded();
    }

    private function product(float $stock = 10, float $minStock = 0): Product
    {
        $category = Category::create([
            'name' => 'Herramientas',
            'slug' => 'herramientas-'.uniqid(),
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Martillo',
            'sale_price' => 1000,
            'cost_price' => 700,
            'stock' => $stock,
            'min_stock' => $minStock,
            'unit' => 'unidad',
            'active' => true,
        ]);
    }
}
