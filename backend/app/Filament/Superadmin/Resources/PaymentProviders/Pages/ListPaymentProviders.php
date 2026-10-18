<?php

namespace App\Filament\Superadmin\Resources\PaymentProviders\Pages;

use App\Filament\Superadmin\Resources\PaymentProviders\PaymentProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentProviders extends ListRecords
{
    protected static string $resource = PaymentProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
