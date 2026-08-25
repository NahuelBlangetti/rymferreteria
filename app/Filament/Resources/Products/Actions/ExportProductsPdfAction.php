<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Category;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProductsPdfAction
{
    private const TYPES = [
        'barcodes' => [
            'label' => 'Códigos de barras',
            'modalHeading' => 'Exportar códigos de barras',
            'description' => 'Listado con nombre del producto y el código de barras gráfico para imprimir y escanear.',
            'pdfTitle' => 'Códigos de Barras',
            'view' => 'pdf.products-barcodes',
            'orientation' => 'portrait',
            'filename' => 'codigos-barras',
        ],
        'price_list' => [
            'label' => 'Lista de precios (Clientes)',
            'modalHeading' => 'Exportar lista de precios para clientes',
            'description' => 'Para entregar o enviar a clientes. Incluye nombre, código, categoría y precio de venta.',
            'pdfTitle' => 'Lista de Precios — Clientes',
            'view' => 'pdf.products-price-list',
            'orientation' => 'portrait',
            'filename' => 'lista-precios',
        ],
        'inventory' => [
            'label' => 'Pedido a proveedor',
            'modalHeading' => 'Exportar pedido a proveedor',
            'description' => 'Indicá cuánto querés recibir de cada producto. Esa cantidad sale en el PDF para el proveedor.',
            'pdfTitle' => 'Pedido a proveedor',
            'view' => 'pdf.products-inventory',
            'orientation' => 'landscape',
            'filename' => 'pedido-proveedor',
        ],
    ];

    public static function make(string $type = 'price_list'): Action
    {
        $meta = self::TYPES[$type];

        return Action::make('exportPdf_'.$type)
            ->label($meta['label'])
            ->icon('heroicon-o-document-arrow-down')
            ->modalHeading($meta['modalHeading'])
            ->modalDescription(fn (HasTable $livewire): string => $meta['description'].' '
                .self::exportScopeDescription($livewire->getTableQueryForExport()->count()))
            ->modalSubmitActionLabel('Descargar PDF')
            ->modalWidth('sm')
            ->action(fn (HasTable $livewire): mixed => self::exportFromQuery(
                $livewire->getTableQueryForExport(),
                $type,
            ));
    }

    public static function bulk(string $type = 'price_list'): BulkAction
    {
        $meta = self::TYPES[$type];

        $action = BulkAction::make('exportPdf_'.$type)
            ->label($meta['label'])
            ->icon('heroicon-o-document-arrow-down')
            ->color('info')
            ->modalHeading($meta['modalHeading'])
            ->modalDescription(fn (Collection $records): string => $meta['description'].' '
                .self::exportScopeDescription($records->count()))
            ->modalSubmitActionLabel('Descargar PDF')
            ->modalWidth($type === 'inventory' ? '3xl' : 'sm')
            ->action(function (Collection $records, array $data) use ($type): mixed {
                $quantities = $type === 'inventory'
                    ? self::quantitiesFromForm($data)
                    : [];

                return self::exportFromCollection($records, $type, $quantities);
            })
            ->deselectRecordsAfterCompletion();

        if ($type === 'inventory') {
            $action
                ->schema(self::inventoryQuantitySchema())
                ->fillForm(fn (Collection $records): array => self::inventoryQuantityFormState($records));
        }

        return $action;
    }

    public static function exportFromQuery($query, string $type): mixed
    {
        $products = $query
            ->with(['category', 'supplier'])
            ->orderBy(
                Category::select('name')
                    ->whereColumn('categories.id', 'products.category_id'),
            )
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            Notification::make()
                ->title('Sin productos para exportar')
                ->body('No hay productos que coincidan con los filtros actuales.')
                ->warning()
                ->send();

            return null;
        }

        return self::download($products, $type);
    }

    /**
     * @param  array<int, float>  $quantities
     */
    public static function exportFromCollection(Collection $records, string $type, array $quantities = []): mixed
    {
        $records->load(['category', 'supplier']);

        $products = $records->sortBy(
            fn ($product) => ($product->category?->name ?? 'zzz').$product->name,
        )->values();

        if ($products->isEmpty()) {
            Notification::make()
                ->title('Sin productos para exportar')
                ->body('Seleccioná al menos un producto.')
                ->warning()
                ->send();

            return null;
        }

        return self::download($products, $type, $quantities);
    }

    /**
     * @param  array<int, float>  $quantities
     */
    public static function download(Collection $products, string $type, array $quantities = []): StreamedResponse
    {
        $meta = self::TYPES[$type];

        self::applyRequestedQuantities($products, $quantities);

        $pdf = Pdf::loadView($meta['view'], [
            'products' => $products,
            'store' => config('store'),
            'pdfTitle' => $meta['pdfTitle'],
        ])->setPaper('a4', $meta['orientation']);

        $slug = str(config('store.name'))->slug();
        $filename = "{$meta['filename']}-{$slug}-".now()->format('Y-m-d').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * @param  SupportCollection<int, Product>  $products
     * @param  array<int, float>  $quantities
     */
    public static function applyRequestedQuantities(SupportCollection $products, array $quantities): void
    {
        $products->each(function (Product $product) use ($quantities): void {
            if (! array_key_exists($product->id, $quantities)) {
                return;
            }

            $product->setAttribute('requested_quantity', $quantities[$product->id]);
        });
    }

    /**
     * @return array<int, Hidden|Repeater>
     */
    private static function inventoryQuantitySchema(): array
    {
        return [
            Repeater::make('items')
                ->label('Productos')
                ->helperText('La cantidad sugerida cubre el faltante hasta el stock mínimo. Cambiala si querés pedir más o menos.')
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->compact()
                ->table([
                    TableColumn::make('Producto'),
                    TableColumn::make('Stock actual'),
                    TableColumn::make('Cantidad a pedir')->markAsRequired(),
                ])
                ->schema([
                    Hidden::make('product_id'),
                    Hidden::make('unit'),
                    TextInput::make('name')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('stock_label')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->minValue(fn (Get $get): float => in_array($get('unit'), Product::FRACTIONAL_UNITS, true) ? 0.001 : 1)
                        ->step(fn (Get $get): float => in_array($get('unit'), Product::FRACTIONAL_UNITS, true) ? 0.001 : 1)
                        ->suffix(fn (Get $get): string => self::unitLabel($get('unit')))
                        ->autofocus(),
                ]),
        ];
    }

    /**
     * @return array{items: list<array{product_id: int, unit: string|null, name: string, stock_label: string, quantity: float}>}
     */
    private static function inventoryQuantityFormState(Collection $records): array
    {
        return [
            'items' => $records
                ->sortBy(fn (Product $product) => ($product->category?->name ?? 'zzz').$product->name)
                ->values()
                ->map(fn (Product $product): array => [
                    'product_id' => $product->id,
                    'unit' => $product->unit,
                    'name' => $product->name,
                    'stock_label' => Product::formatQuantity($product->stock)
                        .' (mín. '.Product::formatQuantity($product->min_stock).')',
                    'quantity' => $product->suggestedOrderQuantity(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, float>
     */
    private static function quantitiesFromForm(array $data): array
    {
        $quantities = [];

        foreach ($data['items'] ?? [] as $item) {
            $id = (int) ($item['product_id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $quantities[$id] = (float) ($item['quantity'] ?? 0);
        }

        return $quantities;
    }

    private static function unitLabel(?string $unit): string
    {
        return match ($unit) {
            'unidad' => 'Unid.',
            'metro' => 'Metro',
            'm2' => 'm²',
            'kg' => 'Kg',
            'g' => 'Gr',
            'litro' => 'Litro',
            'caja' => 'Caja',
            'rollo' => 'Rollo',
            'par' => 'Par',
            'docena' => 'Doc.',
            default => $unit ?? '',
        };
    }

    private static function exportScopeDescription(int $count): string
    {
        return $count === 1
            ? 'Se exportará 1 producto.'
            : "Se exportarán {$count} productos.";
    }
}
