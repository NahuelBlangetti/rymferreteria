<?php

namespace App\Filament\Pages;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class CrearVenta extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Nueva Venta';

    protected static ?string $title = 'Nueva Venta';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.crear-venta';

    // Barcode / búsqueda manual
    public string $barcodeInput = '';
    public string $searchQuery  = '';
    public array  $searchResults = [];

    // Carrito
    public array $cartItems = [];

    // Pago
    public string $paymentMethod = '';
    public string $notes = '';

    // Número de venta creada (para confirmación)
    public ?string $lastSaleNumber = null;

    // ── Getters reactivos ──────────────────────────────────────────────

    public function getSubtotal(): float
    {
        return collect($this->cartItems)->sum('subtotal');
    }

    public function getCartCount(): int
    {
        return collect($this->cartItems)->sum('quantity');
    }

    #[Computed]
    public function hasCashRegisterOpen(): bool
    {
        return CashRegister::where('status', 'open')->exists();
    }

    // ── Escáner de código de barras ────────────────────────────────────

    public function addByBarcode(): void
    {
        $code = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if (empty($code)) {
            return;
        }

        $product = Product::where('active', true)
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->first();

        if (! $product) {
            Notification::make()
                ->title("Código no encontrado: {$code}")
                ->warning()
                ->send();

            return;
        }

        $this->addToCart($product->id);
    }

    // ── Búsqueda manual (live search) ─────────────────────────────────

    public function updatedSearchQuery(): void
    {
        if (strlen($this->searchQuery) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = Product::where('active', true)
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->searchQuery}%")
                ->orWhere('sku', 'like', "%{$this->searchQuery}%")
                ->orWhere('barcode', 'like', "%{$this->searchQuery}%")
            )
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'sale_price', 'stock', 'unit', 'image'])
            ->toArray();
    }

    // ── Carrito ────────────────────────────────────────────────────────

    public function addToCart(int $productId): void
    {
        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        $existingIndex = collect($this->cartItems)
            ->search(fn ($item) => $item['product_id'] === $productId);

        if ($product->sale_price <= 0) {
            Notification::make()
                ->title("Precio en $0: {$product->name}")
                ->body('Este producto no tiene precio de venta cargado. Verificá el producto antes de vender.')
                ->warning()
                ->persistent()
                ->send();
        }

        if ($existingIndex !== false) {
            $newQty         = $this->cartItems[$existingIndex]['quantity'] + 1;
            $availableStock = $this->cartItems[$existingIndex]['stock'];

            if ($newQty > $availableStock) {
                Notification::make()
                    ->title("Stock insuficiente: {$product->name}")
                    ->body("Solo hay {$availableStock} {$product->unit} disponibles.")
                    ->warning()
                    ->send();

                return;
            }

            $this->cartItems[$existingIndex]['quantity'] = $newQty;
            $this->cartItems[$existingIndex]['subtotal']  = $newQty * $this->cartItems[$existingIndex]['unit_price'];
        } else {
            if ($product->stock <= 0) {
                Notification::make()
                    ->title("Sin stock: {$product->name}")
                    ->body('Este producto no tiene unidades disponibles.')
                    ->warning()
                    ->send();

                return;
            }

            $this->cartItems[] = [
                'product_id' => $product->id,
                'name'       => $product->name,
                'unit'       => $product->unit,
                'unit_price' => (float) $product->sale_price,
                'quantity'   => 1,
                'subtotal'   => (float) $product->sale_price,
                'stock'      => $product->stock,
            ];
        }

        $this->searchQuery   = '';
        $this->searchResults = [];

        Notification::make()
            ->title("{$product->name} agregado")
            ->success()
            ->duration(1500)
            ->send();
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cartItems[$index]);
        $this->cartItems = array_values($this->cartItems);
    }

    public function updateQuantity(int $index, mixed $quantity): void
    {
        $qty = (int) $quantity;

        if ($qty <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $availableStock = $this->cartItems[$index]['stock'];

        if ($qty > $availableStock) {
            Notification::make()
                ->title('Stock insuficiente')
                ->body("Solo hay {$availableStock} {$this->cartItems[$index]['unit']} disponibles.")
                ->warning()
                ->send();

            return;
        }

        $this->cartItems[$index]['quantity'] = $qty;
        $this->cartItems[$index]['subtotal'] = $qty * $this->cartItems[$index]['unit_price'];
    }

    public function clearCart(): void
    {
        $this->cartItems    = [];
        $this->paymentMethod = '';
        $this->notes         = '';
        $this->lastSaleNumber = null;
    }

    // ── Confirmar venta ───────────────────────────────────────────────

    public function confirmSale(): void
    {
        if (empty($this->cartItems)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();

            return;
        }

        if (empty($this->paymentMethod)) {
            Notification::make()->title('Seleccioná un método de pago')->warning()->send();

            return;
        }

        $cashRegister = CashRegister::where('status', 'open')->first();

        if (! $cashRegister) {
            Notification::make()
                ->title('No hay caja abierta')
                ->body('Debés abrir una caja antes de registrar una venta.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($cashRegister) {
                // Lock all products upfront and validate stock before crear nada
                $products = [];

                foreach ($this->cartItems as $item) {
                    $product = Product::lockForUpdate()->find($item['product_id']);

                    if (! $product || $product->stock < $item['quantity']) {
                        $available = $product ? $product->stock : 0;

                        throw new \RuntimeException(
                            "Stock insuficiente para \"{$item['name']}\". " .
                            "Disponible: {$available} {$item['unit']}. " .
                            "En carrito: {$item['quantity']}."
                        );
                    }

                    $products[$item['product_id']] = $product;
                }

                $subtotal = $this->getSubtotal();

                $sale = Sale::create([
                    'user_id'          => Auth::id(),
                    'cash_register_id' => $cashRegister->id,
                    'payment_method'   => $this->paymentMethod,
                    'subtotal'         => $subtotal,
                    'discount'         => 0,
                    'total'            => $subtotal,
                    'notes'            => $this->notes,
                    'status'           => 'completed',
                ]);

                foreach ($this->cartItems as $item) {
                    SaleItem::create([
                        'sale_id'      => $sale->id,
                        'product_id'   => $item['product_id'],
                        'product_name' => $item['name'],
                        'unit_price'   => $item['unit_price'],
                        'quantity'     => $item['quantity'],
                        'subtotal'     => $item['subtotal'],
                    ]);

                    $product     = $products[$item['product_id']];
                    $stockBefore = $product->stock;
                    $product->decrement('stock', $item['quantity']);

                    StockMovement::create([
                        'product_id'     => $product->id,
                        'user_id'        => Auth::id(),
                        'type'           => 'out',
                        'quantity'       => $item['quantity'],
                        'stock_before'   => $stockBefore,
                        'stock_after'    => $stockBefore - $item['quantity'],
                        'notes'          => "Venta {$sale->sale_number}",
                        'reference_type' => Sale::class,
                        'reference_id'   => $sale->id,
                    ]);
                }

                $this->lastSaleNumber = $sale->sale_number;
            });
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('No se pudo registrar la venta')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->cartItems     = [];
        $this->paymentMethod = '';
        $this->notes         = '';

        Notification::make()
            ->title("¡Venta {$this->lastSaleNumber} registrada!")
            ->body('El stock fue actualizado automáticamente.')
            ->success()
            ->persistent()
            ->send();
    }
}
