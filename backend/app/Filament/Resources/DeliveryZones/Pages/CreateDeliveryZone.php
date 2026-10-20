<?php

namespace App\Filament\Resources\DeliveryZones\Pages;

use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Filament\Support\StorePanelScope;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryZone extends CreateRecord
{
    protected static string $resource = DeliveryZoneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $storeId = StorePanelScope::storeId();
        abort_if($storeId === null, 403);

        // Never from the form: a zone always belongs to the panel's store
        $data['store_id'] = $storeId;

        return $data;
    }
}
