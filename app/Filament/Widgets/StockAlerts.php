<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StockAlerts extends TableWidget
{
    protected static ?string $heading = 'Alertas de stock';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '320px';

    public static function canView(): bool
    {
        return Product::where('active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->with('category')
                    ->where('active', true)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->where('min_stock', '>', 0)
                    ->orderBy('stock')
                    ->limit(10)
            )
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->imageSize(36)
                    ->defaultImageUrl(asset('images/logo.png'))
                    ->extraImgAttributes(['class' => 'rounded object-cover']),

                TextColumn::make('name')
                    ->label('Producto')
                    ->limit(25)
                    ->weight('medium'),

                TextColumn::make('stock')
                    ->label('Stock')
                    ->badge()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : 'warning'),

                TextColumn::make('min_stock')
                    ->label('Mínimo')
                    ->color('gray'),
            ])
            ->paginated(false);
    }
}
