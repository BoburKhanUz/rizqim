<?php

namespace App\Filament\Superadmin\Resources\GlobalDevices;

use App\Enums\DeviceType;
use App\Filament\Superadmin\Resources\GlobalDevices\Pages\ManageGlobalDevices;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\DeviceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every device on the platform (terminals and app installs) for the super admin, who can revoke any of them.
 *
 * @extends Resource<Device>
 */
class GlobalDeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $modelLabel = 'Qurilma';
    protected static ?string $pluralModelLabel = 'Qurilmalar';
    protected static ?string $navigationLabel = 'Qurilmalar';
    protected static string|\UnitEnum|null $navigationGroup = 'Xodimlar va ruxsatlar';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-tablet';

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['store:id,name', 'branch:id,name', 'user:id,name,role']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nomi')->searchable()->weight('bold'),
                TextColumn::make('type')
                    ->label('Turi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof DeviceType ? $state->label() : (string) $state),
                TextColumn::make('store.name')->label("Do'kon")->placeholder('-')->searchable(),
                TextColumn::make('branch.name')->label('Filial')->placeholder('-'),
                TextColumn::make('user.name')->label('Foydalanuvchi')->placeholder('Terminal'),
                TextColumn::make('platform')->label('Platforma')->toggleable(),
                TextColumn::make('last_seen_at')->label("So'nggi faollik")->since()->sortable(),
                TextColumn::make('revoked_at')->label("O'chirilgan")->dateTime('d.m.Y H:i')->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Turi')
                    ->options(collect(DeviceType::cases())->mapWithKeys(fn (DeviceType $type) => [$type->value => $type->label()])->all()),
                SelectFilter::make('store_id')->label("Do'kon")->relationship('store', 'name')->searchable(),
                TernaryFilter::make('active')
                    ->label('Faol')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('revoked_at'),
                        false: fn (Builder $query) => $query->whereNotNull('revoked_at'),
                    ),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label("O'chirish")
                    ->icon('heroicon-m-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Device $record) => !$record->isRevoked())
                    ->action(function (Device $record) {
                        $actor = auth()->user();
                        app(DeviceService::class)->revoke($record, $actor instanceof User ? $actor : null);
                        Notification::make()->title("Qurilma o'chirildi")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGlobalDevices::route('/'),
        ];
    }
}
