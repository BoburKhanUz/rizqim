<x-filament-panels::page>
    @php
        $overview = $this->overview();
        $subscription = $overview['subscription'] ?? null;
        $next = $overview['next_invoice'] ?? null;
        $tz = config('billing.timezone', 'Asia/Tashkent');
        $date = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone($tz)->format('d.m.Y') : '—';
    @endphp

    <x-filament::section heading="Joriy tarif">
        @if (! $overview)
            <p class="text-sm text-gray-500 dark:text-gray-400">Do'kon tanlanmagan.</p>
        @elseif (! $subscription)
            <p class="text-sm">
                Do'kon tarif rejasiga ulanmagan: onlayn buyurtmalardan <b>{{ rtrim(rtrim(number_format($overview['commission_rate'], 2, '.', ''), '0'), '.') }}%</b>
                komissiya olinadi, oylik to'lov yo'q.
            </p>
        @else
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500 dark:text-gray-400">Tarif</dt><dd class="font-semibold">{{ $subscription['plan']['name'] }}</dd><dd class="text-xs text-gray-500">{{ $subscription['plan']['summary'] }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Holati</dt><dd class="font-semibold">{{ $subscription['status_label'] }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Joriy davr</dt><dd>{{ $date($subscription['current_period']['start']) }} — {{ $date($subscription['current_period']['end']) }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Komissiya</dt><dd>{{ rtrim(rtrim(number_format($overview['commission_rate'], 2, '.', ''), '0'), '.') }}%</dd></div>
                @if ($subscription['trial_ends_at'])
                    <div><dt class="text-gray-500 dark:text-gray-400">Sinov davri tugashi</dt><dd>{{ $date($subscription['trial_ends_at']) }}</dd></div>
                @endif
                @if ($subscription['next_plan'])
                    <div><dt class="text-gray-500 dark:text-gray-400">Keyingi oydan</dt><dd>{{ $subscription['next_plan']['name'] }}</dd></div>
                @elseif ($subscription['cancel_at_period_end'])
                    <div><dt class="text-gray-500 dark:text-gray-400">Keyingi oydan</dt><dd>Obuna tugaydi</dd></div>
                @endif
                <div><dt class="text-gray-500 dark:text-gray-400">Terminallar</dt><dd>Hozir faol: {{ $overview['terminals']['active_now'] }}, shu davrda hisoblangan: {{ $overview['terminals']['counted'] }}</dd></div>
                @if ($next)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Keyingi hisob-faktura ({{ $next['issued_on'] }})</dt>
                        <dd class="font-semibold">{{ $next['trial'] ? "Sinov davri — to'lov yo'q" : $this->uzs($next['amount']) }}</dd>
                        @foreach ($next['lines'] as $line)
                            <dd class="text-xs text-gray-500">{{ $line['label'] }}: {{ $this->uzs($line['amount']) }}</dd>
                        @endforeach
                    </div>
                @endif
            </dl>
        @endif
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
