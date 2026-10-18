<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Models\Complaint;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalStores = Store::count();
        $totalUsers = User::count();
        $totalOrders = Order::count();
        $pendingComplaints = Complaint::whereIn('status', ['pending', 'in_review'])->count();

        return [
            Stat::make('Jami Restoranlar', $totalStores)
                ->description('Tizimga ulangan jami restoranlar')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),

            Stat::make('Jami Foydalanuvchilar', $totalUsers)
                ->description('Mijozlar, kuryerlar va xodimlar')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Jami Buyurtmalar', $totalOrders)
                ->description('Platforma orqali berilgan buyurtmalar')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success'),

            Stat::make('Faol E\'tirozlar', $pendingComplaints)
                ->description('Hal qilinishi kutilayotgan e\'tirozlar')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color($pendingComplaints > 0 ? 'danger' : 'gray'),
        ];
    }
}
