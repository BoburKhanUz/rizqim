<?php

namespace App\Filament\Superadmin\Resources\PaymentProviders\Pages;

use App\Filament\Superadmin\Resources\PaymentProviders\PaymentProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentProvider extends CreateRecord
{
    protected static string $resource = PaymentProviderResource::class;
}
