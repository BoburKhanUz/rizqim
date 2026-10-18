<?php

namespace App\Filament\Superadmin\Resources\GlobalOrders\Pages;

use App\Filament\Superadmin\Resources\GlobalOrders\GlobalOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = GlobalOrderResource::class;
}
