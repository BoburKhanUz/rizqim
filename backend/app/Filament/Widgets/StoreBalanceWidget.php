<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;

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

        $balance = (float) ($store->balance ?? 0);
        $walletBalance = (float) ($store->wallet_balance ?? 0);
        $minReserve = (float) ($store->min_payout_reserve ?? 100000);
        $creditLimit = (float) ($store->credit_limit ?? 0);
        $maxWithdrawal = max(0.00, $walletBalance - $minReserve);

        $balanceLabel = $balance > 0 ? 'Qarz Balansi (Ledger)' : 'Oldindan To\'lov (Ledger)';
        $balanceColor = $balance > 0 ? 'danger' : 'success';
        $balanceDesc = $balance > 0 ? '⚠️ Platformaga komissiya qarzi bor' : '✅ Hisobingiz ijobiy';

        return [
            Stat::make($balanceLabel, number_format(abs($balance)) . ' UZS')
                ->description("Limit: " . number_format($creditLimit) . " UZS | " . $balanceDesc)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($balanceColor),

            Stat::make('Hamyon (Wallet)', number_format($walletBalance) . ' UZS')
                ->description("Minimal zaxira qoldig'i: " . number_format($minReserve) . " UZS")
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),

            Stat::make('Yechib Olinishi Mumkin', number_format($maxWithdrawal) . ' UZS')
                ->description($walletBalance >= $minReserve ? '✅ Yechishga ruxsat etilgan' : '❌ Mablag\' yetarli emas')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color($walletBalance >= $minReserve ? 'success' : 'gray'),
        ];
    }
}
