<?php

namespace App\Filament\Superadmin\Resources\PaymentProviders\Pages;

use App\Filament\Superadmin\Resources\PaymentProviders\PaymentProviderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentProvider extends EditRecord
{
    protected static string $resource = PaymentProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
