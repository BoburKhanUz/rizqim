<?php

namespace App\Filament\Superadmin\Resources\GlobalStores\Pages;

use App\Filament\Superadmin\Resources\GlobalStores\GlobalStoreResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGlobalStores extends ListRecords
{
    protected static string $resource = GlobalStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
