<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\CashRegister;
use App\Support\PaymentMethods;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        $isCompleted = fn ($record) => $record?->status === 'completed';

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('sale_number')
                    ->label('Nro. Venta')
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Auto-generado'),

                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'completed' => 'Completada',
                        'cancelled' => 'Cancelada',
                    ])
                    ->default('completed')
                    ->required(),

                Select::make('payment_method')
                    ->label('Medio de pago')
                    ->options(function ($record) {
                        $options = $record?->payment_method === PaymentMethods::MIXED
                            ? PaymentMethods::labels()
                            : PaymentMethods::options();

                        if ($record?->payment_method === PaymentMethods::CARD) {
                            $options[PaymentMethods::CARD] = PaymentMethods::label(PaymentMethods::CARD);
                        }

                        return $options;
                    })
                    ->required()
                    ->disabled($isCompleted),

                Text::make(fn ($record): string => 'Desglose: '.($record?->paymentSummary() ?? '—'))
                    ->visible(fn ($record): bool => $record?->payment_method === PaymentMethods::MIXED)
                    ->columnSpanFull(),

                Select::make('user_id')
                    ->label('Vendedor')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled($isCompleted),

                Select::make('cash_register_id')
                    ->label('Caja')
                    ->options(
                        fn () => CashRegister::where('status', 'open')
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn ($cr) => [
                                $cr->id => "Caja #{$cr->id} — {$cr->user?->name} ({$cr->opened_at->format('d/m H:i')})",
                            ])
                    )
                    ->searchable()
                    ->disabled($isCompleted),

                TextInput::make('subtotal')
                    ->label('Subtotal')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('$')
                    ->disabled($isCompleted),

                TextInput::make('discount')
                    ->label('Descuento')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('$')
                    ->disabled($isCompleted),

                TextInput::make('total')
                    ->label('Total')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('$')
                    ->disabled($isCompleted)
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $expected = (float) $get('subtotal') - (float) $get('discount');
                            if (abs((float) $value - $expected) > 0.01) {
                                $fail('El total debe ser igual a subtotal − descuento ($'.number_format($expected, 2).').');
                            }
                        },
                    ]),

                Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }
}
