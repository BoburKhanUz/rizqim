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
use App\Models\PaymentProvider;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Filament\Notifications\Notification;
use App\Filament\Support\StorePanelScope;

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
                    $store = StorePanelScope::store();
                    if (!$store) return;

                    $amount = Money::of($data['amount']);
                    $provider = $data['provider'];

                    $topupRequest = StoreTopupRequest::create([
                        'store_id' => $store->id,
                        'amount' => $amount->toString(),
                        'provider' => $provider,
                        'status' => 'pending',
                    ]);

                    $paymentUrl = '';
                    $returnUrl = url()->current();

                    if ($provider === 'click') {
                        $serviceId = PaymentProvider::platformCredential('click', 'service_id');
                        $merchantId = PaymentProvider::platformCredential('click', 'merchant_id');

                        $paymentUrl = "https://my.click.uz/services/pay" .
                               "?service_id={$serviceId}" .
                               "&merchant_id={$merchantId}" .
                               "&amount={$amount->toFloat()}" .
                               "&transaction_param=topup_{$topupRequest->uuid}" .
                               "&return_url=" . urlencode($returnUrl);
                    } elseif ($provider === 'payme') {
                        $merchantId = PaymentProvider::platformCredential('payme', 'merchant_id');

                        $tiyinAmount = $amount->toTiyin();
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
                    $store = StorePanelScope::store();
                    $minReserve = $store ? (float) ($store->min_payout_reserve ?? 100000) : 100000;
                    $maxWithdrawal = $store ? app(LedgerService::class)->availableForPayout($store)->toFloat() : 0.0;

                    return [
                        TextInput::make('amount')
                            ->label('Yechib Olinadigan Summa (UZS)')
                            ->numeric()
                            ->required()
                            ->minValue(1000)
                            ->maxValue($maxWithdrawal)
                            ->helperText("Maksimal yechib olish mumkin bo'lgan summa: " . number_format($maxWithdrawal) . " UZS (kutilayotgan so'rovlar hisobga olingan; eng kamida " . number_format($minReserve) . " UZS hamyon qoldig'i qolishi shart).")
                            ->default($maxWithdrawal),
                        TextInput::make('card_number')
                            ->label('Karta Raqami (8600..., 9860...)')
                            ->required()
                            ->placeholder('8600123456789012')
                            ->maxLength(19)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                if (!preg_match('/^\d{16}$/', preg_replace('/\s+/', '', (string) $value))) {
                                    $fail('Karta raqami 16 ta raqamdan iborat bo\'lishi kerak.');
                                }
                            }),
                        TextInput::make('notes')
                            ->label('Izoh (Qo\'shimcha ma\'lumotlar)')
                            ->maxLength(255),
                    ];
                })
                ->action(function (array $data) {
                    $store = StorePanelScope::store();
                    if (!$store) return;

                    // Same locked check as the API: wallet minus pending payouts minus the reserve.
                    try {
                        app(LedgerService::class)->requestPayout(
                            $store,
                            Money::of($data['amount']),
                            preg_replace('/\s+/', '', (string) $data['card_number']),
                            $data['notes'] ?? null
                        );
                    } catch (\DomainException $e) {
                        Notification::make()
                            ->title('Xatolik')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

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
        $store = StorePanelScope::store();
        if (!$store) return true;

        // Below the 1,000 UZS minimum request there is nothing left to withdraw.
        return app(LedgerService::class)->availableForPayout($store)->isLessThan(1000);
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
