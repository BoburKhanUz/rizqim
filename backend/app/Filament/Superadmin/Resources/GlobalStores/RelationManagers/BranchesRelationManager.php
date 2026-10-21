<?php

namespace App\Filament\Superadmin\Resources\GlobalStores\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('General Information')
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

                \Filament\Schemas\Components\Section::make('Location & Working Hours')
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
                            ->view('filament.forms.components.leaflet-map', [
                                'latField' => 'mountedTableActionData.0.latitude',
                                'lngField' => 'mountedTableActionData.0.longitude'
                            ])
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
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('address'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                // P2-33: refused while staff, devices or zones are tied to the branch
                \App\Filament\Support\BranchDeletionGuard::action(\Filament\Actions\DeleteAction::make()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \App\Filament\Support\BranchDeletionGuard::bulk(\Filament\Actions\DeleteBulkAction::make()),
                ]),
            ]);
    }
}
