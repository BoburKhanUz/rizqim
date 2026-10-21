<?php

namespace App\Filament\Superadmin\Resources\TariffPlans;

use App\Filament\Superadmin\Resources\TariffPlans\Pages\CreateTariffPlan;
use App\Filament\Superadmin\Resources\TariffPlans\Pages\EditTariffPlan;
use App\Filament\Superadmin\Resources\TariffPlans\Pages\ListTariffPlans;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * P1-23: tariff plans (docs/BILLING.md). New prices apply to the next invoices of every store on the plan; a new
 * commission applies to orders placed from now on (each order keeps the commission it was placed with).
 */
class TariffPlanResource extends Resource
{
    protected static ?string $model = TariffPlan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string|\UnitEnum|null $navigationGroup = 'Tariflar';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Tarif rejalari';
    protected static ?string $modelLabel = 'Tarif';
    protected static ?string $pluralModelLabel = 'Tarif rejalari';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tarif')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label('Nomi')->required()->maxLength(100),
                    TextInput::make('code')->label('Kod')->required()->maxLength(50)->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText("Ichki nom (lotin harflari), masalan: pos, mixed."),
                    Toggle::make('is_active')->label('Faol (yangi do\'konlarga biriktirish mumkin)')->default(true),
                    Textarea::make('description')->label('Tavsif')->rows(2)->columnSpanFull(),
                ]),
            Section::make('Narxlar')
                ->description("Komissiya — onlayn buyurtmalardan. Oylik to'lov = asosiy to'lov + (terminallar − tarifga kirganlar) × terminal narxi. Narx o'zgarsa, keyingi hisob-fakturalardan amal qiladi.")
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('commission_percent')->label('Komissiya (%)')->numeric()->minValue(0)->maxValue(100)->step('0.01')
                        ->helperText("Bo'sh qoldirilsa, do'kon sozlamasidagi komissiya foizi olinadi. 0 — komissiya yo'q."),
                    TextInput::make('monthly_base_fee')->label("Oylik asosiy to'lov (UZS)")->numeric()->minValue(0)->maxValue(9999999999)->step('0.01')
                        ->helperText("Bo'sh yoki 0 — asosiy to'lov yo'q."),
                    TextInput::make('monthly_fee_per_terminal')->label('Bir terminal uchun oylik narx (UZS)')->numeric()->minValue(0)->maxValue(9999999999)->step('0.01')
                        ->helperText("Kassa (POS) va oshxona ekrani (KDS) terminallari hisoblanadi."),
                    TextInput::make('included_terminals')->label('Tarifga kirgan terminallar soni')->integer()->minValue(0)->maxValue(10000)->default(0)->required(),
                    TextInput::make('trial_days')->label('Sinov davri (kun)')->integer()->minValue(0)->maxValue(365)->default(0)->required()
                        ->helperText("Faqat do'konning birinchi obunasiga beriladi. Sinov davri tegib o'tgan oy uchun hisob-faktura chiqmaydi."),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nomi')->searchable()->sortable(),
                TextColumn::make('code')->label('Kod')->badge()->color('gray'),
                TextColumn::make('summary')->label('Narxlar')->state(fn (TariffPlan $record) => $record->summary())->wrap(),
                TextColumn::make('trial_days')->label('Sinov (kun)')->sortable(),
                TextColumn::make('current_subscriptions')->label("Do'konlar")
                    ->state(fn (TariffPlan $record) => StoreSubscription::query()->current()->where('tariff_plan_id', $record->id)->count()),
                IconColumn::make('is_active')->label('Faol')->boolean(),
                TextColumn::make('updated_at')->label('Yangilangan')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /**
     * A plan any store has used is kept for its history and invoices: switch it off instead.
     */
    public static function canDelete(Model $record): bool
    {
        return !StoreSubscription::where('tariff_plan_id', $record->getKey())->orWhere('next_tariff_plan_id', $record->getKey())->exists()
            && !SubscriptionInvoice::where('tariff_plan_id', $record->getKey())->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTariffPlans::route('/'),
            'create' => CreateTariffPlan::route('/create'),
            'edit' => EditTariffPlan::route('/{record}/edit'),
        ];
    }
}
