<?php

namespace App\Filament\Superadmin\Resources\GlobalUsers\Pages;

use App\Filament\Superadmin\Resources\GlobalUsers\GlobalUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGlobalUsers extends ManageRecords
{
    protected static string $resource = GlobalUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
