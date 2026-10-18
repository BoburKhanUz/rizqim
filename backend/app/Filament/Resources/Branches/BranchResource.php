<?php

namespace App\Filament\Resources\Branches;

use App\Filament\Resources\Branches\Pages;
use App\Models\Branch;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Filiallar';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $schemaComponents = [];

        if (\Illuminate\Support\Facades\Auth::user()?->role === \App\Enums\UserRole::SUPER_ADMIN) {
            $schemaComponents[] = \Filament\Forms\Components\Placeholder::make('emergency_warning')
                ->hiddenLabel()
                ->content(new \Illuminate\Support\HtmlString('<div style="padding: 1rem; background-color: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.25rem;">⚠</span>
                    <div>
                        <strong>Emergency Access:</strong> You are modifying store operations directly as a SuperAdmin. Every change will be logged under `superadmin_override` action.
                    </div>
                </div>'))
                ->columnSpanFull();
        }

        $schemaComponents = array_merge($schemaComponents, [
            Schemas\Components\Section::make('General Information')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Branch Name')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),
                    \Filament\Forms\Components\Toggle::make('is_active')
                        ->label('Active Branch')
                        ->default(true),
                ])->columns(2),

            Schemas\Components\Section::make('Location & Working Hours')
                ->schema([
                    \Filament\Forms\Components\Select::make('region_id')
                        ->label('Region')
                        ->options(\App\Models\Region::where('is_active', true)->pluck('name_uz', 'id'))
                        ->live()
                        ->required(),
                    \Filament\Forms\Components\Select::make('district_id')
                        ->label('District')
                        ->options(fn ($get) => \App\Models\District::where('is_active', true)
                            ->where('region_id', $get('region_id'))
                            ->pluck('name_uz', 'id')
                        )
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('address')
                        ->label('Street Address / Landmark')
                        ->maxLength(65535)
                        ->columnSpanFull()
                        ->required(),
                    \Filament\Forms\Components\ViewField::make('map_picker')
                        ->label('Xaritadan tanlang')
                        ->view('filament.forms.components.leaflet-map', ['latField' => 'data.latitude', 'lngField' => 'data.longitude'])
                        ->columnSpanFull(),
                    \Filament\Forms\Components\TextInput::make('latitude')
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90)
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('longitude')
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180)
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('radius_km')
                        ->label('Delivery Radius (km)')
                        ->numeric()
                        ->default(5)
                        ->required(),
                    \Filament\Forms\Components\TimePicker::make('open_time'),
                    \Filament\Forms\Components\TimePicker::make('close_time'),
                ])->columns(2),
        ]);

        return $schema->schema($schemaComponents);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('radius_km')
                    ->label('Radius')
                    ->suffix(' km'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
