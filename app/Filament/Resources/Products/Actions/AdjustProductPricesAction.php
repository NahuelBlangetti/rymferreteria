<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductPriceBulkService;
use App\Support\PriceRounding;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;

class AdjustProductPricesAction
{
    public static function make(?int $fixedSupplierId = null): Action
    {
        return Action::make('adjustPrices')
            ->label($fixedSupplierId ? 'Ajustar precios' : 'Ajuste masivo de precios')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('warning')
            ->modalHeading('Ajuste masivo de precios')
            ->modalDescription('Actualizá los precios de todos los productos de un proveedor. Los cambios quedan registrados en el historial de precios.')
            ->modalSubmitActionLabel('Aplicar ajuste')
            ->modalWidth('md')
            ->schema([
                Select::make('supplier_id')
                    ->label('Proveedor')
                    ->options(fn (): array => Supplier::query()
                        ->where('active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(fn ($record): bool => blank($fixedSupplierId) && blank($record))
                    ->hidden(fn ($record): bool => filled($fixedSupplierId) || filled($record))
                    ->dehydrated(fn ($record): bool => blank($fixedSupplierId) && blank($record)),
                ...self::adjustmentFields(),
            ])
            ->fillForm(function ($record) use ($fixedSupplierId): array {
                return [
                    'supplier_id' => $fixedSupplierId ?? $record?->id,
                    'percentage' => 30,
                    'mode' => ProductPriceBulkService::MODE_COST_KEEP_MARGIN,
                    'rounding_step' => 0,
                    'rounding_mode' => PriceRounding::MODE_UP,
                ];
            })
            ->action(function (array $data, $record = null) use ($fixedSupplierId): void {
                $supplierId = (int) ($fixedSupplierId ?? $record?->id ?? $data['supplier_id']);
                $percentage = (float) $data['percentage'];
                $mode = $data['mode'];
                $roundingStep = (float) ($data['rounding_step'] ?? 0);
                $roundingMode = $data['rounding_mode'] ?? PriceRounding::MODE_UP;

                $updated = app(ProductPriceBulkService::class)->applyPercentage(
                    Product::query()->where('supplier_id', $supplierId),
                    $percentage,
                    $mode,
                    $roundingStep,
                    $roundingMode,
                );

                if ($updated === 0) {
                    Notification::make()
                        ->title('Sin productos para actualizar')
                        ->body('El proveedor seleccionado no tiene productos asociados.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Precios actualizados')
                    ->body(self::successBody($updated, $percentage, $roundingStep, $roundingMode))
                    ->success()
                    ->send();
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('adjustPrices')
            ->label('Ajustar precios')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('warning')
            ->modalHeading('Ajustar precios de productos seleccionados')
            ->modalSubmitActionLabel('Aplicar ajuste')
            ->modalWidth('md')
            ->schema(self::adjustmentFields())
            ->action(function (Collection $records, array $data): void {
                $percentage = (float) $data['percentage'];
                $roundingStep = (float) ($data['rounding_step'] ?? 0);
                $roundingMode = $data['rounding_mode'] ?? PriceRounding::MODE_UP;

                $updated = app(ProductPriceBulkService::class)->applyPercentage(
                    $records,
                    $percentage,
                    $data['mode'],
                    $roundingStep,
                    $roundingMode,
                );

                Notification::make()
                    ->title('Precios actualizados')
                    ->body(self::successBody($updated, $percentage, $roundingStep, $roundingMode))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return array<int, Select|TextInput>
     */
    private static function adjustmentFields(): array
    {
        return [
            TextInput::make('percentage')
                ->label('Porcentaje de aumento')
                ->numeric()
                ->required()
                ->default(30)
                ->suffix('%')
                ->minValue(0.01)
                ->maxValue(1000)
                ->step(0.01),
            Select::make('mode')
                ->label('Aplicar sobre')
                ->required()
                ->default(ProductPriceBulkService::MODE_COST_KEEP_MARGIN)
                ->options([
                    ProductPriceBulkService::MODE_COST_KEEP_MARGIN => 'Precio de costo (recalcular venta manteniendo margen %)',
                    ProductPriceBulkService::MODE_SALE_ONLY => 'Solo precio de venta',
                    ProductPriceBulkService::MODE_BOTH => 'Costo y venta (mismo % en ambos)',
                ]),
            Select::make('rounding_step')
                ->label('Redondear precio de venta')
                ->options(PriceRounding::STEPS)
                ->default(0)
                ->required()
                ->live()
                ->helperText('Se aplica al precio de venta después del aumento. El costo no se redondea.'),
            Select::make('rounding_mode')
                ->label('Tipo de redondeo')
                ->options(PriceRounding::MODES)
                ->default(PriceRounding::MODE_UP)
                ->required()
                ->visible(fn (Get $get): bool => (int) ($get('rounding_step') ?? 0) > 0),
        ];
    }

    private static function successBody(int $updated, float $percentage, float $step, string $roundingMode): string
    {
        $body = "Se actualizaron {$updated} producto(s) con un aumento del {$percentage}%.";

        if ($step < 0.01) {
            return $body;
        }

        $stepLabel = PriceRounding::STEPS[(int) $step] ?? "a \${$step}";
        $modeLabel = PriceRounding::MODES[$roundingMode] ?? $roundingMode;

        return $body." Redondeo de venta: {$stepLabel} · {$modeLabel}.";
    }
}
