<?php

namespace App\Filament\Superadmin\Resources\TariffPlans\Pages;

use App\Filament\Superadmin\Resources\TariffPlans\TariffPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTariffPlan extends EditRecord
{
    protected static string $resource = TariffPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
