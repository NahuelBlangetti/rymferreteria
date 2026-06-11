<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestSales extends TableWidget
{
    protected static ?string $heading = 'Últimas ventas';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Sale::query()
                    ->with(['user'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Nro.')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Vendedor'),
                TextColumn::make('payment_method')
                    ->label('Medio de pago')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash'     => 'Efectivo',
                        'transfer' => 'Transferencia',
                        'card'     => 'Tarjeta',
                        default    => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash'     => 'success',
                        'transfer' => 'info',
                        'card'     => 'warning',
                        default    => 'gray',
                    }),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
