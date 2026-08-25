<?php

namespace App\Filament\Pages;

use App\Models\PaymentSurchargeSetting;
use App\Support\PriceRounding;
use BackedEnum;
use Filament\Actions\Action;
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
        $this->form->fill(PaymentSurchargeSetting::current()->only([
            'cash_percentage',
            'transfer_percentage',
            'debit_percentage',
            'credit_percentage',
            'rounding_step',
            'rounding_mode',
        ]));
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
                        TextInput::make('transfer_percentage')
                            ->label('Transferencia')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(0),
                        TextInput::make('debit_percentage')
                            ->label('Débito')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(0),
                        TextInput::make('credit_percentage')
                            ->label('Crédito')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(0),
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
