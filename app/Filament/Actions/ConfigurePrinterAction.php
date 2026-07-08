<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;

class ConfigurePrinterAction
{
    public static function make(): Action
    {
        return Action::make('configurePrinter')
            ->label('Configurar impresoras')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->modalHeading('Configurar impresoras')
            ->modalDescription('Se guarda solo en este navegador. Cada PC con el agente de impresión instalado debe elegir sus propias impresoras (etiquetas y tickets).')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalWidth('md')
            ->modalContent(fn () => view('filament.partials.configure-printer'));
    }
}
