<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Support\StorePanelScope;
use App\Models\Branch;
use App\Models\User;
use App\Services\Auth\AuthTokenException;
use App\Services\Auth\DevicePairingService;
use App\Services\Auth\PermissionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageDevices extends ManageRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A one-time 8-character code (ABCD-EFGH); the terminal app asks for it on first start
            Action::make('pairingCode')
                ->label('Terminal ulash')
                ->icon('heroicon-m-qr-code')
                ->modalHeading('Yangi terminal ulash')
                ->modalDescription("Kod bir marta ishlatiladi va bir necha daqiqada eskiradi. Uni kassa yoki oshxona ekranidagi ilovaga kiriting.")
                ->visible(fn () => static::canPair())
                ->schema([
                    Select::make('type')
                        ->label('Turi')
                        ->options(collect(DeviceType::terminals())->mapWithKeys(fn (DeviceType $type) => [$type->value => $type->label()])->all())
                        ->default(DeviceType::POS->value)
                        ->required(),
                    Select::make('branch_id')
                        ->label('Filial')
                        ->options(fn () => Branch::where('store_id', StorePanelScope::storeId())
                            ->when(static::branchOf(), fn ($query, int $branchId) => $query->whereKey($branchId))
                            ->pluck('name', 'id')->all())
                        ->default(fn () => static::branchOf())
                        ->required(fn () => static::branchOf() !== null)
                        ->placeholder("Butun do'kon"),
                    TextInput::make('name')
                        ->label('Nomi')
                        ->placeholder('Masalan: Kassa 1')
                        ->maxLength(100),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $store = StorePanelScope::store();
                    abort_unless($user instanceof User && $store && static::canPair(), 403);

                    $branch = !empty($data['branch_id']) ? Branch::where('store_id', $store->id)->whereKey($data['branch_id'])->first() : null;
                    $scopedBranch = static::branchOf();
                    abort_if($scopedBranch !== null && $branch?->id !== $scopedBranch, 403);

                    try {
                        $result = app(DevicePairingService::class)->createCode($store, $branch, DeviceType::from($data['type']), $data['name'] ?? null, $user);
                    } catch (AuthTokenException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title("Ulash kodi: {$result['code']}")
                        ->body('Kod ' . $result['expires_at']->timezone(config('app.timezone'))->format('H:i') . ' gacha amal qiladi.')
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    protected static function canPair(): bool
    {
        $user = auth()->user();
        $store = StorePanelScope::store();

        return $user instanceof User && $store !== null && app(PermissionService::class)->allows($user, Permission::DEVICES_MANAGE, $store);
    }

    /**
     * The branch a branch-tied manager pairs terminals for; null for owners and store-wide staff.
     */
    protected static function branchOf(): ?int
    {
        $user = auth()->user();

        return $user instanceof User && app(PermissionService::class)->isBranchScoped($user) ? (int) $user->branch_id : null;
    }
}
