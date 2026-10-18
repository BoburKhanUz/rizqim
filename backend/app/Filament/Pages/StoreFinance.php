<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\StoreBalanceWidget;
use App\Filament\Widgets\StoreLedgerTransactionsWidget;
use App\Filament\Widgets\StoreWalletTransactionsWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\StoreTopupRequest;
use App\Models\PayoutRequest;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;

class StoreFinance extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationLabel = 'Moliya va Balans';
    
    protected static ?string $title = 'Moliya va Balans boshqaruvi';
    
    protected string $view = 'filament.pages.ui-page';
    
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, [
            \App\Enums\UserRole::OWNER,
            \App\Enums\UserRole::SUPER_ADMIN,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('topUp')
                ->label('Kartadan To\'ldirish')
                ->color('success')
                ->icon('heroicon-m-arrow-path')
                ->modalHeading('Balansni To\'ldirish')
                ->modalDescription('Qarz balansini kamaytirish yoki oldindan to\'lov kiritish uchun Click yoki Payme orqali to\'lov qiling.')
                ->form([
                    TextInput::make('amount')
                        ->label('To\'lov Summasi (UZS)')
                        ->numeric()
                        ->required()
                        ->minValue(1000)
                        ->default(100000),
                    Select::make('provider')
                        ->label('To\'lov Tizimi')
                        ->options([
                            'click' => 'Click',
                            'payme' => 'Payme',
                        ])
                        ->required()
                        ->default('click'),
                ])
                ->action(function (array $data) {
                    $store = Filament::getTenant();
                    if (!$store) return;

                    $amount = (float) $data['amount'];
                    $provider = $data['provider'];

                    $topupRequest = StoreTopupRequest::create([
                        'store_id' => $store->id,
                        'amount' => $amount,
                        'provider' => $provider,
                        'status' => 'pending',
                    ]);

                    $paymentUrl = '';
                    $returnUrl = url()->current();

                    if ($provider === 'click') {
                        $providerModel = \App\Models\PaymentProvider::where('slug', 'click')->first();
                        $clickConfig = $providerModel?->credentials ?? [];
                        $serviceId = $clickConfig['service_id'] ?? env('CLICK_SERVICE_ID', '32104');
                        $merchantId = $clickConfig['merchant_id'] ?? env('CLICK_MERCHANT_ID', '24185');

                        $paymentUrl = "https://my.click.uz/services/pay" .
                               "?service_id={$serviceId}" .
                               "&merchant_id={$merchantId}" .
                               "&amount={$amount}" .
                               "&transaction_param=topup_{$topupRequest->uuid}" .
                               "&return_url=" . urlencode($returnUrl);
                    } elseif ($provider === 'payme') {
                        $providerModel = \App\Models\PaymentProvider::where('slug', 'payme')->first();
                        $paymeConfig = $providerModel?->credentials ?? [];
                        $merchantId = $paymeConfig['merchant_id'] ?? env('PAYME_MERCHANT_ID', '65eb2b988f01b97b0a7c4912');
                        
                        $tiyinAmount = $amount * 100;
                        $params = "m={$merchantId};ac.order_id=topup_{$topupRequest->uuid};a={$tiyinAmount};c=" . urlencode($returnUrl);
                        $paymentUrl = "https://checkout.paycom.uz/" . base64_encode($params);
                    }

                    $this->redirect($paymentUrl);
                }),

            Action::make('requestPayout')
                ->label('Hamyondan Pul Yechish')
                ->color('primary')
                ->icon('heroicon-m-banknotes')
                ->modalHeading('Hamyondan Pul Yechish')
                ->modalDescription('Buyurtmalardan yig\'ilgan hamyon balansingizdan pul yechish uchun so\'rov yuboring.')
                ->disabled(fn () => $this->isPayoutDisabled())
                ->form(function () {
                    $store = Filament::getTenant();
                    $minReserve = $store ? (float) ($store->min_payout_reserve ?? 100000) : 100000;
                    $walletBalance = $store ? (float) $store->wallet_balance : 0;
                    $maxWithdrawal = max(0.00, $walletBalance - $minReserve);

                    return [
                        TextInput::make('amount')
                            ->label('Yechib Olinadigan Summa (UZS)')
                            ->numeric()
                            ->required()
                            ->minValue(1000)
                            ->maxValue($maxWithdrawal)
                            ->helperText("Maksimal yechib olish mumkin bo'lgan summa: " . number_format($maxWithdrawal) . " UZS (Eng kamida " . number_format($minReserve) . " UZS hamyon qoldig'i qolishi shart).")
                            ->default($maxWithdrawal),
                        TextInput::make('card_number')
                            ->label('Karta Raqami (8600..., 9860...)')
                            ->required()
                            ->placeholder('8600123456789012')
                            ->minLength(16)
                            ->maxLength(19),
                        TextInput::make('notes')
                            ->label('Izoh (Qo\'shimcha ma\'lumotlar)')
                            ->maxLength(255),
                    ];
                })
                ->action(function (array $data) {
                    $store = Filament::getTenant();
                    if (!$store) return;

                    $amount = (float) $data['amount'];
                    $minReserve = (float) ($store->min_payout_reserve ?? 100000.00);

                    if ($store->wallet_balance < $minReserve) {
                        Notification::make()
                            ->title('Xatolik')
                            ->body('Hamyonda eng kamida ' . number_format($minReserve) . ' UZS bo\'lysis kerak.')
                            ->danger()
                            ->send();
                        return;
                    }

                    if (($store->wallet_balance - $amount) < $minReserve) {
                        Notification::make()
                            ->title('Xatolik')
                            ->body('Hamyonda yechib olingandan so\'ng kamida ' . number_format($minReserve) . ' UZS qolishi kerak.')
                            ->danger()
                            ->send();
                        return;
                    }

                    PayoutRequest::create([
                        'store_id' => $store->id,
                        'amount' => $amount,
                        'card_number' => $data['card_number'],
                        'notes' => $data['notes'] ?? null,
                        'status' => 'pending',
                    ]);

                    Notification::make()
                        ->title('So\'rov yuborildi')
                        ->body('Yechib olish so\'rovingiz qabul qilindi va SuperAdmin tomonidan ko\'rib chiqiladi.')
                        ->success()
                        ->send();

                    $this->redirect(url()->current());
                }),
        ];
    }

    protected function isPayoutDisabled(): bool
    {
        $store = Filament::getTenant();
        if (!$store) return true;
        
        $minReserve = (float) ($store->min_payout_reserve ?? 100000);
        $walletBalance = (float) $store->wallet_balance;
        
        return $walletBalance < $minReserve;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StoreBalanceWidget::class,
            StoreLedgerTransactionsWidget::class,
            StoreWalletTransactionsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 2;
    }
}
