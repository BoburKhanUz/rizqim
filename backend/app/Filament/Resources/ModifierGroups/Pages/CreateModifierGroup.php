<?php

namespace App\Filament\Resources\ModifierGroups\Pages;

use App\Filament\Resources\ModifierGroups\ModifierGroupResource;
use App\Filament\Support\StorePanelScope;
use Filament\Resources\Pages\CreateRecord;

class CreateModifierGroup extends CreateRecord
{
    protected static string $resource = ModifierGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $storeId = StorePanelScope::storeId();
        abort_if($storeId === null, 403);

        return ['store_id' => $storeId] + $data;
    }
}
