<?php

namespace App\Filament\Pages;

use App\Models\Product;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;

class CargaRapida extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Carga Rápida';

    protected static ?string $title = 'Carga Rápida';

    public function getSubheading(): ?string
    {
        return 'Escaneá, completá y guardá productos en segundos';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.carga-rapida';

    // Escáner
    public string $barcodeInput = '';

    // Formulario rápido
    public string $productName = '';
    public string $salePrice   = '';
    public string $stockInput  = ''; // stock inicial opcional
    public bool   $showForm    = false;
    public bool   $manualMode  = false; // carga sin código de barras

    // Resultado del lookup de API
    public ?string $lookupSource = null; // 'api' | null

    // Sesión
    public int   $sessionCount    = 0;
    public array $recentProducts  = [];

    // ── Escáner ───────────────────────────────────────────────────────

    public function scanBarcode(): void
    {
        $code = trim($this->barcodeInput);

        if (empty($code)) {
            return;
        }

        $existing = Product::where('barcode', $code)->orWhere('sku', $code)->first();

        if ($existing) {
            Notification::make()
                ->title("'{$existing->name}' ya está cargado")
                ->body("Código: {$code}")
                ->warning()
                ->send();

            $this->barcodeInput = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // Intentar obtener el nombre desde la API
        $this->productName  = '';
        $this->salePrice    = '';
        $this->stockInput   = '';
        $this->lookupSource = null;
        $this->manualMode   = false;

        $apiName = $this->lookupFromApi($code);

        if ($apiName) {
            $this->productName  = $apiName;
            $this->lookupSource = 'api';
        }

        $this->showForm = true;
        $this->dispatch('focus-name');
    }

    /** Abre el formulario para cargar un producto sin código de barras. */
    public function startManualEntry(): void
    {
        $this->barcodeInput = '';
        $this->productName  = '';
        $this->salePrice    = '';
        $this->stockInput   = '';
        $this->lookupSource = null;
        $this->manualMode   = true;
        $this->showForm     = true;
        $this->dispatch('focus-name');
    }

    // ── Guardar producto ──────────────────────────────────────────────

    public function saveProduct(): void
    {
        $name  = trim($this->productName);
        $price = (float) str_replace(',', '.', $this->salePrice);

        if (empty($name)) {
            Notification::make()->title('Escribí el nombre del producto')->warning()->send();
            $this->dispatch('focus-name');
            return;
        }

        if ($price <= 0) {
            Notification::make()->title('El precio debe ser mayor a $0')->warning()->send();
            $this->dispatch('focus-price');
            return;
        }

        $barcode = trim($this->barcodeInput);
        $stock   = max(0, (int) str_replace(',', '.', $this->stockInput));

        $product = Product::create([
            'barcode'    => $barcode !== '' ? $barcode : null,
            'name'       => $name,
            'sale_price' => $price,
            'cost_price' => 0,
            'stock'      => $stock,
            'min_stock'  => 0,
            'unit'       => 'unidad',
            'active'     => true,
        ]);

        $this->sessionCount++;
        array_unshift($this->recentProducts, [
            'id'    => $product->id,
            'name'  => $product->name,
            'price' => (float) $product->sale_price,
            'stock' => (int) $product->stock,
        ]);
        $this->recentProducts = array_slice($this->recentProducts, 0, 10);

        $this->resetForm();

        Notification::make()
            ->title("'{$product->name}' guardado")
            ->success()
            ->duration(1800)
            ->send();
    }

    /** Deshace un producto recién cargado (lo elimina y lo saca de la lista). */
    public function removeRecent(int $id): void
    {
        $index = collect($this->recentProducts)->search(fn ($p) => ($p['id'] ?? null) === $id);

        if ($index === false) {
            return;
        }

        $name = $this->recentProducts[$index]['name'] ?? 'Producto';

        Product::where('id', $id)->delete();

        unset($this->recentProducts[$index]);
        $this->recentProducts = array_values($this->recentProducts);
        $this->sessionCount   = max(0, $this->sessionCount - 1);

        Notification::make()
            ->title("'{$name}' eliminado")
            ->body('Se deshizo la carga.')
            ->success()
            ->duration(1800)
            ->send();

        $this->dispatch('focus-barcode');
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->barcodeInput = '';
        $this->productName  = '';
        $this->salePrice    = '';
        $this->stockInput   = '';
        $this->showForm     = false;
        $this->lookupSource = null;
        $this->manualMode   = false;
        $this->dispatch('focus-barcode');
    }

    private function lookupFromApi(string $barcode): ?string
    {
        try {
            $response = Http::timeout(3)->get('https://api.upcitemdb.com/prod/trial/lookup', [
                'upc' => $barcode,
            ]);

            if ($response->successful()) {
                $title = $response->json('items.0.title');
                return $title ? mb_convert_case(mb_strtolower($title), MB_CASE_TITLE) : null;
            }
        } catch (\Throwable) {
            // API no disponible, continuar sin autocompletado
        }

        return null;
    }
}
