<x-filament-panels::page>
    @if(method_exists($this, 'table'))
        {{ $this->table }}
    @endif
</x-filament-panels::page>
