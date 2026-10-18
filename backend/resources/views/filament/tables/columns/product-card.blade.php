@php
    $record = $getRecord();
    $totalStock = $record->variants()->sum('stock');
    $stockState = $totalStock == 0 ? 'Out of stock' : ($totalStock <= 10 ? 'Low stock' : 'In stock');
    $stockColor = match($stockState) {
        'In stock' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
        'Low stock' => 'text-amber-400 bg-amber-500/10 border-amber-500/20 stock-glow-yellow',
        'Out of stock' => 'text-rose-400 bg-rose-500/10 border-rose-500/20 stock-border-red',
    };
    $imageUrl = $record->image ? Storage::url($record->image) : 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&background=18181b&color=fff';
@endphp

<div class="flex flex-col h-full w-full">
    <!-- Image Header -->
    <div class="relative w-full h-[220px] overflow-hidden rounded-t-xl bg-gray-900 border-b border-white/5">
        <img src="{{ $imageUrl }}" alt="{{ $record->name }}" class="w-full h-full object-cover product-image-zoom">
        
        <!-- Top Badges -->
        <div class="absolute top-3 left-3 flex gap-2">
            @if($record->sort_order === 1)
                <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-orange-500/20 text-orange-400 border border-orange-500/30 backdrop-blur-md shadow-lg">
                    🔥 Bestseller
                </span>
            @endif
        </div>
        
        <div class="absolute top-3 right-3 flex gap-2">
            <span class="px-2.5 py-1 rounded-md text-xs font-bold backdrop-blur-md shadow-lg border {{ $stockColor }}">
                {{ $stockState }}
            </span>
        </div>
    </div>

    <!-- Body -->
    <div class="flex flex-col p-5 gap-3">
        <div>
            <h3 class="text-xl font-bold text-white mb-1 tracking-tight">{{ $record->name }}</h3>
            <p class="text-sm font-medium text-gray-400">{{ $record->category?->name ?? 'Uncategorized' }}</p>
        </div>
        
        <!-- Footer Info -->
        <div class="flex items-center justify-between mt-auto pt-3 border-t border-white/5">
            <div class="flex items-center gap-2">
                <span class="text-gray-500 flex items-center gap-1.5 text-sm font-medium">
                    <x-heroicon-o-swatch class="w-4 h-4"/>
                    {{ $record->variants()->count() }} variants
                </span>
            </div>
            <div>
                @if($record->is_active)
                    <span class="text-emerald-400 flex items-center gap-1.5 text-sm font-bold">
                        <x-heroicon-m-check-circle class="w-5 h-5"/>
                        Active
                    </span>
                @else
                    <span class="text-gray-500 flex items-center gap-1.5 text-sm font-bold">
                        <x-heroicon-m-x-circle class="w-5 h-5"/>
                        Inactive
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
