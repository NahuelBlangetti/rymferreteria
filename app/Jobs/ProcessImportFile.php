<?php

namespace App\Jobs;

use App\Filament\Pages\ValidarImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\DiscordNotifier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;

class ProcessImportFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int  $timeout      = 600; // 10 minutos máximo
    public int  $tries        = 1;   // sin reintentos — las llamadas a OpenAI son costosas
    public bool $failOnTimeout = true;

    private const OPENAI_INPUT_COST_PER_MILLION_TOKENS = 0.40;
    private const CHUNK_SIZE = 4000;
    private const MAX_CHUNKS = 12;

    private const ALLOWED_UNITS = ['unidad', 'metro', 'm2', 'kg', 'g', 'litro', 'caja', 'rollo', 'par', 'docena'];

    private const UNIT_SYNONYMS = [
        'uni' => 'unidad', 'un' => 'unidad', 'und' => 'unidad', 'unid' => 'unidad',
        'u' => 'unidad', 'pza' => 'unidad', 'pieza' => 'unidad',
        'mt' => 'metro', 'mts' => 'metro', 'm' => 'metro', 'metros' => 'metro',
        'm2' => 'm2', 'mt2' => 'm2', 'metros2' => 'm2', 'metro2' => 'm2',
        'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kilogramo' => 'kg', 'kilogramos' => 'kg',
        'g' => 'g', 'gr' => 'g', 'gramo' => 'g', 'gramos' => 'g',
        'lt' => 'litro', 'lts' => 'litro', 'l' => 'litro', 'litros' => 'litro',
        'cja' => 'caja', 'cj' => 'caja', 'cajas' => 'caja', 'box' => 'caja',
        'rollos' => 'rollo', 'rll' => 'rollo',
        'pares' => 'par',
        'doc' => 'docena', 'dz' => 'docena', 'docenas' => 'docena',
    ];

    public function __construct(private int $importId) {}

    public function handle(): void
    {
        $import = ProductImport::findOrFail($this->importId);
        $import->update(['status' => 'processing']);

        try {
            set_time_limit(0);

            $fullPath  = Storage::disk('local')->path($import->file_path);
            $extension = strtolower(pathinfo($import->filename, PATHINFO_EXTENSION));

            $text = $extension === 'pdf'
                ? $this->extractTextFromPdf($fullPath)
                : $this->extractTextFromSpreadsheet($fullPath);

            if (trim($text) === '') {
                throw new \RuntimeException('No se pudo extraer texto del archivo. ¿Es un PDF escaneado (imagen) o una planilla vacía?');
            }

            $text   = $this->filterText($text);
            $chunks = $this->chunkText($text);

            $allExtracted = [];
            foreach ($chunks as $i => $chunk) {
                $context      = count($chunks) > 1
                    ? "{$import->filename} (parte " . ($i + 1) . ' de ' . count($chunks) . ')'
                    : $import->filename;
                $allExtracted = array_merge($allExtracted, $this->callOpenAiApi($chunk, $context));
            }

            $products = $this->detectDuplicates($this->mapToCategories($allExtracted));
            $count    = count($products);

            $import->update([
                'status'        => 'done',
                'products'      => $products,
                'product_count' => $count,
                'processed_at'  => now(),
            ]);

            Notification::make()
                ->title('Importación lista ✓')
                ->body(
                    ($count > 0
                        ? "{$count} " . ($count === 1 ? 'producto extraído' : 'productos extraídos')
                        : 'No se detectaron productos')
                    . " de \"{$import->filename}\". Revisalos antes de guardar."
                )
                ->success()
                ->persistent()
                ->actions([
                    Action::make('validar')
                        ->label('Revisar y guardar →')
                        ->url(ValidarImport::getUrl(['id' => $import->id]))
                        ->button(),
                ])
                ->sendToDatabase($import->user);

        } catch (\Throwable $e) {
            $this->failImport($import, $e);
        } finally {
            if (Storage::disk('local')->exists($import->file_path)) {
                Storage::disk('local')->delete($import->file_path);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $import = ProductImport::with('user')->find($this->importId);

        if (! $import) {
            return;
        }

        if ($import->status === 'done') {
            return;
        }

        $this->failImport($import, $exception, notifyDiscord: ! ($exception instanceof MaxAttemptsExceededException));
    }

    private function failImport(ProductImport $import, \Throwable $e, bool $notifyDiscord = true): void
    {
        $import->loadMissing('user');

        $message = $e instanceof MaxAttemptsExceededException
            ? 'El procesamiento tardó demasiado o el servidor se quedó sin memoria. Probá con un archivo más chico.'
            : $e->getMessage();

        $import->update([
            'status'        => 'error',
            'error_message' => $message,
        ]);

        if ($notifyDiscord) {
            (new DiscordNotifier())->notify(
                '❌ Error al procesar importación',
                sprintf(
                    "**Archivo:** %s\n**Usuario:** %s\n**Error:** %s\n**Línea:** %s:%d",
                    $import->filename,
                    $import->user->email ?? "ID {$import->user_id}",
                    $message,
                    basename($e->getFile()),
                    $e->getLine()
                ),
                0xED4245
            );
        }

        if ($import->user) {
            Notification::make()
                ->title('No se pudo procesar el archivo')
                ->body("Ocurrió un error analizando \"{$import->filename}\". Por favor intentá de nuevo más tarde. Si el problema persiste, contactá a soporte.")
                ->danger()
                ->persistent()
                ->sendToDatabase($import->user);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Extracción de texto
    // ══════════════════════════════════════════════════════════════════════

    private function extractTextFromPdf(string $path): string
    {
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('No se pudo leer el PDF: ' . $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    private function extractTextFromSpreadsheet(string $path): string
    {
        $spreadsheet = IOFactory::load($path);
        $lines       = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $lines[] = "--- Hoja: {$sheet->getTitle()} ---";

            foreach ($sheet->toArray(null, true, true, false) as $row) {
                $row = array_map(fn ($cell) => trim((string) $cell), $row);

                if (implode('', $row) === '') {
                    continue;
                }

                $lines[] = implode(' | ', $row);
            }
        }

        return implode("\n", $lines);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Llamada a OpenAI
    // ══════════════════════════════════════════════════════════════════════

    private function callOpenAiApi(string $text, string $context): array
    {
        $instructions = <<<PROMPT
        Sos un asistente que extrae productos de listas de precios de ferretería a partir de texto plano sacado de un PDF o de una planilla Excel (puede tener columnas, secciones por rubro, precios con o sin IVA, etc.).

        Devolvé ÚNICAMENTE un objeto JSON con esta estructura exacta:

        {
          "products": [
            {
              "name": "nombre del producto",
              "sku": "código o SKU del proveedor, o null si no figura",
              "barcode": "código de barras, o null si no figura",
              "unit": "unidad de venta, tiene que ser EXACTAMENTE uno de estos valores: unidad, metro, m2, kg, g, litro, caja, rollo, par, docena. Elegí el más parecido a lo que figura en el texto, 'unidad' si no hay forma de saberlo",
              "cost_price": numero con el precio de costo (sin simbolos ni separadores de miles, punto como decimal),
              "sale_price": numero con el precio de venta sugerido si figura, 0 si no figura,
              "stock": numero con la cantidad en stock si figura, 0 si no figura,
              "category": "rubro o categoría a la que pertenece según cómo esté organizada la lista, o null"
            }
          ]
        }

        Reglas:
        - No inventes productos que no estén en el texto.
        - Ignorá encabezados, totales, pies de página y líneas que no sean productos.
        - Los números deben ser numéricos, sin "$" ni separadores de miles.
        PROMPT;

        $payload = [
            'model'           => config('services.openai.model'),
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $instructions],
                ['role' => 'user',   'content' => $text],
            ],
        ];

        $response = Http::withToken(config('services.openai.key'))
            ->connectTimeout(15)
            ->timeout(180)
            ->retry(3, 8000, function (\Throwable $e): bool {
                // Reintentar solo en timeouts y errores de red, no en 4xx (auth, rate limit, etc.)
                return $e instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            $status = $response->status();
            $body   = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("Error en la API de OpenAI ({$status}): " . Str::limit($body, 300));
        }

        $promptTokens     = (int) $response->json('usage.prompt_tokens', 0);
        $completionTokens = (int) $response->json('usage.completion_tokens', 0);
        $estimatedCost    = $promptTokens / 1_000_000 * self::OPENAI_INPUT_COST_PER_MILLION_TOKENS;

        (new DiscordNotifier())->notifyOpenAiUsage($context, $promptTokens, $completionTokens, $estimatedCost);

        $content = (string) $response->json('choices.0.message.content', '');
        $data    = json_decode($content, true);

        if (! is_array($data) || ! isset($data['products']) || ! is_array($data['products'])) {
            throw new \RuntimeException('La IA no devolvió un JSON válido.');
        }

        return $data['products'];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Procesamiento de texto
    // ══════════════════════════════════════════════════════════════════════

    private function filterText(string $text): string
    {
        $lines     = explode("\n", $text);
        $frequency = [];
        $result    = [];

        foreach ($lines as $line) {
            $key = mb_strtolower(trim($line));
            if ($key !== '') {
                $frequency[$key] = ($frequency[$key] ?? 0) + 1;
            }
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (mb_strlen($trimmed) < 4) {
                continue;
            }

            if (preg_match('/^[\-=_\.·\*\#]{3,}$/', $trimmed)) {
                continue;
            }

            $key = mb_strtolower($trimmed);
            if (($frequency[$key] ?? 0) > 4) {
                continue;
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    private function chunkText(string $text): array
    {
        $chunks    = [];
        $remaining = trim($text);

        while (mb_strlen($remaining) > 0 && count($chunks) < self::MAX_CHUNKS) {
            if (mb_strlen($remaining) <= self::CHUNK_SIZE) {
                $chunks[] = $remaining;
                break;
            }

            $slice       = mb_substr($remaining, 0, self::CHUNK_SIZE);
            $lastNewline = mb_strrpos($slice, "\n");

            if ($lastNewline !== false && $lastNewline > self::CHUNK_SIZE * 0.6) {
                $slice = mb_substr($remaining, 0, $lastNewline + 1);
            }

            $chunks[]  = trim($slice);
            $remaining = trim(mb_substr($remaining, mb_strlen($slice)));
        }

        return array_values(array_filter($chunks));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Mapeo y detección de duplicados
    // ══════════════════════════════════════════════════════════════════════

    private function mapToCategories(array $extracted): array
    {
        $categories = Category::query()->pluck('id', 'name');

        return collect($extracted)->map(function (array $item) use ($categories) {
            $categoryName = $item['category'] ?? null;
            $categoryId   = null;

            if ($categoryName) {
                foreach ($categories as $name => $id) {
                    if (str_contains(mb_strtolower($name), mb_strtolower($categoryName))
                        || str_contains(mb_strtolower($categoryName), mb_strtolower($name))) {
                        $categoryId = $id;
                        break;
                    }
                }
            }

            return [
                'selected'            => true,
                'action'              => 'create',
                'name'                => $item['name'] ?? '',
                'sku'                 => $item['sku'] ?? null,
                'barcode'             => $item['barcode'] ?? null,
                'unit'                => $this->normalizeUnit($item['unit'] ?? null),
                'cost_price'          => (float) ($item['cost_price'] ?? 0),
                'sale_price'          => (float) ($item['sale_price'] ?? 0),
                'stock'               => (int) ($item['stock'] ?? 0),
                'min_stock'           => 0,
                'category_raw'        => $categoryName,
                'category_id'         => $categoryId,
                'duplicate'           => null,
                'existing_product_id' => null,
                'existing_cost'       => null,
                'existing_sale'       => null,
                'price_direction'     => null,
            ];
        })->values()->all();
    }

    private function detectDuplicates(array $rows): array
    {
        $existingProducts = Product::query()
            ->select(['id', 'name', 'sku', 'barcode', 'cost_price', 'sale_price'])
            ->get();

        $existingByKey = $existingProducts->reduce(function (array $carry, Product $product) {
            $entry = [
                'id'         => $product->id,
                'name'       => $product->name,
                'cost_price' => (float) $product->cost_price,
                'sale_price' => (float) $product->sale_price,
            ];

            if ($product->barcode) {
                $carry['barcode'][mb_strtolower(trim($product->barcode))] = $entry;
            }

            $carry['name'][mb_strtolower(trim($product->name))] = $entry;

            return $carry;
        }, ['barcode' => [], 'name' => []]);

        $seenBarcodes = [];
        $seenNames    = [];

        foreach ($rows as &$row) {
            $barcode = $row['barcode'] ? mb_strtolower(trim($row['barcode'])) : null;
            $name    = mb_strtolower(trim($row['name']));

            $existingEntry = null;
            $reason        = null;

            if ($barcode && isset($existingByKey['barcode'][$barcode])) {
                $existingEntry = $existingByKey['barcode'][$barcode];
                $reason        = "Ya existe con este código de barras: \"{$existingEntry['name']}\"";
            } elseif (isset($existingByKey['name'][$name])) {
                $existingEntry = $existingByKey['name'][$name];
                $reason        = 'Ya existe un producto con este nombre';
            } elseif ($barcode && isset($seenBarcodes[$barcode])) {
                $reason = 'Código de barras repetido dentro de este archivo';
            } elseif (isset($seenNames[$name])) {
                $reason = 'Nombre repetido dentro de este archivo';
            }

            if ($barcode) {
                $seenBarcodes[$barcode] = true;
            }
            $seenNames[$name] = true;

            $row['duplicate'] = $reason;

            if ($existingEntry) {
                $existingCost = $existingEntry['cost_price'];
                $existingSale = $existingEntry['sale_price'];
                $newCost      = (float) $row['cost_price'];

                $row['existing_product_id'] = $existingEntry['id'];
                $row['existing_cost']       = $existingCost;
                $row['existing_sale']       = $existingSale;
                $row['action']              = 'update';
                $row['selected']            = true;

                if ($existingCost > 0 && abs($newCost - $existingCost) > 0.001) {
                    $row['price_direction'] = $newCost > $existingCost ? 'up' : 'down';
                } else {
                    $row['price_direction'] = 'same';
                }
            } elseif ($reason) {
                $row['selected'] = false;
            }
        }

        return $rows;
    }

    private function normalizeUnit(?string $raw): string
    {
        if (! $raw) {
            return 'unidad';
        }

        $normalized = mb_strtolower(trim($raw));

        if (in_array($normalized, self::ALLOWED_UNITS, true)) {
            return $normalized;
        }

        return self::UNIT_SYNONYMS[$normalized] ?? 'unidad';
    }
}
