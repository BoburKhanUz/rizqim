<?php

namespace App\Filament\Superadmin\Resources\Regions\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DistrictsRelationManager extends RelationManager
{
    protected static string $relationship = 'districts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_uz')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_uz')
            ->columns([
                TextColumn::make('name_uz')
                    ->searchable(),
                \Filament\Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
