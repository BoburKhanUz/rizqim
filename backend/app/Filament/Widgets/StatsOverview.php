<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        $today = Carbon::today();
        
        $todayRevenue = Order::whereDate('created_at', $today)
            ->whereIn('status', ['accepted', 'preparing', 'delivery', 'delivered'])
            ->sum('total_price');
            
        $ordersToday = Order::whereDate('created_at', $today)->count();
            
        $activeOrdersCount = Order::whereIn('status', ['new', 'accepted', 'preparing', 'delivery'])->count();
            
        $avgCheck = $ordersToday > 0 ? $todayRevenue / $ordersToday : 0;

        $countUp = function($value, $suffix = '') {
            if ($value == 0) return '0 ' . $suffix;
            return new \Illuminate\Support\HtmlString(
                '<span x-data="{ count: 0 }" x-init="
                    let end = '.$value.'; 
                    let duration = 1500; 
                    let start = 0; 
                    let increment = end / (duration / 16);
                    let timer = setInterval(() => { 
                        start += increment; 
                        if (start >= end) { 
                            count = end; 
                            clearInterval(timer); 
                        } else { 
                            count = Math.floor(start); 
                        } 
                    }, 16);
                " x-text="(count.toLocaleString(\'uz-UZ\').replace(/,/g, \' \')) + \' '.$suffix.'\'">0 '.$suffix.'</span>'
            );
        };

        return [
            Stat::make('Revenue Today', $countUp($todayRevenue, 'UZS'))
                ->description('Total approved sales today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),
                
            Stat::make('Orders Today', $countUp($ordersToday))
                ->description('Total orders created today')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart([2, 5, 3, 8, 4, 9, 12])
                ->color('primary'),
                
            Stat::make('Active Orders', $countUp($activeOrdersCount))
                ->description('Orders currently in progress')
                ->descriptionIcon('heroicon-m-fire')
                ->color('warning'),
                
            Stat::make('Average Check', $countUp($avgCheck, 'UZS'))
                ->description('Average order value today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
        ];
    }
}
