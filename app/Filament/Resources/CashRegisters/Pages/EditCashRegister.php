<?php

namespace App\Filament\Resources\CashRegisters\Pages;

use App\Filament\Resources\CashRegisters\CashRegisterResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditCashRegister extends EditRecord
{
    protected static string $resource = CashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Eliminar')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('¿Eliminar esta caja?')
                ->modalDescription('Esta acción no se puede deshacer.')
                ->action(function () {
                    if ($this->record->sales()->exists()) {
                        Notification::make()
                            ->title('No se puede eliminar esta caja')
                            ->body('La caja tiene ventas asociadas. Reasignales una caja antes de eliminarla.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->delete();

                    Notification::make()
                        ->title('Caja eliminada')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }

    protected function beforeSave(): void
    {
        $data = $this->form->getState();

        if ($this->record->status === 'closed' && $data['status'] === 'open') {
            Notification::make()
                ->title('No se puede reabrir una caja cerrada')
                ->body('Una vez cerrada, la caja es un registro histórico inmutable. Abrí una nueva caja para el próximo turno.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
