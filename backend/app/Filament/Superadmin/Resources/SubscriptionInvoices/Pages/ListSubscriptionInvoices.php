<?php

namespace App\Filament\Superadmin\Resources\SubscriptionInvoices\Pages;

use App\Filament\Superadmin\Resources\SubscriptionInvoices\SubscriptionInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionInvoices extends ListRecords
{
    protected static string $resource = SubscriptionInvoiceResource::class;
}
