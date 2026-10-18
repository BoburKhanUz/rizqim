<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-2 mb-4">
            <x-heroicon-m-bolt class="text-warning-500" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; display: inline-block;" />
            <h2 class="text-lg font-bold">Live Activity Feed</h2>
        </div>
        
        <div class="space-y-4">
            @foreach($this->getActivities() as $activity)
                <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <div class="w-3 h-3 rounded-full mt-1.5 shadow-sm {{ match($activity->status->value ?? $activity->status) {
                            'new' => 'bg-gray-400',
                            'accepted' => 'bg-blue-500 shadow-blue-500/50',
                            'preparing' => 'bg-orange-500 shadow-orange-500/50',
                            'delivery' => 'bg-purple-500 shadow-purple-500/50',
                            'delivered' => 'bg-green-500 shadow-green-500/50',
                            'canceled' => 'bg-red-500 shadow-red-500/50',
                            default => 'bg-gray-400'
                        } }}"></div>
                        @if(!$loop->last)
                            <div class="w-px h-full bg-gray-200 dark:bg-gray-800 mt-2"></div>
                        @endif
                    </div>
                    <div class="flex flex-col pb-2">
                        <div class="text-sm font-medium">
                            Order <a href="{{ route('filament.admin.resources.orders.index', ['tenant' => filament()->getTenant()]) }}?tableSearch={{ $activity->order->short_code }}" class="text-primary-500 hover:underline">#{{ $activity->order->short_code }}</a>
                            <span class="text-gray-500">is now</span>
                            <span class="font-bold {{ match($activity->status->value ?? $activity->status) {
                                'new' => 'text-gray-600 dark:text-gray-400',
                                'accepted' => 'text-blue-600 dark:text-blue-400',
                                'preparing' => 'text-orange-600 dark:text-orange-400',
                                'delivery' => 'text-purple-600 dark:text-purple-400',
                                'delivered' => 'text-green-600 dark:text-green-400',
                                'canceled' => 'text-red-600 dark:text-red-400',
                                default => 'text-gray-600'
                            } }}">{{ strtoupper($activity->status->value ?? $activity->status) }}</span>
                        </div>
                        <div class="text-xs text-gray-400">{{ $activity->created_at->diffForHumans() }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
