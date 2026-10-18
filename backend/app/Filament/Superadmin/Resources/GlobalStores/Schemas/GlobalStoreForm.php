<?php

namespace App\Filament\Superadmin\Resources\GlobalStores\Schemas;

use Filament\Schemas\Schema;

class GlobalStoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Tabs::make('StoreSozlamalari')
                    ->columnSpanFull()
                    ->tabs([
                        \Filament\Schemas\Components\Tabs\Tab::make('Asosiy Ma\'lumotlar')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                \Filament\Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(255),
                                \Filament\Forms\Components\Select::make('status')
                                    ->options(\App\Enums\StoreStatus::class)
                                    ->default(\App\Enums\StoreStatus::PENDING->value)
                                    ->required(),
                                \Filament\Forms\Components\Toggle::make('is_active')
                                    ->label('Active System Login')
                                    ->default(false),
                            ])->columns(2),

                        \Filament\Schemas\Components\Tabs\Tab::make('Moliyaviy Sozlamalar')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('commission_rate')
                                    ->label('Commission Rate (%)')
                                    ->numeric()
                                    ->default(10)
                                    ->required(),
                                \Filament\Forms\Components\TextInput::make('credit_limit')
                                    ->label('Credit Limit (UZS)')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Owes platform maximum threshold (-50000)'),
                                \Filament\Forms\Components\TextInput::make('min_payout_reserve')
                                    ->label('Min. Payout Reserve (UZS)')
                                    ->numeric()
                                    ->default(100000)
                                    ->required()
                                    ->helperText('Minimum balance that must remain in the wallet after withdrawals'),
                                \Filament\Forms\Components\Select::make('payment_account_type')
                                    ->options(\App\Enums\PaymentAccountType::class)
                                    ->default(\App\Enums\PaymentAccountType::PLATFORM_ACCOUNT->value)
                                    ->live()
                                    ->required(),
                                \Filament\Forms\Components\Select::make('settlement_type')
                                    ->options(\App\Enums\SettlementType::class)
                                    ->default(\App\Enums\SettlementType::MANUAL->value)
                                    ->required(),
                                \Filament\Forms\Components\Toggle::make('is_premium')
                                    ->label('Premium Status')
                                    ->default(false),
                                \Filament\Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured Store')
                                    ->default(false),
                                \Filament\Forms\Components\TextInput::make('payment_settings.delivery_fee')
                                    ->label('Yetkazib berish narxi (UZS)')
                                    ->numeric()
                                    ->default(15000)
                                    ->columnSpanFull(),
                                \Filament\Forms\Components\CheckboxList::make('active_payment_methods')
                                    ->label('Ruxsat etilgan to\'lov turlari (Allowed Payment Methods)')
                                    ->options([
                                        'cash' => 'Naqd pul (Cash)',
                                        'click' => 'Click',
                                        'payme' => 'Payme',
                                        'uzum' => 'Uzum Bank',
                                        'paynet' => 'Paynet',
                                    ])
                                    ->default(['cash', 'click', 'payme', 'uzum', 'paynet'])
                                    ->required()
                                    ->columnSpanFull(),
                            ])->columns(2),

                        \Filament\Schemas\Components\Tabs\Tab::make('Telegram va Tafsilotlar')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('telegram_bot_link')
                                    ->label('Bot Connection Link')
                                    ->content(function ($record) {
                                        if (!$record) {
                                            return 'Restoran yaratilgandan so\'ng ulanish linki paydo bo\'ladi.';
                                        }
                                        $botUsername = env('TELEGRAM_BOT_USERNAME', 'YourBotUsername');
                                        $link = "https://t.me/{$botUsername}?start=store_" . $record->uuid;
                                        return new \Illuminate\Support\HtmlString("
                                            <div class='flex flex-col gap-2'>
                                                <a href='{$link}' target='_blank' style='color:#3b82f6; font-weight:bold; text-decoration:underline;'>
                                                    {$link}
                                                </a>
                                                <span class='text-sm text-gray-500'>Bu linkni do'kon menejeriga bering yoki menejerlar guruhiga tashlang. Ular link orqali botni '/start' qilganida, shu guruh avtomatik do'konga bog'lanadi.</span>
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),
                                \Filament\Forms\Components\TextInput::make('telegram_admin_chat_id')
                                    ->label('Admin Group/Chat ID')
                                    ->helperText('Agar link orqali ulanmasa, guruh ID sini qo\'lda yozing (masalan: -1001234567)'),

                                \Filament\Forms\Components\Textarea::make('address')
                                    ->maxLength(65535)
                                    ->columnSpanFull(),
                                \Filament\Forms\Components\Textarea::make('description')
                                    ->maxLength(65535)
                                    ->columnSpanFull(),
                            ])->columns(1),
                    ]),
            ]);
    }
}
