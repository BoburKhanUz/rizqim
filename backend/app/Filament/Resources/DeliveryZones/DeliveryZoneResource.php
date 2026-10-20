<?php

namespace App\Filament\Resources\DeliveryZones;

use App\Domain\Delivery\Geometry;
use App\Enums\BranchStatus;
use App\Enums\Permission;
use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\ListDeliveryZones;
use App\Filament\Support\StorePanelScope;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\User;
use App\Rules\ValidGeoJsonPolygon;
use App\Services\Auth\PermissionService;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The store's delivery zones (P1-24): a polygon drawn on a map, with the delivery price, minimum order and free-delivery
 * threshold of that zone. Owner only (zones.manage); everything is scoped to the panel's store.
 *
 * @extends Resource<DeliveryZone>
 */
class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    // Scoped by getEloquentQuery() through StorePanelScope, like the device and employee lists
    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Yetkazib berish hududi';
    protected static ?string $pluralModelLabel = 'Yetkazib berish hududlari';
    protected static ?string $navigationLabel = 'Yetkazib berish hududlari';
    protected static ?int $navigationSort = 4;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(PermissionService::class)->has($user, Permission::ZONES_MANAGE);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny() && StorePanelScope::storeId() !== null;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && $record instanceof DeliveryZone && (int) $record->store_id === StorePanelScope::storeId();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    /**
     * @return Builder<DeliveryZone>
     */
    public static function getEloquentQuery(): Builder
    {
        $storeId = StorePanelScope::storeId();

        // A NULL store would become "store_id IS NULL"
        return $storeId === null ? DeliveryZone::query()->whereRaw('1 = 0') : DeliveryZone::query()->where('store_id', $storeId);
    }

    /**
     * The panel store's branches: choices for the zone's branch and markers on the map.
     *
     * @return Builder<Branch>
     */
    protected static function branches(): Builder
    {
        $storeId = StorePanelScope::storeId();

        return Branch::query()->where('store_id', $storeId ?? 0)->where('status', BranchStatus::ACTIVE->value)->orderBy('name');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Asosiy')
                ->schema([
                    TextInput::make('name')
                        ->label('Nomi')
                        ->placeholder('Masalan: Markaz')
                        ->required()
                        ->maxLength(120),
                    Select::make('branch_id')
                        ->label('Filial')
                        ->options(fn () => static::branches()->pluck('name', 'id')->all())
                        ->placeholder('Eng yaqin ochiq filial')
                        ->helperText("Bo'sh qoldirilsa, buyurtmani manzilga eng yaqin va hozir ochiq filial qabul qiladi."),
                    TextInput::make('priority')
                        ->label('Ustuvorlik')
                        ->numeric()
                        ->integer()
                        ->default(0)
                        ->required()
                        ->minValue(-1000)
                        ->maxValue(1000)
                        ->helperText("Hududlar ustma-ust tushsa, ustuvorligi kattasi tanlanadi; teng bo'lsa maydoni kichigi."),
                    Toggle::make('active')
                        ->label('Faol')
                        ->default(true),
                ])->columns(2),

            Section::make('Narx va shartlar')
                ->schema([
                    TextInput::make('delivery_price')
                        ->label('Yetkazib berish narxi')
                        ->numeric()->minValue(0)->maxValue(99999999)
                        ->default(0)->required()
                        ->suffix("so'm"),
                    TextInput::make('min_order_amount')
                        ->label('Minimal buyurtma')
                        ->numeric()->minValue(0)->maxValue(99999999)
                        ->default(0)->required()
                        ->suffix("so'm"),
                    TextInput::make('free_delivery_from')
                        ->label('Bepul yetkazib berish')
                        ->numeric()->minValue(0)->maxValue(99999999)
                        ->suffix("so'm dan")
                        ->helperText("Buyurtma summasi shu miqdordan oshsa, yetkazib berish bepul. Bo'sh = hech qachon bepul emas."),
                    TextInput::make('eta_minutes')
                        ->label('Taxminiy vaqt')
                        ->numeric()->integer()->minValue(1)->maxValue(600)
                        ->suffix('daqiqa'),
                ])->columns(2),

            Section::make('Hudud')
                ->columnSpanFull()
                ->description("Xaritada ko'pburchak chizing (chap tomondagi asboblar), keyin shakl chetlarini sudrab to'g'rilang. Pastdagi GeoJSON maydonidan ham foydalanish mumkin: tayyor GeoJSON'ni shu yerga qo'ying.")
                ->schema([
                    ViewField::make('polygon')
                        ->label('Xarita')
                        ->view('filament.forms.components.zone-map-editor')
                        ->viewData(fn () => ['branches' => static::branches()->get(['name', 'latitude', 'longitude'])
                            ->filter(fn (Branch $branch) => $branch->latitude !== null && $branch->longitude !== null)
                            ->map(fn (Branch $branch) => ['name' => $branch->name, 'lat' => $branch->latitude, 'lng' => $branch->longitude])
                            ->values()->all()])
                        // The map and the GeoJSON box edit one JSON string; the model gets the cleaned geometry
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state)
                        ->rule(new ValidGeoJsonPolygon())
                        ->dehydrateStateUsing(fn ($state) => Geometry::normalize($state))
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state) => $state === null ? null : number_format((float) $state, 0, ',', ' ') . " so'm";

        return $table
            ->columns([
                TextColumn::make('name')->label('Nomi')->searchable()->weight('bold'),
                TextColumn::make('branch.name')->label('Filial')->placeholder('Eng yaqin ochiq'),
                TextColumn::make('delivery_price')->label('Yetkazish')->formatStateUsing($money)->sortable(),
                TextColumn::make('min_order_amount')->label('Minimal buyurtma')->formatStateUsing($money)->sortable(),
                TextColumn::make('free_delivery_from')->label('Bepul')->formatStateUsing($money)->placeholder('-'),
                TextColumn::make('eta_minutes')->label('Vaqt')->suffix(' daq.')->placeholder('-'),
                TextColumn::make('priority')->label('Ustuvorlik')->sortable(),
                IconColumn::make('active')->label('Faol')->boolean(),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                TernaryFilter::make('active')->label('Faol'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryZones::route('/'),
            'create' => CreateDeliveryZone::route('/create'),
            'edit' => EditDeliveryZone::route('/{record}/edit'),
        ];
    }
}
