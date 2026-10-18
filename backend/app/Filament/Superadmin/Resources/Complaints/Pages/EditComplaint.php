<?php

namespace App\Filament\Superadmin\Resources\Complaints\Pages;

use App\Filament\Superadmin\Resources\Complaints\ComplaintResource;
use Filament\Resources\Pages\EditRecord;

class EditComplaint extends EditRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
