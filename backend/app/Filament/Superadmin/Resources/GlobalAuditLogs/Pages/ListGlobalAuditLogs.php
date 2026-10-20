<?php

namespace App\Filament\Superadmin\Resources\GlobalAuditLogs\Pages;

use App\Filament\Superadmin\Resources\GlobalAuditLogs\GlobalAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListGlobalAuditLogs extends ListRecords
{
    protected static string $resource = GlobalAuditLogResource::class;
}
