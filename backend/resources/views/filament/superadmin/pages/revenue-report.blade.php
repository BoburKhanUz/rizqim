<x-filament-panels::page>
    @php
        $months = $this->monthOptions();
        $monthly = $this->monthlyRows();
        $stores = $this->storeRows();
        $sum = fn (array $rows, string $key) => \App\Support\Money::sum(...array_column($rows, $key));
    @endphp

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Komissiya — shu oyda yetkazilgan (qaytarilmagan) buyurtmalar komissiyasi. Obuna — shu oy uchun chiqarilgan,
        bekor qilinmagan hisob-fakturalar. Oylar Toshkent vaqti bo'yicha.
    </p>

    <x-filament::section heading="Oylar bo'yicha">
        <div class="flex flex-wrap gap-4 mb-4 text-sm">
            <label class="flex items-center gap-2">Dan
                <select wire:model.live="from" class="rounded-lg border-gray-300 dark:bg-white/5 dark:border-white/10">
                    @foreach ($months as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-center gap-2">Gacha
                <select wire:model.live="to" class="rounded-lg border-gray-300 dark:bg-white/5 dark:border-white/10">
                    @foreach ($months as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-start">Oy</th>
                        <th class="px-3 py-2 text-end">Komissiya</th>
                        <th class="px-3 py-2 text-end">Obuna</th>
                        <th class="px-3 py-2 text-end">Jami</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($monthly as $row)
                        <tr wire:key="month-{{ $row['period'] }}">
                            <td class="px-3 py-2">
                                <button type="button" class="text-primary-600 hover:underline" wire:click="$set('period', '{{ $row['period'] }}')">{{ $row['period'] }}</button>
                            </td>
                            <td class="px-3 py-2 text-end">{{ $this->uzs($row['commission']) }}</td>
                            <td class="px-3 py-2 text-end">{{ $this->uzs($row['subscription']) }}</td>
                            <td class="px-3 py-2 text-end font-semibold">{{ $this->uzs($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="font-semibold">
                    <tr>
                        <td class="px-3 py-2">Jami</td>
                        <td class="px-3 py-2 text-end">{{ $this->uzs($sum($monthly, 'commission')) }}</td>
                        <td class="px-3 py-2 text-end">{{ $this->uzs($sum($monthly, 'subscription')) }}</td>
                        <td class="px-3 py-2 text-end">{{ $this->uzs($sum($monthly, 'total')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Restoranlar bo'yicha">
        <div class="flex flex-wrap gap-4 mb-4 text-sm">
            <label class="flex items-center gap-2">Oy
                <select wire:model.live="period" class="rounded-lg border-gray-300 dark:bg-white/5 dark:border-white/10">
                    @foreach ($months as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($stores === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">Bu oyda daromad yo'q.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-2 text-start">Restoran</th>
                            <th class="px-3 py-2 text-end">Komissiya</th>
                            <th class="px-3 py-2 text-end">Obuna</th>
                            <th class="px-3 py-2 text-end">Jami</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($stores as $row)
                            <tr wire:key="store-{{ $row['store_id'] }}">
                                <td class="px-3 py-2">{{ $row['store_name'] }}</td>
                                <td class="px-3 py-2 text-end">{{ $this->uzs($row['commission']) }}</td>
                                <td class="px-3 py-2 text-end">{{ $this->uzs($row['subscription']) }}</td>
                                <td class="px-3 py-2 text-end font-semibold">{{ $this->uzs($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="font-semibold">
                        <tr>
                            <td class="px-3 py-2">Jami</td>
                            <td class="px-3 py-2 text-end">{{ $this->uzs($sum($stores, 'commission')) }}</td>
                            <td class="px-3 py-2 text-end">{{ $this->uzs($sum($stores, 'subscription')) }}</td>
                            <td class="px-3 py-2 text-end">{{ $this->uzs($sum($stores, 'total')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
