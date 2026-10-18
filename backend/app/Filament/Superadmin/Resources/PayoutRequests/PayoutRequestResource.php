<?php

namespace App\Filament\Superadmin\Resources\PayoutRequests;

use App\Filament\Superadmin\Resources\PayoutRequests\Pages\EditPayoutRequest;
use App\Filament\Superadmin\Resources\PayoutRequests\Pages\ListPayoutRequests;
use App\Models\PayoutRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PayoutRequestResource extends Resource
{
    protected static ?string $model = PayoutRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Yechib Olish So\'rovlari';
    protected static ?string $modelLabel = 'Yechib Olish So\'rovi';
    protected static ?string $pluralModelLabel = 'Yechib Olish So\'rovlari';
    protected static ?int $navigationSort = 6;
    protected static string|\UnitEnum|null $navigationGroup = 'Moliya';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('So\'rov Tafsilotlari')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('store_name')
                            ->label('Restoran')
                            ->content(fn ($record) => $record?->store?->name ?? 'Noma\'lum'),
                        \Filament\Forms\Components\Placeholder::make('amount')
                            ->label('Summa')
                            ->content(fn ($record) => number_format($record?->amount ?? 0) . ' UZS'),
                        \Filament\Forms\Components\Placeholder::make('card_number')
                            ->label('Karta Raqami')
                            ->content(fn ($record) => $record?->card_number ?? ''),
                        \Filament\Forms\Components\Placeholder::make('notes')
                            ->label('Izoh (Restorandan)')
                            ->content(fn ($record) => $record?->notes ?? 'Izohsiz'),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('SuperAdmin Qarori')
                    ->schema([
                        \Filament\Forms\Components\Select::make('status')
                            ->label('Holati')
                            ->options([
                                'pending' => 'Kutilmoqda (Pending)',
                                'completed' => 'Tasdiqlandi (Completed)',
                                'rejected' => 'Rad etildi (Rejected)',
                            ])
                            ->required()
                            ->disabled(fn ($record) => $record && $record->status !== 'pending'),
                        \Filament\Forms\Components\Textarea::make('resolution_notes')
                            ->label('SuperAdmin izohi / Rad etish sababi')
                            ->rows(3)
                            ->placeholder('Agar so\'rov rad etilsa sababini yozing...')
                            ->disabled(fn ($record) => $record && $record->status !== 'pending'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Restoran')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->money('UZS', locale: 'uz-UZ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('card_number')
                    ->label('Karta Raqami')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Holati')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'KUTILMOQDA',
                        'completed' => 'TASDIQLANDI',
                        'rejected' => 'RAD ETILDI',
                        default => strtoupper($state),
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yaratilgan Vaqt')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Kutilmoqda',
                        'completed' => 'Tasdiqlangan',
                        'rejected' => 'Rad etilgan',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayoutRequests::route('/'),
            'edit' => EditPayoutRequest::route('/{record}/edit'),
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
