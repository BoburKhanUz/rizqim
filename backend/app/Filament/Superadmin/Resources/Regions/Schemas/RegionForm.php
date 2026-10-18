<?php

namespace App\Filament\Superadmin\Resources\Regions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RegionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_uz')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
