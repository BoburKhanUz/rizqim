<?php

namespace App\Filament\Superadmin\Resources\GlobalOrders\Pages;

use App\Filament\Superadmin\Resources\GlobalOrders\GlobalOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGlobalOrder extends ViewRecord
{
    protected static string $resource = GlobalOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
