<?php

namespace App\Filament\Pages;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\Tickets\SaleTicketEscPosBuilder;
use App\Support\PaymentMethods;
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

    // Búsqueda / escáner unificado
    public string $productQuery = '';

    public array $searchResults = [];

    // Carrito
    public array $cartItems = [];

    // Pago: uno o más medios. Si hay más de uno, paymentAmounts guarda el monto de cada parte.
    public array $selectedPaymentMethods = [];

    /** @var array<string, float|int|string> */
    public array $paymentAmounts = [];

    public string $notes = '';

    public bool $printTicket = true;

    // Número de venta creada (para confirmación)
    public ?string $lastSaleNumber = null;

    // ── Getters reactivos ──────────────────────────────────────────────

    public function getSubtotal(): float
    {
        return collect($this->cartItems)->sum('subtotal');
    }

    public function getCartCount(): float
    {
        return collect($this->cartItems)->sum('quantity');
    }

    #[Computed]
    public function hasCashRegisterOpen(): bool
    {
        return CashRegister::where('status', 'open')->exists();
    }

    // ── Búsqueda / escáner unificado ───────────────────────────────────

    public function updatedProductQuery(): void
    {
        $query = trim($this->productQuery);

        if (strlen($query) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = $this->findProducts($query);
    }

    public function addProduct(): void
    {
        $query = trim($this->productQuery);

        if ($query === '') {
            return;
        }

        $exact = Product::where('active', true)
            ->where(fn ($q) => $q->where('barcode', $query)->orWhere('sku', $query))
            ->first();

        if ($exact) {
            $this->resetProductSearch();
            $this->addToCart($exact->id);
            $this->dispatch('focus-product-search');

            return;
        }

        if (strlen($query) >= 2 && empty($this->searchResults)) {
            $this->searchResults = $this->findProducts($query);
        }

        if (count($this->searchResults) === 1) {
            $productId = $this->searchResults[0]['id'];
            $this->resetProductSearch();
            $this->addToCart($productId);
            $this->dispatch('focus-product-search');

            return;
        }

        Notification::make()
            ->title('Producto no encontrado')
            ->body(count($this->searchResults) > 1
                ? 'Hay varios resultados. Seleccioná uno de la lista.'
                : "No hay productos para \"{$query}\".")
            ->warning()
            ->send();
    }

    private function findProducts(string $query): array
    {
        return Product::where('active', true)
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$query}%")
                ->orWhere('sku', 'like', "%{$query}%")
                ->orWhere('barcode', 'like', "%{$query}%")
            )
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'sale_price', 'stock', 'unit', 'sku', 'barcode'])
            ->toArray();
    }

    private function resetProductSearch(): void
    {
        $this->productQuery = '';
        $this->searchResults = [];
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
            if ($product->isFractional()) {
                Notification::make()
                    ->title("{$product->name} ya está en el carrito")
                    ->body("Editá la cantidad en {$product->unit} directamente en el carrito.")
                    ->warning()
                    ->send();

                return;
            }

            $newQty = $this->cartItems[$existingIndex]['quantity'] + 1;
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
            $this->cartItems[$existingIndex]['subtotal'] = $newQty * $this->cartItems[$existingIndex]['unit_price'];
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
                'name' => $product->name,
                'unit' => $product->unit,
                'is_fractional' => $product->isFractional(),
                'unit_price' => (float) $product->sale_price,
                'quantity' => 1,
                'subtotal' => (float) $product->sale_price,
                'stock' => (float) $product->stock,
            ];
        }

        $this->resetProductSearch();
        $this->dispatch('focus-product-search');

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
        $isFractional = $this->cartItems[$index]['is_fractional'] ?? false;
        $qty = round((float) str_replace(',', '.', (string) $quantity), 3);
        $qty = $isFractional ? $qty : (float) (int) $qty;

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
        $this->cartItems = [];
        $this->resetPayment();
        $this->notes = '';
        $this->lastSaleNumber = null;
    }

    public function togglePaymentMethod(string $method): void
    {
        if (! in_array($method, PaymentMethods::METHODS, true)) {
            return;
        }

        if (in_array($method, $this->selectedPaymentMethods, true)) {
            $this->selectedPaymentMethods = array_values(array_filter(
                $this->selectedPaymentMethods,
                fn (string $selected): bool => $selected !== $method
            ));
            unset($this->paymentAmounts[$method]);
        } else {
            $this->selectedPaymentMethods[] = $method;
        }

        $this->redistributePaymentAmounts();
    }

    public function splitPaymentEqually(): void
    {
        $this->redistributePaymentAmounts();
    }

    public function updatedPaymentAmounts(mixed $value, string $key): void
    {
        if (count($this->selectedPaymentMethods) !== 2) {
            return;
        }

        $other = collect($this->selectedPaymentMethods)
            ->first(fn (string $method): bool => $method !== $key);

        if ($other === null) {
            return;
        }

        $this->paymentAmounts[$other] = round($this->getSubtotal() - (float) str_replace(',', '.', (string) $value), 2);
    }

    public function isPaymentMethodSelected(string $method): bool
    {
        return in_array($method, $this->selectedPaymentMethods, true);
    }

    public function getPaymentPartsTotal(): float
    {
        return round(collect($this->selectedPaymentMethods)
            ->sum(fn (string $method): float => (float) ($this->paymentAmounts[$method] ?? 0)), 2);
    }

    public function paymentCoversTotal(): bool
    {
        if ($this->selectedPaymentMethods === []) {
            return false;
        }

        if (count($this->selectedPaymentMethods) === 1) {
            return true;
        }

        foreach ($this->selectedPaymentMethods as $method) {
            if ((float) ($this->paymentAmounts[$method] ?? 0) <= 0) {
                return false;
            }
        }

        return abs($this->getPaymentPartsTotal() - $this->getSubtotal()) < 0.01;
    }

    /**
     * @return list<array{method: string, amount: float}>
     */
    public function resolvedPaymentParts(): array
    {
        $total = round($this->getSubtotal(), 2);

        if (count($this->selectedPaymentMethods) === 1) {
            return [[
                'method' => $this->selectedPaymentMethods[0],
                'amount' => $total,
            ]];
        }

        $parts = [];

        foreach ($this->selectedPaymentMethods as $method) {
            $parts[] = [
                'method' => $method,
                'amount' => round((float) ($this->paymentAmounts[$method] ?? 0), 2),
            ];
        }

        return $parts;
    }

    private function resetPayment(): void
    {
        $this->selectedPaymentMethods = [];
        $this->paymentAmounts = [];
    }

    private function redistributePaymentAmounts(): void
    {
        $methods = $this->selectedPaymentMethods;
        $count = count($methods);
        $this->paymentAmounts = array_intersect_key($this->paymentAmounts, array_flip($methods));

        if ($count === 0) {
            return;
        }

        $total = round($this->getSubtotal(), 2);

        if ($count === 1) {
            $this->paymentAmounts[$methods[0]] = $total;

            return;
        }

        $share = round($total / $count, 2);
        $assigned = 0.0;

        foreach ($methods as $index => $method) {
            if ($index === $count - 1) {
                $this->paymentAmounts[$method] = round($total - $assigned, 2);
            } else {
                $this->paymentAmounts[$method] = $share;
                $assigned += $share;
            }
        }
    }

    // ── Confirmar venta ───────────────────────────────────────────────

    public function confirmSale(): void
    {
        if (empty($this->cartItems)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();

            return;
        }

        if ($this->selectedPaymentMethods === []) {
            Notification::make()->title('Seleccioná un método de pago')->warning()->send();

            return;
        }

        if (! $this->paymentCoversTotal()) {
            Notification::make()
                ->title('El pago mixto no cubre el total')
                ->body('La suma de las partes tiene que ser igual al total de la venta.')
                ->warning()
                ->send();

            return;
        }

        $paymentParts = $this->resolvedPaymentParts();
        $paymentMethod = count($paymentParts) > 1
            ? PaymentMethods::MIXED
            : $paymentParts[0]['method'];

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

        $sale = null;

        try {
            DB::transaction(function () use ($cashRegister, $paymentMethod, $paymentParts, &$sale) {
                // Lock all products upfront and validate stock before crear nada
                $products = [];

                foreach ($this->cartItems as $item) {
                    $product = Product::lockForUpdate()->find($item['product_id']);

                    if (! $product || $product->stock < $item['quantity']) {
                        $available = $product ? $product->stock : 0;

                        throw new \RuntimeException(
                            "Stock insuficiente para \"{$item['name']}\". ".
                            "Disponible: {$available} {$item['unit']}. ".
                            "En carrito: {$item['quantity']}."
                        );
                    }

                    $products[$item['product_id']] = $product;
                }

                $subtotal = $this->getSubtotal();

                $sale = Sale::create([
                    'user_id' => Auth::id(),
                    'cash_register_id' => $cashRegister->id,
                    'payment_method' => $paymentMethod,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'total' => $subtotal,
                    'notes' => $this->notes,
                    'status' => 'completed',
                ]);

                foreach ($this->cartItems as $item) {
                    $product = $products[$item['product_id']];

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                    ]);

                    $stockBefore = $product->stock;
                    $product->decrement('stock', $item['quantity']);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'user_id' => Auth::id(),
                        'type' => 'out',
                        'quantity' => $item['quantity'],
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore - $item['quantity'],
                        'notes' => "Venta {$sale->sale_number}",
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                    ]);
                }

                $sale->storePayments($paymentParts);

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

        $this->cartItems = [];
        $this->resetPayment();
        $this->notes = '';

        if ($this->printTicket) {
            $ticket = app(SaleTicketEscPosBuilder::class)->build($sale);
            $this->dispatch('print-escpos-ticket', content: $ticket);
        }

        Notification::make()
            ->title("¡Venta {$this->lastSaleNumber} registrada!")
            ->body('El stock fue actualizado automáticamente.')
            ->success()
            ->persistent()
            ->send();
    }
}
