<?php

namespace App\Filament\Superadmin\Resources\Districts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DistrictForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('region_id')
                    ->relationship('region', 'name_uz')
                    ->required(),
                \Filament\Forms\Components\TextInput::make('name_uz')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
