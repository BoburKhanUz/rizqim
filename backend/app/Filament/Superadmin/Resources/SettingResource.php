<?php

namespace App\Filament\Superadmin\Resources;

use App\Models\Setting;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Filament\Superadmin\Resources\SettingResource\Pages;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Sozlamalar';
    protected static ?string $navigationLabel = 'Tizim Sozlamalari';
    protected static ?string $modelLabel = 'Tizim Sozlamasi';
    protected static ?string $pluralModelLabel = 'Tizim Sozlamalari';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sozlama tahrirlash')
                    ->schema([
                        TextInput::make('key')
                            ->label('Kalit')
                            ->disabled()
                            ->required()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'sms_driver' => 'SMS Driver (log yoki eskiz)',
                                'eskiz_email' => 'Eskiz API Email',
                                'eskiz_password' => 'Eskiz API Password',
                                'eskiz_from' => 'Eskiz Sender (From)',
                                default => $state,
                            }),

                        TextInput::make('value')
                            ->label('Qiymat')
                            ->required()
                            ->visible(fn ($record) => $record?->key !== 'sms_driver' && $record?->key !== 'eskiz_password')
                            ->placeholder('Qiymatni kiriting...'),

                        TextInput::make('value')
                            ->label('Qiymat')
                            ->password()
                            ->revealable()
                            ->required()
                            ->visible(fn ($record) => $record?->key === 'eskiz_password')
                            ->placeholder('Parolni kiriting...'),

                        Select::make('value')
                            ->label('Qiymat')
                            ->options([
                                'log' => 'Log Driver (Mock - faqat log faylga yozish)',
                                'eskiz' => 'Eskiz SMS Gateway (Real SMS yuborish)',
                            ])
                            ->required()
                            ->visible(fn ($record) => $record?->key === 'sms_driver'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Sozlama nomi')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'sms_driver' => 'SMS Driver',
                        'eskiz_email' => 'Eskiz API Email',
                        'eskiz_password' => 'Eskiz API Password',
                        'eskiz_from' => 'Eskiz Sender (From)',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('value')
                    ->label('Qiymati')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->key === 'eskiz_password' && !empty($state)) {
                            return '••••••••';
                        }
                        if ($record->key === 'sms_driver') {
                            return $state === 'eskiz' ? 'Eskiz SMS Gateway' : 'Log Driver (Mock)';
                        }
                        return $state;
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->modalWidth('xl')
                    ->successNotificationTitle('Sozlama muvaffaqiyatli saqlandi.'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
