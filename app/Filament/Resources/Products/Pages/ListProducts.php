<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Pages\PaymentSurchargeSettings;
use App\Filament\Resources\Products\Actions\AdjustProductPricesAction;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getSubheading(): ?string
    {
        return 'Filtrá por proveedor o categoría, seleccioná los productos y exportá el PDF. En el pedido a proveedor podés indicar cuánto querés recibir de cada uno.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('paymentPrices')
                ->label('Precios por medio de pago')
                ->icon('heroicon-o-banknotes')
                ->url(PaymentSurchargeSettings::getUrl())
                ->color('gray'),
            AdjustProductPricesAction::make(),
            CreateAction::make(),
        ];
    }
}
