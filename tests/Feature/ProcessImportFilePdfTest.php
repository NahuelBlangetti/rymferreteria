<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportFile;
use App\Models\ProductImport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImportFilePdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Todos los tests de import usan Excel/CSV; el camino de PDF (pdftotext
     * vía Symfony\Process) nunca se ejercitó de punta a punta. Generamos un
     * PDF real con dompdf (ya es dependencia del proyecto para reportes) para
     * confirmar que la extracción de texto del PDF funciona en este entorno.
     */
    public function test_a_real_pdf_is_extracted_and_processed_end_to_end(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'test-key']);

        $user = User::factory()->create();

        $pdfContent = Pdf::loadHTML('<p>Martillo de acero | SKU MART-01 | $1560</p>')->output();
        Storage::disk('local')->put('imports/lista.pdf', $pdfContent);

        $import = ProductImport::create([
            'user_id' => $user->id,
            'filename' => 'lista.pdf',
            'file_path' => 'imports/lista.pdf',
            'file_hash' => hash('sha256', $pdfContent),
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
                                'category' => null,
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
        $this->assertSame(1, $import->product_count);
        $this->assertSame('Martillo de acero', $import->products[0]['name']);
    }
}
