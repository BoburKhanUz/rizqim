<?php

namespace App\Filament\Resources\Products\Schemas;

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
                                    ->unique(ignoreRecord: true),
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
                                            ->prefix('UZS')
                                            ->columnSpan(2),
                                        \Filament\Forms\Components\Hidden::make('stock')
                                            ->default(999),
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->columnSpan(1),
                                    ])
                                    ->columns(6)
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
                                    ->directory('products'),
                            ]),

                        Section::make('Visibility')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Visible to Customers')
                                    ->default(true),
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
