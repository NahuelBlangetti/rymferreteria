<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductBarcodesPdfTest extends TestCase
{
    public function test_barcodes_view_embeds_png_instead_of_text_badge(): void
    {
        $html = view('pdf.products-barcodes', [
            'products' => Collection::make([
                (object) ['name' => 'ABRAZADERA INOX STD 9MM 8-16MM PERFECTO', 'barcode' => 'RYM816'],
                (object) ['name' => 'Producto sin código', 'barcode' => null],
            ]),
            'store' => config('store'),
            'pdfTitle' => 'Códigos de Barras',
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('RYM816', $html);
        $this->assertStringContainsString('ABRAZADERA INOX STD 9MM 8-16MM PERFECTO', $html);
        $this->assertStringContainsString('class="barcode-img"', $html);
        $this->assertStringNotContainsString('class="badge">RYM816', $html);
    }

    public function test_barcodes_pdf_renders_without_errors(): void
    {
        $pdf = Pdf::loadView('pdf.products-barcodes', [
            'products' => Collection::make([
                (object) ['name' => 'ABRAZADERA INOX STD 9MM 8-16MM PERFECTO', 'barcode' => 'RYM816'],
            ]),
            'store' => config('store'),
            'pdfTitle' => 'Códigos de Barras',
        ])->setPaper('a4', 'portrait');

        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
    }
}
