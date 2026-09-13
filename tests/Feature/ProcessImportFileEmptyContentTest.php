<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportFile;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImportFileEmptyContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_marked_as_error_when_the_file_only_has_headers(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'test-key']);

        $user = User::factory()->create();
        $csv = "Código,Descripción,Categoría,Cantidad en stock,Precio de costo,Precio de venta\n";
        Storage::disk('local')->put('imports/vacio.csv', $csv);

        $import = ProductImport::create([
            'user_id' => $user->id,
            'filename' => 'vacio.csv',
            'file_path' => 'imports/vacio.csv',
            'file_hash' => hash('sha256', $csv),
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['products' => []])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        (new ProcessImportFile($import->id))->handle();

        $import->refresh();

        $this->assertSame('error', $import->status);
        $this->assertStringContainsString('No se encontraron productos', $import->error_message);
        $this->assertFalse(Storage::disk('local')->exists('imports/vacio.csv'));
    }
}
