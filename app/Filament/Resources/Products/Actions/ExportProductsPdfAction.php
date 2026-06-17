<?php

namespace App\Filament\Resources\Products\Actions;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Illuminate\Database\Eloquent\Collection;

class ExportProductsPdfAction
{
    public static function bulk(): BulkAction
    {
        return BulkAction::make('exportPdf')
            ->label('Exportar PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('info')
            ->modalHeading('Exportar productos a PDF')
            ->modalSubmitActionLabel('Descargar PDF')
            ->modalWidth('sm')
            ->schema([
                Radio::make('type')
                    ->label('Tipo de reporte')
                    ->options([
                        'price_list' => 'Lista de precios (para clientes)',
                        'inventory'  => 'Inventario interno',
                    ])
                    ->default('price_list')
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): mixed {
                $records->load(['category', 'supplier']);

                $type = $data['type'];

                $view = $type === 'inventory'
                    ? 'pdf.products-inventory'
                    : 'pdf.products-price-list';

                $orientation = $type === 'inventory' ? 'landscape' : 'portrait';

                $pdf = Pdf::loadView($view, ['products' => $records])
                    ->setPaper('a4', $orientation);

                $filename = $type === 'inventory'
                    ? 'inventario-productos-' . now()->format('Y-m-d') . '.pdf'
                    : 'lista-precios-' . now()->format('Y-m-d') . '.pdf';

                return response()->streamDownload(
                    fn () => print($pdf->output()),
                    $filename,
                    ['Content-Type' => 'application/pdf'],
                );
            })
            ->deselectRecordsAfterCompletion();
    }
}
