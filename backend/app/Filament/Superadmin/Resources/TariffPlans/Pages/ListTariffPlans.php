<?php

namespace App\Filament\Superadmin\Resources\TariffPlans\Pages;

use App\Filament\Superadmin\Resources\TariffPlans\TariffPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTariffPlans extends ListRecords
{
    protected static string $resource = TariffPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
