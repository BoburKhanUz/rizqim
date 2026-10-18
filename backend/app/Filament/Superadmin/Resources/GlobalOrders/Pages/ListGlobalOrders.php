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

    public function getTabs(): array
    {
        $storeId = auth()->user()->store_id;

        return [
            'active' => Tab::make('Active Queue')
                ->modifyQueryUsing(fn ($query) => $query->where('store_id', $storeId)->whereNotIn('status', ['delivered', 'canceled']))
                ->badge(Order::query()->where('store_id', $storeId)->whereNotIn('status', ['delivered', 'canceled'])->count())
                ->badgeColor('primary'),
                
            'new' => Tab::make('1. Awaiting Accept')
                ->modifyQueryUsing(fn ($query) => $query->where('store_id', $storeId)->where('status', 'new'))
                ->badge(Order::query()->where('store_id', $storeId)->where('status', 'new')->count())
                ->badgeColor(fn () => Order::query()->where('store_id', $storeId)->where('status', 'new')->count() > 0 ? 'danger' : 'gray'),
                
            'preparing' => Tab::make('2. Kitchen Active')
                ->modifyQueryUsing(fn ($query) => $query->where('store_id', $storeId)->whereIn('status', ['accepted', 'preparing']))
                ->badge(Order::query()->where('store_id', $storeId)->whereIn('status', ['accepted', 'preparing'])->count())
                ->badgeColor('warning'),
                
            'delivering' => Tab::make('3. En Route')
                ->modifyQueryUsing(fn ($query) => $query->where('store_id', $storeId)->where('status', 'delivery'))
                ->badge(Order::query()->where('store_id', $storeId)->where('status', 'delivery')->count())
                ->badgeColor('info'),
                
            'completed' => Tab::make('4. Archive')
                ->modifyQueryUsing(fn ($query) => $query->where('store_id', $storeId)->whereIn('status', ['delivered', 'canceled']))
                ->badge(Order::query()->where('store_id', $storeId)->whereIn('status', ['delivered', 'canceled'])->count())
                ->badgeColor('success'),
        ];
    }
}
