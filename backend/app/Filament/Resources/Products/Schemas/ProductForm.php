<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Support\StorePanelScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Forms\Set;
use Illuminate\Support\Str;
use App\Enums\MenuUnit;
use App\Enums\OrderChannel;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Services\Catalog\MenuImageService;
use App\Support\QuantityRules;
use Filament\Schemas\Components\Utilities\Get;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        $components = [];

        if (\Illuminate\Support\Facades\Auth::user()?->role === \App\Enums\UserRole::SUPER_ADMIN) {
            $components[] = \Filament\Forms\Components\Placeholder::make('emergency_warning')
                ->hiddenLabel()
                ->content(new \Illuminate\Support\HtmlString('<div style="padding: 1rem; background-color: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                    <span style="font-size: 1.25rem;">⚠</span>
                    <div>
                        <strong>Emergency Access:</strong> You are modifying store operations directly as a SuperAdmin. Every change will be logged under `superadmin_override` action.
                    </div>
                </div>'))
                ->columnSpanFull();
        }

        $components = array_merge($components, [
            Group::make()
                    ->schema([
                        Section::make('General Information')
                            ->description('Basic details about the product.')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                                \Filament\Forms\Components\Hidden::make('slug')
                                    ->required()
                                    // Unique within the store: checked against every store, it refused a name another
                                    // restaurant already used and so told the owner what that restaurant sells
                                    ->scopedUnique(ignoreRecord: true, modifyQueryUsing: fn ($query) => $query->where('store_id', StorePanelScope::storeId())),
                                Textarea::make('description')
                                    ->rows(4),
                            ])->columns(1),

                        Section::make('Pricing & Inventory (Variants)')
                            ->description('Manage product variants such as sizes or colors. Masalan: Small, Medium yoki 0.5L, 1L')
                            ->schema([
                                Repeater::make('variants')
                                    ->relationship()
                                    ->hiddenLabel()
                                    ->addActionLabel('Add Variant')
                                    ->collapsible()
                                    ->defaultItems(1)
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->label('Variant Name')
                                            ->placeholder('e.g. 1L, Large, etc.')
                                            ->columnSpan(2),
                                        TextInput::make('price')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(9999999999)
                                            ->prefix('UZS')
                                            ->columnSpan(2),
                                        \Filament\Forms\Components\Hidden::make('stock')
                                            ->default(999),
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->columnSpan(1),
                                        // P1-21: sold by the piece or by weight (price is per kg / l), batch use
                                        Select::make('unit')
                                            ->label("O'lchov birligi")
                                            ->options(collect(MenuUnit::cases())->mapWithKeys(fn (MenuUnit $unit) => [$unit->value => $unit->label()])->all())
                                            ->default(MenuUnit::PIECE->value)
                                            ->required()
                                            ->live()
                                            ->columnSpan(2),
                                        TextInput::make('quantity_step')
                                            ->label('Qadam')
                                            ->numeric()
                                            ->minValue(0.001)
                                            ->maxValue(99999)
                                            ->placeholder('0.1')
                                            ->visible(fn (Get $get) => $get('unit') !== null && $get('unit') !== MenuUnit::PIECE->value)
                                            ->columnSpan(1),
                                        TextInput::make('min_quantity')
                                            ->label('Eng kam')
                                            ->numeric()
                                            ->minValue(0.001)
                                            ->maxValue(99999)
                                            // A multiple of the step, or no quantity at or above it is accepted at checkout
                                            ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                                $problem = QuantityRules::minimumProblem($value, $get('quantity_step'));
                                                if ($problem !== null) {
                                                    $fail($problem);
                                                }
                                            })
                                            ->visible(fn (Get $get) => $get('unit') !== null && $get('unit') !== MenuUnit::PIECE->value)
                                            ->columnSpan(1),
                                        TextInput::make('batch_consumption')
                                            ->label('Qozondan (1 birlikka)')
                                            ->helperText('Masalan 0.7 porsiya uchun 0.7')
                                            ->numeric()
                                            ->minValue(0.001)
                                            ->maxValue(99999)
                                            ->default(1)
                                            ->columnSpan(2),
                                    ])
                                    ->columns(6)
                            ]),

                        // P1-21: the modifier groups this product offers, with per-product rules (docs/MENU.md)
                        Section::make('Modifikatorlar')
                            ->description("Taomga ulangan modifikator guruhlari. Bo'sh qoldirilgan qoidalar guruhnikidan olinadi.")
                            ->collapsible()
                            ->schema([
                                Repeater::make('modifierGroupLinks')
                                    ->relationship()
                                    ->hiddenLabel()
                                    ->defaultItems(0)
                                    ->orderColumn('sort_order')
                                    ->addActionLabel("Guruh qo'shish")
                                    ->schema([
                                        Select::make('modifier_group_id')
                                            ->label('Guruh')
                                            ->options(fn () => ModifierGroup::where('store_id', StorePanelScope::storeId())->orderBy('sort_order')->pluck('name', 'id')->all())
                                            ->required()
                                            ->distinct()
                                            ->columnSpan(3),
                                        TextInput::make('min_select')->label('Kamida')->numeric()->minValue(0)->maxValue(50)->columnSpan(1),
                                        TextInput::make('max_select')->label("Ko'pi bilan")->numeric()->minValue(1)->maxValue(50)->columnSpan(1),
                                        Select::make('is_required')
                                            ->label('Majburiy')
                                            ->options([1 => 'Ha', 0 => "Yo'q"])
                                            ->placeholder('Guruhdagidek')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(6),
                            ]),

                        // P1-21: own price or hidden on a sales channel; no row = the variant's price, visible
                        Section::make('Kanal narxlari')
                            ->description("Telegram, mobil ilova, POS yoki agregatorda boshqa narx yoki yashirish.")
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Repeater::make('channelPrices')
                                    ->relationship()
                                    ->hiddenLabel()
                                    ->defaultItems(0)
                                    ->addActionLabel("Qator qo'shish")
                                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => $data + [
                                        'store_id' => StorePanelScope::storeId(),
                                    ])
                                    ->schema([
                                        Select::make('channel')
                                            ->label('Kanal')
                                            ->options(collect(OrderChannel::cases())->mapWithKeys(fn (OrderChannel $channel) => [$channel->value => $channel->label()])->all())
                                            ->required()
                                            ->columnSpan(2),
                                        Select::make('product_variant_id')
                                            ->label('Variant')
                                            // The product's variants (the page's record; none yet while creating)
                                            ->options(function ($livewire) {
                                                $product = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;

                                                return $product instanceof Product ? $product->variants()->pluck('name', 'id')->all() : [];
                                            })
                                            ->placeholder('Butun mahsulot')
                                            ->columnSpan(2),
                                        TextInput::make('price')->label('Narx')->numeric()->minValue(0)->maxValue(9999999999)->prefix('UZS')->columnSpan(1),
                                        Toggle::make('is_hidden')->label('Yashirish')->default(false)->columnSpan(1),
                                    ])
                                    ->columns(6),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Organization')
                            ->extraAttributes(['style' => 'z-index: 50; position: relative;'])
                            ->schema([
                                Select::make('category_id')
                                    ->relationship('category', 'name', fn ($query) => $query->whereDoesntHave('children'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),

                        Section::make('Media')
                            ->schema([
                                FileUpload::make('image')
                                    ->hiddenLabel()
                                    ->image()
                                    ->imageEditor()
                                    // P1-21: the menu image disk (S3 when configured); thumbnails are queued on save
                                    ->disk(fn () => app(MenuImageService::class)->disk())
                                    ->directory('products'),
                            ]),

                        Section::make('Visibility')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Visible to Customers')
                                    ->default(true),
                                // P1-21: "tugadi" on a branch unless an open batch (kazan) has something left
                                Toggle::make('batch_tracked')
                                    ->label("Qozon bo'yicha sotiladi")
                                    ->helperText("Yoqilsa, filialda ochiq partiya bo'lmasa taom \"tugadi\" bo'ladi.")
                                    ->default(false),
                                TextInput::make('sort_order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers are displayed first.'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
        ]);

        return $schema->components($components)->columns(3);
    }
}
