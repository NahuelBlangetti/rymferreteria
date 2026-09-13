<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportFile;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProcessImportFileMultiSheetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduce el caso real: un Excel con la hoja "Stock" llena de productos
     * y una segunda hoja "Bicis" que solo tiene la fila de encabezados. El
     * import no debe marcarse como error solo porque una de las hojas esté
     * vacía — el sistema lee todas las hojas (getAllSheets()).
     */
    public function test_products_from_a_populated_sheet_are_extracted_even_if_another_sheet_is_empty(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'test-key']);

        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet;
        $stock = $spreadsheet->getActiveSheet();
        $stock->setTitle('Stock');
        $stock->fromArray(['Código', 'Descripción', 'Precio de costo', 'Precio de venta'], null, 'A1');
        $stock->fromArray(['ASI101', 'Asiento con elastomero', 9869.06, 19738.12], null, 'A2');

        $bicis = $spreadsheet->createSheet();
        $bicis->setTitle('Bicis');
        $bicis->fromArray(['Código', 'Descripción', 'Precio de costo', 'Precio de venta'], null, 'A1');

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($tmpPath);
        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        Storage::disk('local')->put('imports/multi-hoja.xlsx', $content);

        $import = ProductImport::create([
            'user_id' => $user->id,
            'filename' => 'multi-hoja.xlsx',
            'file_path' => 'imports/multi-hoja.xlsx',
            'file_hash' => hash('sha256', $content),
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'products' => [
                            [
                                'name' => 'Asiento con elastomero',
                                'sku' => 'ASI101',
                                'barcode' => '',
                                'unit' => 'unidad',
                                'cost_price' => 9869.06,
                                'sale_price' => 19738.12,
                                'stock' => 1,
                                'category' => 'Asientos',
                            ],
                        ],
                    ])]],
                ],
            ], 200),
        ]);

        (new ProcessImportFile($import->id))->handle();

        $import->refresh();

        $this->assertSame('done', $import->status);
        $this->assertSame(1, $import->product_count);
    }
}
