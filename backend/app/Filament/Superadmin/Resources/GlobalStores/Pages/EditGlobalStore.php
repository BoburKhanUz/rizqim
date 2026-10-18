<?php

namespace App\Filament\Superadmin\Resources\GlobalStores\Pages;

use App\Filament\Superadmin\Resources\GlobalStores\GlobalStoreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGlobalStore extends EditRecord
{
    protected static string $resource = GlobalStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
