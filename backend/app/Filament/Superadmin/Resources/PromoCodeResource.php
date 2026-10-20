<?php

namespace App\Filament\Superadmin\Resources;

use App\Models\PromoCode;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use App\Filament\Superadmin\Resources\PromoCodeResource\Pages;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?string $navigationLabel = 'Promo-kodlar';
    protected static ?string $modelLabel = 'Promo-kod';
    protected static ?string $pluralModelLabel = 'Promo-kodlar';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Promo-kod Tafsilotlari')
                    ->schema([
                        TextInput::make('code')
                            ->label('Promo-kod (KODI)')
                            ->required()
                            ->maxLength(50)
                            ->dehydrateStateUsing(fn ($state) => strtoupper(trim($state)))
                            ->placeholder('YANGI2026')
                            ->unique(ignoreRecord: true)
                            ->prefix('🎫')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase; font-weight: bold;'])
                            ->columnSpan(2),

                        Select::make('type')
                            ->label('Turi')
                            ->options([
                                'percentage' => 'Foiz (%)',
                                'fixed' => 'Belgilangan summa (UZS)',
                            ])
                            ->required()
                            ->default('percentage')
                            ->selectablePlaceholder(false),

                        TextInput::make('value')
                            ->label('Chegirma qiymati')
                            ->numeric()
                            ->required()
                            ->default(10)
                            ->placeholder('Qiymatni kiriting...'),
                    ])
                    ->columns(2),

                Section::make('Cheklovlar va Biriktirish')
                    ->schema([
                        TextInput::make('min_order_amount')
                            ->label('Minimal buyurtma summasi (UZS)')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->prefix('sum'),

                        TextInput::make('max_discount_amount')
                            ->label('Maksimal chegirma summasi (UZS)')
                            ->numeric()
                            ->placeholder('Cheklanmagan')
                            ->nullable()
                            ->prefix('sum'),

                        TextInput::make('usage_limit')
                            ->label('Umumiy foydalanish limiti')
                            ->integer()
                            ->minValue(1)
                            ->nullable()
                            ->placeholder('Cheklanmagan')
                            ->helperText("Barcha mijozlar bo'yicha jami necha marta ishlatish mumkin. Bekor qilingan buyurtmalar hisoblanmaydi."),

                        TextInput::make('per_user_limit')
                            ->label('Bir mijoz uchun limit')
                            ->integer()
                            ->minValue(1)
                            ->nullable()
                            ->default(1)
                            ->placeholder('Cheklanmagan')
                            ->helperText("Bitta mijoz necha marta ishlata oladi. Bo'sh qoldirilsa — cheklanmagan."),

                        Select::make('store_id')
                            ->label('Biriktirilgan Oshxona (Global uchun bo\'sh qoldiring)')
                            ->relationship('store', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder("Tizim bo'yicha global (Barcha oshxonalar)")
                            ->hint("Agar ma'lum bir oshxonaga biriktirilsa, faqat o'sha oshxonada ishlaydi")
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('Holat va Muddat')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Faol holatda')
                            ->helperText("Promo-koddan foydalanishni yoqish yoki o'chirish")
                            ->default(true)
                            ->required(),

                        DateTimePicker::make('expires_at')
                            ->label('Amal qilish muddati')
                            ->placeholder('Cheksiz (Muddatsiz)')
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kodi')
                    ->searchable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->copyable()
                    ->description(fn (PromoCode $record) => $record->type === 'percentage' ? "Chegirma: {$record->value}%" : "Chegirma: " . number_format($record->value, 0, '.', ' ') . " UZS"),

                Tables\Columns\TextColumn::make('min_order_amount')
                    ->label('Min. Buyurtma')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 0, '.', ' ') . ' UZS'),

                Tables\Columns\TextColumn::make('max_discount_amount')
                    ->label('Maks. Chegirma')
                    ->numeric()
                    ->sortable()
                    ->placeholder('Cheklanmagan')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 0, '.', ' ') . ' UZS' : 'Cheklanmagan'),

                Tables\Columns\TextColumn::make('redemptions_count')
                    ->label('Ishlatilgan')
                    ->counts('redemptions')
                    ->description(fn (PromoCode $record) => 'Limit: ' . ($record->usage_limit ?? 'cheklanmagan') . ' / mijozga: ' . ($record->per_user_limit ?? 'cheklanmagan'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Birlashgan Oshxona')
                    ->placeholder('Global (Tizimdagi barcha)')
                    ->searchable()
                    ->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'primary'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Faol'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Muddati')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Muddatsiz')
                    ->sortable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->modalWidth('7xl'),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
        ];
    }
}
