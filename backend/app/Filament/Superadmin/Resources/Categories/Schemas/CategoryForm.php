<?php

namespace App\Filament\Superadmin\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                    ->maxLength(255),
                Select::make('parent_id')
                    ->label('Ota kategoriya')
                    ->relationship(
                        'parent',
                        'name',
                        fn ($query, $record) => $record ? $query->where('id', '!=', $record->id) : $query
                    )
                    ->placeholder('Asosiy (Ota) kategoriya')
                    ->nullable(),
                \Filament\Forms\Components\Hidden::make('slug')
                    ->required(),
                FileUpload::make('image')
                    ->image()
                    ->directory('categories'),
                TextInput::make('sort_order')
                     ->numeric()
                     ->default(fn () => (\App\Models\Category::max('sort_order') ?? 0) + 1),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
