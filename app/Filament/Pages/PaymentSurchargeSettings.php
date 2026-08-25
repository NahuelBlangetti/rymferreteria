<?php

namespace App\Filament\Pages;

use App\Models\PaymentSurchargeSetting;
use App\Support\PriceRounding;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class PaymentSurchargeSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Precios por medio de pago';

    protected static ?string $title = 'Precios por medio de pago';

    protected static ?string $slug = 'precios-medios-pago';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 5;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = PaymentSurchargeSetting::current();
        $transfer = (float) $settings->transfer_percentage;
        $debit = (float) $settings->debit_percentage;
        $credit = (float) $settings->credit_percentage;
        $sameNonCash = $transfer === $debit && $debit === $credit;

        $this->form->fill([
            ...$settings->only([
                'cash_percentage',
                'transfer_percentage',
                'debit_percentage',
                'credit_percentage',
                'rounding_step',
                'rounding_mode',
            ]),
            'percentage_mode' => $sameNonCash ? 'non_cash' : 'per_method',
            'non_cash_percentage' => $sameNonCash ? $transfer : max($transfer, $debit, $credit),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Porcentajes por medio')
                    ->description('El precio de venta de cada producto es el de efectivo. Los otros medios muestran el valor final ya calculado: en la venta y en el listado no aparece ningún recargo.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cash_percentage')
                            ->label('Efectivo')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(0),
                        Radio::make('percentage_mode')
                            ->label('Transferencia, débito y crédito')
                            ->options([
                                'non_cash' => 'Un solo porcentaje para los tres',
                                'per_method' => 'Un porcentaje distinto por cada medio',
                            ])
                            ->default('non_cash')
                            ->live()
                            ->columnSpanFull()
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                if ($state !== 'non_cash') {
                                    return;
                                }

                                $set('non_cash_percentage', max(
                                    (float) ($get('transfer_percentage') ?? 0),
                                    (float) ($get('debit_percentage') ?? 0),
                                    (float) ($get('credit_percentage') ?? 0),
                                ));
                            }),
                        TextInput::make('non_cash_percentage')
                            ->label('Porcentaje para transferencia, débito y crédito')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(0)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('percentage_mode') !== 'per_method')
                            ->dehydrated(fn (Get $get): bool => $get('percentage_mode') !== 'per_method'),
                        TextInput::make('transfer_percentage')
                            ->label('Transferencia')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(0)
                            ->visible(fn (Get $get): bool => $get('percentage_mode') === 'per_method')
                            ->dehydrated(fn (Get $get): bool => $get('percentage_mode') === 'per_method'),
                        TextInput::make('debit_percentage')
                            ->label('Débito')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(0)
                            ->visible(fn (Get $get): bool => $get('percentage_mode') === 'per_method')
                            ->dehydrated(fn (Get $get): bool => $get('percentage_mode') === 'per_method'),
                        TextInput::make('credit_percentage')
                            ->label('Crédito')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(0)
                            ->visible(fn (Get $get): bool => $get('percentage_mode') === 'per_method')
                            ->dehydrated(fn (Get $get): bool => $get('percentage_mode') === 'per_method'),
                    ]),
                Section::make('Redondeo del valor final')
                    ->description('Se aplica después de calcular el valor de cada medio, para cotizar números redondos.')
                    ->columns(2)
                    ->schema([
                        Select::make('rounding_step')
                            ->label('Redondear a')
                            ->options(PriceRounding::STEPS)
                            ->required()
                            ->default(0)
                            ->live(),
                        Select::make('rounding_mode')
                            ->label('Tipo de redondeo')
                            ->options(PriceRounding::MODES)
                            ->required()
                            ->default(PriceRounding::MODE_UP)
                            ->visible(fn (Get $get): bool => (int) ($get('rounding_step') ?? 0) > 0)
                            ->dehydrated(),
                    ]),
            ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
        } catch (Halt) {
            return;
        }

        if (($data['percentage_mode'] ?? 'non_cash') === 'non_cash') {
            $percentage = (float) ($data['non_cash_percentage'] ?? 0);
            $data['transfer_percentage'] = $percentage;
            $data['debit_percentage'] = $percentage;
            $data['credit_percentage'] = $percentage;
        }

        unset($data['percentage_mode'], $data['non_cash_percentage']);

        PaymentSurchargeSetting::current()->update($data);
        PaymentSurchargeSetting::forgetCache();

        Notification::make()
            ->title('Precios por medio de pago actualizados')
            ->body('Los valores finales se van a ver en el listado de productos y al elegir el medio en una venta.')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
