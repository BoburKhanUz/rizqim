<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $storeId = \Filament\Facades\Filament::getTenant()?->getKey() ?? auth()->user()?->store_id;
                    abort_if($storeId === null, 403);
                    $data['store_id'] = $storeId;
                    $data['password'] = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12));
                    $data['telegram_invite_token'] = \Illuminate\Support\Str::uuid()->toString();
                    return $data;
                })
                ->after(function (\App\Models\User $record) {
                    $owner = auth()->user();
                    if ($owner && $owner->telegram_id) {
                        app(\App\Services\Notifications\TelegramNotificationService::class)->sendEmployeeInvite($record, $owner);
                    }
                }),
        ];
    }
}
