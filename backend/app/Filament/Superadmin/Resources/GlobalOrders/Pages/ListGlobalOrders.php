<?php

namespace App\Filament\Superadmin\Resources\GlobalOrders\Pages;

use App\Filament\Superadmin\Resources\GlobalOrders\GlobalOrderResource;
use App\Models\Order;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListGlobalOrders extends ListRecords
{
    protected static string $resource = GlobalOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    // Every status belongs to exactly one stage tab, so no order drops out of the list (rejected and refunded included)
    private const FINAL = ['delivered', 'canceled', 'rejected', 'refunded'];
    private const KITCHEN = ['accepted', 'preparing', 'ready'];
    private const EN_ROUTE = ['courier_assigned', 'on_the_way', 'delivery'];

    public function getTabs(): array
    {
        // The superadmin works across all stores and has no store of their own (store_id is null), so no tab is scoped by store
        return [
            'active' => Tab::make('Active Queue')
                ->modifyQueryUsing(fn ($query) => $query->whereNotIn('status', self::FINAL))
                ->badge(Order::query()->whereNotIn('status', self::FINAL)->count())
                ->badgeColor('primary'),
                
            'new' => Tab::make('1. Awaiting Accept')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'new'))
                ->badge(Order::query()->where('status', 'new')->count())
                ->badgeColor(fn () => Order::query()->where('status', 'new')->count() > 0 ? 'danger' : 'gray'),
                
            'preparing' => Tab::make('2. Kitchen Active')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', self::KITCHEN))
                ->badge(Order::query()->whereIn('status', self::KITCHEN)->count())
                ->badgeColor('warning'),
                
            'delivering' => Tab::make('3. En Route')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', self::EN_ROUTE))
                ->badge(Order::query()->whereIn('status', self::EN_ROUTE)->count())
                ->badgeColor('info'),
                
            'completed' => Tab::make('4. Archive')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', self::FINAL))
                ->badge(Order::query()->whereIn('status', self::FINAL)->count())
                ->badgeColor('success'),
        ];
    }
}
