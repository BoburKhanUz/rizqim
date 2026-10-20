<?php

namespace App\Filament\Resources\ModifierGroups;

use App\Enums\Permission;
use App\Filament\Resources\ModifierGroups\Pages\CreateModifierGroup;
use App\Filament\Resources\ModifierGroups\Pages\EditModifierGroup;
use App\Filament\Resources\ModifierGroups\Pages\ListModifierGroups;
use App\Filament\Support\StorePanelScope;
use App\Models\ModifierGroup;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Auth\PermissionService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The store's modifier groups and their modifiers (P1-21, docs/MENU.md). Anyone with menu.view reads them; menu.manage
 * (owners by default) edits. Products pick their groups on the product form.
 *
 * @extends Resource<ModifierGroup>
 */
class ModifierGroupResource extends Resource
{
    protected static ?string $model = ModifierGroup::class;

    // Scoped by getEloquentQuery() through StorePanelScope (no tenant relationship on Store needed)
    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Modifikator guruhi';
    protected static ?string $pluralModelLabel = 'Modifikatorlar';
    protected static ?string $navigationLabel = 'Modifikatorlar';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 2;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    // Filament v5 authorizes pages and actions (CreateAction, EditAction, DeleteAction) through these responses
    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::response(static::allowed(Permission::MENU_VIEW) || static::allowed(Permission::MENU_MANAGE));
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::response(static::allowed(Permission::MENU_MANAGE));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::response(static::allowed(Permission::MENU_MANAGE) && (int) $record->getAttribute('store_id') === StorePanelScope::storeId());
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::getEditAuthorizationResponse($record);
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::getCreateAuthorizationResponse();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return static::response(static::canViewAny() && (int) $record->getAttribute('store_id') === StorePanelScope::storeId());
    }

    protected static function response(bool $allowed): Response
    {
        return $allowed ? Response::allow() : Response::deny();
    }

    protected static function allowed(Permission $permission): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(PermissionService::class)->has($user, $permission);
    }

    public static function getEloquentQuery(): Builder
    {
        $storeId = StorePanelScope::storeId();

        return $storeId === null
            ? ModifierGroup::query()->whereRaw('1 = 0')
            : ModifierGroup::query()->where('store_id', $storeId)->withCount('modifiers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Guruh')
                ->description("Masalan: \"Sous tanlang\" (majburiy, 1 ta) yoki \"Qo'shimchalar\" (ixtiyoriy, 3 tagacha).")
                ->schema([
                    TextInput::make('name')->label('Nomi')->required()->maxLength(255),
                    TextInput::make('name_i18n.ru')->label('Nomi (ruscha)')->maxLength(255),
                    TextInput::make('min_select')->label('Eng kam tanlov')->numeric()->minValue(0)->maxValue(50)->default(0)->required(),
                    TextInput::make('max_select')->label("Eng ko'p tanlov")->numeric()->minValue(1)->maxValue(50)
                        ->helperText("Bo'sh: cheklanmagan"),
                    Toggle::make('is_required')->label('Majburiy')->default(false),
                    Toggle::make('is_active')->label('Faol')->default(true),
                    TextInput::make('sort_order')->label('Tartib')->numeric()->default(0),
                ])->columns(2),
            Section::make('Modifikatorlar')
                ->schema([
                    Repeater::make('modifiers')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('sort_order')
                        ->addActionLabel("Modifikator qo'shish")
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->collapsible()
                        ->schema([
                            TextInput::make('name')->label('Nomi')->required()->maxLength(255)->columnSpan(2),
                            TextInput::make('price_delta')->label("Narx qo'shimchasi")->numeric()->minValue(0)->default(0)->prefix('UZS')->required()->columnSpan(2),
                            TextInput::make('max_quantity')->label("Ko'pi bilan (marta)")->numeric()->minValue(1)->maxValue(50)->default(1)->required(),
                            Toggle::make('is_active')->label('Faol')->default(true),
                            Select::make('product_variant_id')
                                ->label("Bog'langan variant (ixtiyoriy)")
                                ->options(fn () => ProductVariant::query()
                                    ->whereHas('product', fn ($query) => $query->where('store_id', StorePanelScope::storeId()))
                                    ->with('product:id,name')
                                    ->get()
                                    ->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => trim(($variant->product->name ?? '') . ' ' . ($variant->name ? "({$variant->name})" : ''))])
                                    ->all())
                                ->searchable()
                                ->columnSpan(2),
                        ])
                        ->columns(6),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nomi')->searchable()->weight('bold'),
                TextColumn::make('min_select')->label('Kamida'),
                TextColumn::make('max_select')->label("Ko'pi bilan")->placeholder('∞'),
                IconColumn::make('is_required')->label('Majburiy')->boolean(),
                TextColumn::make('modifiers_count')->label('Modifikatorlar'),
                IconColumn::make('is_active')->label('Faol')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModifierGroups::route('/'),
            'create' => CreateModifierGroup::route('/create'),
            'edit' => EditModifierGroup::route('/{record}/edit'),
        ];
    }
}
