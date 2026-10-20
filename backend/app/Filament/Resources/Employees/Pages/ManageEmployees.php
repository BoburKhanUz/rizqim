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
            // The owner's / manager's own terminal PIN (they are not in the list themselves)
            \Filament\Actions\Action::make('myPin')
                ->label('Mening PIN kodim')
                ->icon('heroicon-m-key')
                ->color('gray')
                ->modalHeading('Terminal uchun shaxsiy PIN')
                ->modalDescription("4-6 xonali raqam. 0000, 1234 kabi oddiy kodlar qabul qilinmaydi.")
                ->visible(fn () => auth()->user() instanceof \App\Models\User
                    && app(\App\Services\Auth\StaffPinService::class)->canManagePin(auth()->user(), auth()->user()))
                ->schema([
                    \Filament\Forms\Components\TextInput::make('pin')
                        ->label('PIN')
                        ->password()
                        ->required()
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if ($reason = \App\Services\Auth\StaffPinService::weakness((string) $value)) {
                                $fail($reason);
                            }
                        }),
                    \Filament\Forms\Components\TextInput::make('pin_confirmation')
                        ->label('PIN (takroran)')
                        ->password()
                        ->required()
                        ->same('pin'),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    abort_unless($user instanceof \App\Models\User, 403);
                    app(\App\Services\Auth\StaffPinService::class)->setPin($user, (string) $data['pin'], $user);

                    \Filament\Notifications\Notification::make()
                        ->title("PIN o'rnatildi")
                        ->body("Xodim kodingiz: {$user->staff_code}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
