<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\Actions\AdjustProductPricesAction;
use App\Filament\Resources\Products\Actions\ExportProductsPdfAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
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
                    ->imageSize(48),
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
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
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
                    ->color('gray'),
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
                        $state === null  => 'gray',
                        $state < 15      => 'danger',
                        $state < 25      => 'warning',
                        default          => 'success',
                    })
                    ->toggleable(),
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
                        $state <= 0                  => 'danger',
                        $state <= $record->min_stock => 'warning',
                        default                      => 'success',
                    }),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Filter::make('incomplete')
                    ->label('Sin completar (cargados desde escáner)')
                    ->query(fn (Builder $query): Builder => $query->whereNull('category_id')->where('cost_price', 0))
                    ->toggle(),
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Categoría'),
                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->label('Proveedor'),
                TrashedFilter::make(),
            ])
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
                EditAction::make()
                    ->modal()
                    ->slideOver()
                    ->modalWidth('3xl'),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                AdjustProductPricesAction::bulk(),
                ExportProductsPdfAction::bulk(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
