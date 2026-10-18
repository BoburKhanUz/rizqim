<?php

namespace App\Filament\Superadmin\Resources\Districts\Pages;

use App\Filament\Superadmin\Resources\Districts\DistrictResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDistricts extends ListRecords
{
    protected static string $resource = DistrictResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
