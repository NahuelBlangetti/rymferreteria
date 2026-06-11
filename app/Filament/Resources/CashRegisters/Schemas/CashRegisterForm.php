<?php

namespace App\Filament\Resources\CashRegisters\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CashRegisterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'open'   => 'Abierta',
                        'closed' => 'Cerrada',
                    ])
                    ->default('open')
                    ->required(),

                TextInput::make('opening_amount')
                    ->label('Monto apertura')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('$'),

                TextInput::make('closing_amount')
                    ->label('Monto cierre')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$'),

                TextInput::make('expected_amount')
                    ->label('Monto esperado')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->disabled(),

                TextInput::make('difference')
                    ->label('Diferencia')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),

                DateTimePicker::make('opened_at')
                    ->label('Apertura')
                    ->required()
                    ->default(now()),

                DateTimePicker::make('closed_at')
                    ->label('Cierre')
                    ->after('opened_at')
                    ->helperText('Debe ser posterior a la apertura.'),

                Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }
}
