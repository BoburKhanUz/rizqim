<?php

namespace App\Filament\Superadmin\Resources\PromoResource\Pages;

use App\Filament\Superadmin\Resources\PromoResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPromos extends ListRecords
{
    protected static string $resource = PromoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
