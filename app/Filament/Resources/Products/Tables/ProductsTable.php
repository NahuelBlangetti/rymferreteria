<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\Actions\AdjustProductPricesAction;
use App\Filament\Resources\Products\Actions\ExportProductsPdfAction;
use App\Filament\Resources\Products\Actions\PrintLabelAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction('edit')
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagen')
                    ->disk('public')
                    ->square()
                    ->imageSize(48)
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label('Código')
                    ->placeholder('Sin código')
                    ->searchable()
                    ->badge()
                    ->color(fn ($state): string => $state ? 'gray' : 'warning')
                    ->icon(fn ($state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->placeholder('Sin SKU')
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->sortable()
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('unit')
                    ->label('Unidad')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unidad' => 'Unid.',
                        'metro'  => 'Metro',
                        'm2'     => 'm²',
                        'kg'     => 'Kg',
                        'g'      => 'Gr',
                        'litro'  => 'Litro',
                        'caja'   => 'Caja',
                        'rollo'  => 'Rollo',
                        'par'    => 'Par',
                        'docena' => 'Doc.',
                        default  => $state,
                    })
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cost_price')
                    ->label('Costo')
                    ->money('ARS')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('margin_percentage')
                    ->label('Margen')
                    ->formatStateUsing(fn ($state): string => $state ? number_format($state, 1) . '%' : '—')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        $state < 15     => 'danger',
                        $state < 25     => 'warning',
                        default         => 'success',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sale_price')
                    ->label('Precio venta')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state, $record): string => match (true) {
                        $state <= 0                   => 'danger',
                        $state <= $record->min_stock => 'warning',
                        default                       => 'success',
                    }),
            ])
            ->filters([
                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name', fn (Builder $query) => $query->where('active', true)->orderBy('name'))
                    ->label('Proveedor')
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos los proveedores')
                    ->native(false),
                SelectFilter::make('category')
                    ->relationship('category', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->label('Categoría')
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas las categorías')
                    ->native(false),
                Filter::make('incomplete')
                    ->label('Sin completar')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('category_id')->where('cost_price', 0)),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->recordActions([
                Action::make('assignBarcode')
                    ->label('Asignar código')
                    ->icon('heroicon-o-viewfinder-circle')
                    ->color('gray')
                    ->modalHeading('Asignar código de barras')
                    ->modalDescription('Escaneá el código de barras o escribilo manualmente.')
                    ->modalSubmitActionLabel('Guardar')
                    ->modalWidth('sm')
                    ->schema([
                        TextInput::make('barcode')
                            ->label('Código de barras')
                            ->placeholder('Apuntá el escáner y escaneá...')
                            ->autofocus()
                            ->maxLength(100),
                    ])
                    ->fillForm(fn ($record): array => ['barcode' => $record->barcode])
                    ->action(fn ($record, array $data) => $record->update(['barcode' => $data['barcode'] ?: null]))
                    ->successNotificationTitle('Código asignado correctamente'),
                PrintLabelAction::make(),
                EditAction::make()
                    ->modal()
                    ->slideOver()
                    ->modalWidth('3xl'),
            ])
            ->toolbarActions([
                ExportProductsPdfAction::bulk('price_list'),
                ExportProductsPdfAction::bulk('inventory'),
                AdjustProductPricesAction::bulk(),
                PrintLabelAction::bulk(),
            ]);
    }
}
