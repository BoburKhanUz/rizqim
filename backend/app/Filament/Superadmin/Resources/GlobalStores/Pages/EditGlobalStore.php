<?php

namespace App\Filament\Superadmin\Resources\GlobalStores\Pages;

use App\Filament\Superadmin\Resources\GlobalStores\GlobalStoreResource;
use App\Models\Store;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGlobalStore extends EditRecord
{
    protected static string $resource = GlobalStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * As on the store's own settings pages: payment settings as checkout reads them and without secrets (a save keeps
     * the stored ones), and the allowed-methods list ticked from them. That list is an accessor, not a column, so the
     * form used to open with every method unticked and saving any other field switched them all off.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $settings = Store::paymentSettingsForForm(Store::withoutSecrets($data['payment_settings'] ?? []));

        $data['payment_settings'] = $settings;
        $data['active_payment_methods'] = (new Store())->forceFill(['payment_settings' => $settings])->active_payment_methods;

        return $data;
    }
}
