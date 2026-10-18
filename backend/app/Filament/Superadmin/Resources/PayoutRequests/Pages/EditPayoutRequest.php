<?php

namespace App\Filament\Superadmin\Resources\PayoutRequests\Pages;

use App\Filament\Superadmin\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditPayoutRequest extends EditRecord
{
    protected static string $resource = PayoutRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
