<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class StoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Sozlamalar';
    protected static ?string $title = 'Restoran Sozlamalari';
    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, [
            \App\Enums\UserRole::OWNER,
            \App\Enums\UserRole::SUPER_ADMIN,
        ]);
    }

    protected string $view = 'filament.pages.store-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            // Attributes (not the public toArray(), which drops internal fields such as telegram_admin_chat_id);
            // secrets stay out of the page state and are kept on save by Store's settings merge.
            $data = $tenant->attributesToArray();

            // Map old structure for the form if it exists
            $settings = \App\Models\Store::withoutSecrets($data['payment_settings'] ?? []);
            if (isset($settings['allowed_methods'])) {
                foreach (['cash', 'click', 'payme', 'uzum', 'paynet'] as $provider) {
                    if (!isset($settings[$provider])) {
                        $settings[$provider] = [];
                    }
                    if (!isset($settings[$provider]['enabled'])) {
                        $settings[$provider]['enabled'] = in_array($provider, $settings['allowed_methods']);
                    }
                }
            }
            $data['payment_settings'] = $settings;

            $this->form->fill($data);
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
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
                                TextInput::make('address')
                                    ->label('Manzil')
                                    ->maxLength(500),
                                TextInput::make('location_url')
                                    ->label('Lokatsiya (Map URL)')
                                    ->maxLength(1000),
                                TextInput::make('description')
                                    ->label('Tavsif (Description)')
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ])->columns(2),

                        \Filament\Schemas\Components\Tabs\Tab::make('Ish vaqti va Cheklovlar')
                            ->schema([
                                TextInput::make('min_order_amount')
                                    ->label('Minimal buyurtma miqdori (UZS)')
                                    ->numeric()
                                    ->default(30000)
                                    ->required(),
                                
                                TextInput::make('timezone')
                                    ->label('Vaqt zonasi')
                                    ->default('Asia/Tashkent')
                                    ->required(),

                                TextInput::make('open_time')
                                    ->label('Ochilish vaqti (HH:MM:SS)')
                                    ->placeholder('e.g. 09:00:00')
                                    ->required(),

                                TextInput::make('close_time')
                                    ->label('Yopilish vaqti (HH:MM:SS)')
                                    ->placeholder('e.g. 23:00:00')
                                    ->required(),

                                Toggle::make('is_open')
                                    ->label('Do\'kon ochiq (Faol)')
                                    ->default(true),
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

                        \Filament\Schemas\Components\Tabs\Tab::make('Telegram')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('telegram_bot_link')
                                    ->label('Botga ulanish havolasi (Link)')
                                    ->content(function () {
                                        $record = Filament::getTenant();
                                        if (!$record) return 'Restoran topilmadi.';
                                        $botUsername = env('TELEGRAM_BOT_USERNAME', 'YourBotUsername');
                                        $link = "https://t.me/{$botUsername}?start=store_" . $record->uuid;
                                        return new \Illuminate\Support\HtmlString("
                                            <div class='flex flex-col gap-2'>
                                                <a href='{$link}' target='_blank' style='color:#3b82f6; font-weight:bold; text-decoration:underline;'>
                                                    {$link}
                                                </a>
                                                <span class='text-sm text-gray-500'>Bu link orqali botga kirsangiz, buyurtmalar keladigan guruhga ulanasiz.</span>
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),
                                TextInput::make('telegram_admin_chat_id')
                                    ->label('Telegram Guruh/Chat ID')
                                    ->helperText('Super Admin tomonidan kiritilgan telegram ID.'),
                            ])->columns(1),

                        \Filament\Schemas\Components\Tabs\Tab::make('To\'lov va Kuryer Sozlamalari')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('payment_info')
                                    ->label('To\'lov Tizimi Turi')
                                    ->content(fn () => Filament::getTenant()?->payment_account_type === 'platform_account' 
                                        ? new \Illuminate\Support\HtmlString('<div class="p-3 bg-emerald-500/10 text-emerald-700 text-xs rounded-xl border border-emerald-500/20 font-semibold leading-relaxed"><b>Platforma To\'lovi (Platform Account)</b>: Onlayn to\'lovlar platforma hisoblariga qabul qilinadi va hamyoningizga tushadi. Shaxsiy merchant ID va klyuchlarni sozlash shart emas.</div>') 
                                        : new \Illuminate\Support\HtmlString('<div class="p-3 bg-amber-500/10 text-amber-700 text-xs rounded-xl border border-amber-500/20 font-semibold leading-relaxed"><b>Shaxsiy Hisob (Own Account)</b>: Onlayn to\'lovlar to\'g\'ridan-to\'g\'ri restoraningiz hisobiga tushadi. Quyida Click/Payme shaxsiy merchant sozlamalarini to\'g\'ri kiritishingiz kerak.</div>')
                                    )
                                    ->columnSpanFull(),

                                TextInput::make('payment_settings.delivery_fee')
                                    ->label('Yetkazib berish narxi (UZS)')
                                    ->numeric()
                                    ->default(15000)
                                    ->columnSpanFull(),

                                Toggle::make('payment_settings.delivery_enabled')
                                    ->label('Yetkazib berish (Delivery) faol')
                                    ->default(true),

                                Toggle::make('payment_settings.pickup_enabled')
                                    ->label('Olib ketish (Pickup/Takeaway) faol')
                                    ->default(true),

                                // Cash Fieldset
                                \Filament\Schemas\Components\Fieldset::make('Naqd pul (Cash)')
                                    ->schema([
                                        Toggle::make('payment_settings.cash.enabled')
                                            ->label('Naqd (Cash) faol')
                                            ->default(true),
                                    ])
                                    ->visible(fn () => in_array('cash', Filament::getTenant()?->active_payment_methods ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),

                                // Click Fieldset
                                \Filament\Schemas\Components\Fieldset::make('CLICK Tizimi')
                                    ->schema([
                                        Toggle::make('payment_settings.click.enabled')
                                            ->label('Click faol')
                                            ->live()
                                            ->default(true)
                                            ->columnSpanFull(),
                                        TextInput::make('payment_settings.click.merchant_id')
                                            ->label('Click Merchant ID')
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.click.enabled')),
                                        TextInput::make('payment_settings.click.service_id')
                                            ->label('Click Service ID')
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.click.enabled')),
                                        TextInput::make('payment_settings.click.secret_key')
                                            ->label('Click Secret Key')
                                            ->password()
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.click.enabled'))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                     ->visible(fn () => in_array('click', Filament::getTenant()?->active_payment_methods ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),

                                // Payme Fieldset
                                \Filament\Schemas\Components\Fieldset::make('Payme Tizimi')
                                    ->schema([
                                        Toggle::make('payment_settings.payme.enabled')
                                            ->label('Payme faol')
                                            ->live()
                                            ->default(true)
                                            ->columnSpanFull(),
                                        TextInput::make('payment_settings.payme.merchant_id')
                                            ->label('Payme Merchant ID')
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.payme.enabled')),
                                        TextInput::make('payment_settings.payme.secret_key')
                                            ->label('Payme Secret Key')
                                            ->password()
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.payme.enabled')),
                                    ])
                                    ->columns(2)
                                     ->visible(fn () => in_array('payme', Filament::getTenant()?->active_payment_methods ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),

                                // Uzum Fieldset
                                \Filament\Schemas\Components\Fieldset::make('Uzum Bank')
                                    ->schema([
                                        Toggle::make('payment_settings.uzum.enabled')
                                            ->label('Uzum faol')
                                            ->live()
                                            ->default(true)
                                            ->columnSpanFull(),
                                        TextInput::make('payment_settings.uzum.merchant_id')
                                            ->label('Uzum Merchant ID')
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.uzum.enabled')),
                                        TextInput::make('payment_settings.uzum.secret_key')
                                            ->label('Uzum Secret Key')
                                            ->password()
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.uzum.enabled')),
                                    ])
                                    ->columns(2)
                                     ->visible(fn () => in_array('uzum', Filament::getTenant()?->active_payment_methods ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),

                                // Paynet Fieldset
                                \Filament\Schemas\Components\Fieldset::make('Paynet')
                                    ->schema([
                                        Toggle::make('payment_settings.paynet.enabled')
                                            ->label('Paynet faol')
                                            ->live()
                                            ->default(true)
                                            ->columnSpanFull(),
                                        TextInput::make('payment_settings.paynet.merchant_id')
                                            ->label('Paynet Merchant ID')
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.paynet.enabled')),
                                        TextInput::make('payment_settings.paynet.secret_key')
                                            ->label('Paynet Secret Key')
                                            ->password()
                                            ->visible(fn ($get) => Filament::getTenant()?->payment_account_type === 'own_account' && $get('payment_settings.paynet.enabled')),
                                    ])
                                    ->columns(2)
                                     ->visible(fn () => in_array('paynet', Filament::getTenant()?->active_payment_methods ?? ['cash', 'click', 'payme', 'uzum', 'paynet']))
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $tenant->update($this->form->getState());

            Notification::make()
                ->title('Sozlamalar muvaffaqiyatli saqlandi!')
                ->success()
                ->send();
        }
    }
}
