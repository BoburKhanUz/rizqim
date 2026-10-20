<?php

namespace App\Filament\Resources\Devices;

use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Filament\Resources\Devices\Pages\ManageDevices;
use App\Filament\Support\StorePanelScope;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\DeviceService;
use App\Services\Auth\PermissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The store's devices: paired POS/KDS terminals and the staff's app installs. Owners and managers (devices.view /
 * devices.manage; a branch manager only their branch's) make pairing codes, rename and revoke.
 *
 * @extends Resource<Device>
 */
class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    // Scoped by getEloquentQuery() through StorePanelScope, like the employee list
    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Qurilma';
    protected static ?string $pluralModelLabel = 'Qurilmalar';
    protected static ?string $navigationLabel = 'Qurilmalar';
    protected static string|\UnitEnum|null $navigationGroup = 'Xodimlar va ruxsatlar';
    protected static ?int $navigationSort = 5;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-tablet';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(PermissionService::class)->has($user, Permission::DEVICES_VIEW);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canManage(Device $device): bool
    {
        $user = auth()->user();

        return $user instanceof User && !$device->isRevoked()
            && app(PermissionService::class)->allows($user, Permission::DEVICES_MANAGE, $device);
    }

    public static function getEloquentQuery(): Builder
    {
        $storeId = StorePanelScope::storeId();
        $query = Device::query()->with(['user:id,name,role', 'branch:id,name']);

        if ($storeId === null) {
            return $query->whereRaw('1 = 0');
        }

        $user = auth()->user();
        $branchScoped = $user instanceof User && app(PermissionService::class)->isBranchScoped($user);

        return $query->where('store_id', $storeId)
            ->when($branchScoped, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('type')
                    ->label('Turi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof DeviceType ? $state->label() : (string) $state),
                TextColumn::make('branch.name')
                    ->label('Filial')
                    ->placeholder("Butun do'kon"),
                TextColumn::make('user.name')
                    ->label('Xodim')
                    ->placeholder('Terminal'),
                TextColumn::make('platform')
                    ->label('Platforma')
                    ->formatStateUsing(fn ($state, Device $record) => trim($state . ' ' . ($record->app_version ? "v{$record->app_version}" : '')))
                    ->toggleable(),
                TextColumn::make('last_seen_at')
                    ->label("So'nggi faollik")
                    ->since()
                    ->sortable(),
                TextColumn::make('revoked_at')
                    ->label('Holati')
                    ->badge()
                    ->getStateUsing(fn (Device $record) => $record->isRevoked() ? "O'chirilgan" : 'Faol')
                    ->color(fn (string $state) => $state === 'Faol' ? 'success' : 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Turi')
                    ->options(collect(DeviceType::cases())->mapWithKeys(fn (DeviceType $type) => [$type->value => $type->label()])->all()),
                TernaryFilter::make('active')
                    ->label('Faol')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('revoked_at'),
                        false: fn (Builder $query) => $query->whereNotNull('revoked_at'),
                    ),
            ])
            ->recordActions([
                Action::make('rename')
                    ->label("Nomini o'zgartirish")
                    ->icon('heroicon-m-pencil-square')
                    ->visible(fn (Device $record) => static::canManage($record))
                    ->fillForm(fn (Device $record) => ['name' => $record->name])
                    ->schema([
                        TextInput::make('name')->label('Nomi')->required()->maxLength(100),
                    ])
                    ->action(function (Device $record, array $data) {
                        abort_unless(static::canManage($record), 403);
                        app(DeviceService::class)->rename($record, (string) $data['name'], auth()->user());
                        Notification::make()->title('Saqlandi')->success()->send();
                    }),
                Action::make('revoke')
                    ->label("O'chirish")
                    ->icon('heroicon-m-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription("Qurilma tizimdan uziladi: uning tokeni va undagi barcha xodim sessiyalari darhol yopiladi.")
                    ->visible(fn (Device $record) => static::canManage($record))
                    ->action(function (Device $record) {
                        abort_unless(static::canManage($record), 403);
                        app(DeviceService::class)->revoke($record, auth()->user());
                        Notification::make()->title("Qurilma o'chirildi")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDevices::route('/'),
        ];
    }
}
