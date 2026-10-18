<?php

namespace App\Filament\Superadmin\Resources\PromoCodeResource\Pages;

use App\Filament\Superadmin\Resources\PromoCodeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPromoCodes extends ListRecords
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalWidth('7xl'),
        ];
    }
}
