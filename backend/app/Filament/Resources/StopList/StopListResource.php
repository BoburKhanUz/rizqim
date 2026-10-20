<?php

namespace App\Filament\Resources\StopList;

use App\Enums\Permission;
use App\Enums\StopListSubject;
use App\Filament\Resources\StopList\Pages\ManageStopList;
use App\Filament\Support\MenuPanelAccess;
use App\Filament\Support\StorePanelScope;
use App\Models\StopListEntry;
use App\Services\Catalog\StopListService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The branches' stop-lists (P1-21): what is switched off where, why and until when. stoplist.manage (owner, manager;
 * a branch manager only their branch). Changes reach the menus at once (menu.availability_changed).
 *
 * @extends Resource<StopListEntry>
 */
class StopListResource extends Resource
{
    protected static ?string $model = StopListEntry::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'stop-list';
    protected static ?string $modelLabel = 'Stop-list yozuvi';
    protected static ?string $pluralModelLabel = 'Stop-list';
    protected static ?string $navigationLabel = 'Stop-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 4;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    public static function canViewAny(): bool
    {
        return MenuPanelAccess::has(Permission::STOPLIST_MANAGE);
    }

    public static function canCreate(): bool
    {
        return false; // the page's "Stop-listga qo'shish" action
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canManage(StopListEntry $entry): bool
    {
        return (int) $entry->store_id === StorePanelScope::storeId()
            && MenuPanelAccess::allowsBranch(Permission::STOPLIST_MANAGE, (int) $entry->branch_id);
    }

    public static function getEloquentQuery(): Builder
    {
        $storeId = StorePanelScope::storeId();
        $query = StopListEntry::query()->with(['branch:id,name', 'creator:id,name'])->active();

        if ($storeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('store_id', $storeId)
            ->when(MenuPanelAccess::ownBranchId(), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->columns([
                TextColumn::make('subject_id')
                    ->label('Nima')
                    ->formatStateUsing(function ($state, StopListEntry $record) {
                        $subject = $record->subject();
                        $name = $subject?->getAttribute('name') ?? "#{$state}";
                        if ($subject instanceof \App\Models\ProductVariant) {
                            $name = trim(($subject->product->name ?? '') . " ({$subject->name})");
                        }

                        return $name;
                    })
                    ->description(fn (StopListEntry $record) => match ($record->subject_type) {
                        StopListSubject::PRODUCT => 'Mahsulot',
                        StopListSubject::VARIANT => 'Variant',
                        StopListSubject::MODIFIER => 'Modifikator',
                    })
                    ->weight('bold'),
                TextColumn::make('branch.name')->label('Filial'),
                TextColumn::make('reason')->label('Sabab')->placeholder('—'),
                TextColumn::make('until')->label('Qachongacha')->dateTime('d.m H:i')->placeholder("Qo'lda olinguncha"),
                TextColumn::make('creator.name')->label("Kim qo'shdi")->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label("Qo'shilgan")->since(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('branch_id')->label('Filial')->options(fn () => MenuPanelAccess::branchOptions()),
                SelectFilter::make('subject_type')->label('Turi')->options([
                    StopListSubject::PRODUCT->value => 'Mahsulot',
                    StopListSubject::VARIANT->value => 'Variant',
                    StopListSubject::MODIFIER->value => 'Modifikator',
                ]),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Olib tashlash')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (StopListEntry $record) => static::canManage($record))
                    ->action(function (StopListEntry $record) {
                        abort_unless(static::canManage($record), 403);
                        app(StopListService::class)->remove($record, MenuPanelAccess::user());
                        Notification::make()->title('Yana sotuvda')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStopList::route('/'),
        ];
    }
}
