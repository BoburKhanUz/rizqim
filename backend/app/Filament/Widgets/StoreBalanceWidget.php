<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use App\Services\Finance\LedgerService;
use App\Support\Money;

class StoreBalanceWidget extends BaseWidget
{
    protected static ?int $sort = -10;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, [
            \App\Enums\UserRole::OWNER,
            \App\Enums\UserRole::SUPER_ADMIN,
        ]);
    }

    protected function getStats(): array
    {
        $store = Filament::getTenant();
        if (!$store) {
            return [];
        }

        $debt = Money::of($store->debt_balance);
        $walletBalance = Money::of($store->wallet_balance);
        $minReserve = Money::of($store->min_payout_reserve ?? 100000);
        $creditLimit = Money::of($store->credit_limit);
        // The figure the payout form allows: pending requests and the reserve already taken off
        $maxWithdrawal = app(LedgerService::class)->availableForPayout($store);
        $canWithdraw = $maxWithdrawal->isGreaterThanOrEqualTo(1000);

        $balanceLabel = $debt->isPositive() ? 'Qarz Balansi (Ledger)' : 'Oldindan To\'lov (Ledger)';
        $balanceColor = $debt->isPositive() ? 'danger' : 'success';
        $balanceDesc = $debt->isPositive() ? '⚠️ Platformaga komissiya qarzi bor' : '✅ Hisobingiz ijobiy';

        return [
            Stat::make($balanceLabel, number_format($debt->abs()->toFloat()) . ' UZS')
                ->description("Limit: " . number_format($creditLimit->toFloat()) . " UZS | " . $balanceDesc)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($balanceColor),

            Stat::make('Hamyon (Wallet)', number_format($walletBalance->toFloat()) . ' UZS')
                ->description("Minimal zaxira qoldig'i: " . number_format($minReserve->toFloat()) . " UZS")
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),

            Stat::make('Yechib Olinishi Mumkin', number_format($maxWithdrawal->toFloat()) . ' UZS')
                ->description($canWithdraw ? '✅ Yechishga ruxsat etilgan' : '❌ Mablag\' yetarli emas')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color($canWithdraw ? 'success' : 'gray'),
        ];
    }
}
