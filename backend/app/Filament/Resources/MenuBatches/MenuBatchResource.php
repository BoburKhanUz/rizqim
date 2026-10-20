<?php

namespace App\Filament\Resources\MenuBatches;

use App\Enums\Permission;
use App\Filament\Resources\MenuBatches\Pages\ManageMenuBatches;
use App\Filament\Support\MenuPanelAccess;
use App\Filament\Support\StorePanelScope;
use App\Models\MenuBatch;
use App\Services\Catalog\BatchService;
use App\Support\Quantity;
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
use Illuminate\Validation\ValidationException;

/**
 * Batches / kazans of the store's branches (P1-21): open, top up, correct and close them; the "qoldi" column refreshes
 * every few seconds. batches.manage (owner, manager; a branch manager only their branch).
 *
 * @extends Resource<MenuBatch>
 */
class MenuBatchResource extends Resource
{
    protected static ?string $model = MenuBatch::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Partiya';
    protected static ?string $pluralModelLabel = 'Qozon va partiyalar';
    protected static ?string $navigationLabel = 'Qozon qoldig\'i';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 3;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    public static function canViewAny(): bool
    {
        return MenuPanelAccess::has(Permission::BATCHES_MANAGE);
    }

    public static function canCreate(): bool
    {
        return false; // the page's "Partiya ochish" action
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canManage(MenuBatch $batch): bool
    {
        return (int) $batch->store_id === StorePanelScope::storeId()
            && MenuPanelAccess::allowsBranch(Permission::BATCHES_MANAGE, (int) $batch->branch_id);
    }

    public static function getEloquentQuery(): Builder
    {
        $storeId = StorePanelScope::storeId();
        $query = MenuBatch::query()->with(['branch:id,name', 'product:id,name', 'variant:id,name,unit']);

        if ($storeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('store_id', $storeId)
            ->when(MenuPanelAccess::ownBranchId(), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Taom')
                    ->description(fn (MenuBatch $record) => trim(($record->product_variant_id !== null ? ($record->variant->name ?? '') : 'barcha variantlar') . ($record->label ? " · {$record->label}" : '')))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('branch.name')->label('Filial'),
                TextColumn::make('quantity_left')
                    ->label('Qoldi')
                    ->formatStateUsing(fn ($state, MenuBatch $record) => Quantity::of($state)->format() . ' / ' . Quantity::of($record->quantity_produced)->format())
                    ->badge()
                    ->color(fn (MenuBatch $record) => $record->left()->isZero() ? 'danger' : 'success'),
                TextColumn::make('started_at')->label('Ochilgan')->since()->sortable(),
                TextColumn::make('closed_at')
                    ->label('Holati')
                    ->badge()
                    ->getStateUsing(fn (MenuBatch $record) => $record->closed_at ? 'Yopilgan' : ($record->left()->isZero() ? 'Tugadi' : 'Ochiq'))
                    ->color(fn (string $state) => match ($state) {
                        'Ochiq' => 'success',
                        'Tugadi' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                TernaryFilter::make('open')
                    ->label('Ochiq')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('closed_at'),
                        false: fn (Builder $query) => $query->whereNotNull('closed_at'),
                    ),
                SelectFilter::make('branch_id')
                    ->label('Filial')
                    ->options(fn () => MenuPanelAccess::branchOptions()),
            ])
            ->recordActions([
                Action::make('adjust')
                    ->label("To'ldirish / tuzatish")
                    ->icon('heroicon-m-plus-circle')
                    ->visible(fn (MenuBatch $record) => $record->closed_at === null && static::canManage($record))
                    ->schema([
                        TextInput::make('add')->label("Qo'shish (yana pishirildi)")->numeric()->minValue(0.001),
                        TextInput::make('quantity_left')->label('Yoki qoldiqni belgilash')->numeric()->minValue(0),
                        TextInput::make('reason')->label('Sabab')->maxLength(255),
                    ])
                    ->action(function (MenuBatch $record, array $data) {
                        abort_unless(static::canManage($record), 403);
                        $add = filled($data['add'] ?? null) ? Quantity::tryOf((string) $data['add']) : null;
                        $left = filled($data['quantity_left'] ?? null) ? Quantity::tryOf((string) $data['quantity_left']) : null;
                        if ($add === null && $left === null) {
                            Notification::make()->title("Qo'shiladigan miqdor yoki yangi qoldiqni kiriting")->danger()->send();

                            return;
                        }

                        try {
                            app(BatchService::class)->adjust($record, $add, $left, $data['reason'] ?? null, MenuPanelAccess::user());
                        } catch (ValidationException $e) {
                            Notification::make()->title($e->validator->errors()->first())->danger()->send();

                            return;
                        }
                        Notification::make()->title('Saqlandi')->success()->send();
                    }),
                Action::make('close')
                    ->label('Yopish')
                    ->icon('heroicon-m-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription("Qozon bo'yicha sotiladigan taom yangi partiya ochilguncha \"tugadi\" bo'ladi.")
                    ->visible(fn (MenuBatch $record) => $record->closed_at === null && static::canManage($record))
                    ->action(function (MenuBatch $record) {
                        abort_unless(static::canManage($record), 403);
                        app(BatchService::class)->close($record, MenuPanelAccess::user());
                        Notification::make()->title('Partiya yopildi')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMenuBatches::route('/'),
        ];
    }
}
