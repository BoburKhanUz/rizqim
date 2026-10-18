<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

class EditStoreProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Restoran Profili';
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $tenant): bool
    {
        return in_array(auth()->user()?->role, [
            \App\Enums\UserRole::OWNER,
            \App\Enums\UserRole::SUPER_ADMIN,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Tabs::make('StoreSozlamalari')
                    ->columnSpanFull()
                    ->tabs([
                        \Filament\Schemas\Components\Tabs\Tab::make('Asosiy Ma\'lumotlar')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(255),
                            ])->columns(2),

                        \Filament\Schemas\Components\Tabs\Tab::make('Media')
                            ->schema([
                                FileUpload::make('logo')
                                    ->image()
                                    ->directory('stores/logos'),
                                FileUpload::make('banner')
                                    ->image()
                                    ->directory('stores/banners'),
                            ])->columns(2),

                        \Filament\Schemas\Components\Tabs\Tab::make('To\'lov va Kuryer Sozlamalari')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('payment_info')
                                    ->label('To\'lov Tizimi Turi')
                                    ->content(fn ($record) => $record?->payment_account_type === 'platform_account' 
                                        ? new \Illuminate\Support\HtmlString('<div class="p-3 bg-emerald-500/10 text-emerald-700 text-xs rounded-xl border border-emerald-500/20 font-semibold leading-relaxed"><b>Platforma To\'lovi (Platform Account)</b>: Onlayn to\'lovlar platforma hisoblariga qabul qilinadi va hamyoningizga tushadi. Shaxsiy merchant ID va klyuchlarni sozlash shart emas.</div>') 
                                        : new \Illuminate\Support\HtmlString('<div class="p-3 bg-amber-500/10 text-amber-700 text-xs rounded-xl border border-amber-500/20 font-semibold leading-relaxed"><b>Shaxsiy Hisob (Own Account)</b>: Onlayn to\'lovlar to\'g\'ridan-to\'g\'ri restoraningiz hisobiga tushadi. Quyida Click/Payme shaxsiy merchant sozlamalarini to\'g\'ri kiritishingiz kerak.</div>')
                                    )
                                    ->columnSpanFull(),

                                TextInput::make('payment_settings.delivery_fee')
                                    ->label('Yetkazib berish narxi (UZS)')
                                    ->numeric()
                                    ->default(15000)
                                    ->columnSpanFull(),

                                \Filament\Forms\Components\Toggle::make('payment_settings.delivery_enabled')
                                    ->label('Yetkazib berish (Delivery) xizmati faol')
                                    ->default(true),

                                \Filament\Forms\Components\Toggle::make('payment_settings.pickup_enabled')
                                    ->label('Olib ketish (Pickup/Takeaway) xizmati faol')
                                    ->default(true),

                                // Cash Fieldset
                                \Filament\Schemas\Components\Fieldset::make('Naqd pul (Cash)')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('payment_settings.cash.enabled')
                                            ->label('Naqd (Cash) orqali to\'lov faol')
                                            ->default(true),
                                    ])
                                    ->visible(fn ($record) => !$record || in_array('cash', $record->payment_settings['allowed_methods'] ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),

                                // Click Fieldset
                                \Filament\Schemas\Components\Fieldset::make('CLICK Tizimi')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('payment_settings.click.enabled')
                                            ->label('CLICK orqali to\'lov faol')
                                            ->live()
                                            ->default(true)
                                            ->columnSpanFull(),
                                        TextInput::make('payment_settings.click.merchant_id')
                                            ->label('Click Merchant ID')
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.click.enabled')),
                                        TextInput::make('payment_settings.click.service_id')
                                            ->label('Click Service ID')
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.click.enabled')),
                                        TextInput::make('payment_settings.click.secret_key')
                                            ->label('Click Secret Key')
                                            ->password()
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.click.enabled'))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($record) => !$record || in_array('click', $record->payment_settings['allowed_methods'] ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),

                                // Payme Fieldset
                                \Filament\Schemas\Components\Fieldset::make('Payme Tizimi')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('payment_settings.payme.enabled')
                                            ->label('Payme orqali to\'lov faol')
                                            ->live()
                                            ->default(true)
                                            ->columnSpanFull(),
                                        TextInput::make('payment_settings.payme.merchant_id')
                                            ->label('Payme Merchant ID')
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.payme.enabled')),
                                        TextInput::make('payment_settings.payme.secret_key')
                                            ->label('Payme Secret Key')
                                            ->password()
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.payme.enabled')),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($record) => !$record || in_array('payme', $record->payment_settings['allowed_methods'] ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),

                                // Uzum Fieldset
                                \Filament\Schemas\Components\Fieldset::make('Uzum Bank')
                                    ->schema([
                                        \App\Filament\Pages\StoreSettings::unlessUnavailable(\Filament\Forms\Components\Toggle::make('payment_settings.uzum.enabled')
                                            ->label('Uzum Bank orqali to\'lov faol')
                                            ->live()
                                            ->default(true)
                                            ->columnSpanFull(), 'uzum'),
                                        TextInput::make('payment_settings.uzum.merchant_id')
                                            ->label('Uzum Merchant ID')
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.uzum.enabled')),
                                        TextInput::make('payment_settings.uzum.secret_key')
                                            ->label('Uzum Secret Key')
                                            ->password()
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.uzum.enabled')),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($record) => !$record || !\App\Models\Store::isPaymentMethodAvailable('uzum') || in_array('uzum', $record->payment_settings['allowed_methods'] ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),

                                // Paynet Fieldset
                                \Filament\Schemas\Components\Fieldset::make('Paynet')
                                    ->schema([
                                        \App\Filament\Pages\StoreSettings::unlessUnavailable(\Filament\Forms\Components\Toggle::make('payment_settings.paynet.enabled')
                                            ->label('Paynet orqali to\'lov faol')
                                            ->live()
                                            ->default(true)
                                            ->columnSpanFull(), 'paynet'),
                                        TextInput::make('payment_settings.paynet.merchant_id')
                                            ->label('Paynet Merchant ID')
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.paynet.enabled')),
                                        TextInput::make('payment_settings.paynet.secret_key')
                                            ->label('Paynet Secret Key')
                                            ->password()
                                            ->visible(fn ($get, $record) => $record && $record->payment_account_type === 'own_account' && $get('payment_settings.paynet.enabled')),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($record) => !$record || !\App\Models\Store::isPaymentMethodAvailable('paynet') || in_array('paynet', $record->payment_settings['allowed_methods'] ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
