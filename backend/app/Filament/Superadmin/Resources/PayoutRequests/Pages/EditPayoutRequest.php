<?php

namespace App\Filament\Superadmin\Resources\PayoutRequests\Pages;

use App\Filament\Superadmin\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditPayoutRequest extends EditRecord
{
    protected static string $resource = PayoutRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (\DomainException $e) {
            // Already decided elsewhere (double click / another admin) or wallet too low: nothing was debited.
            Notification::make()
                ->title('Xatolik')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $record->refresh();
            $this->refreshFormData(['status', 'resolution_notes']);

            throw (new Halt())->rollBackDatabaseTransaction();
        }
    }
}
