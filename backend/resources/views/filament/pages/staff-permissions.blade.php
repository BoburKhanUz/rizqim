<x-filament-panels::page>
    @php
        $matrix = $this->matrix();
        $defaults = $this->defaults();
        $editable = $this->canEditPermissions();
    @endphp

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Har bir rol nima qila olishini belgilang. Ega huquqlari o'zgarmaydi. Sariq belgi standartdan farq qiladigan sozlamani bildiradi.
        Filialga biriktirilgan xodim faqat o'z filialida ishlaydi.
    </p>

    <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 text-start font-semibold">Ruxsat</th>
                    @foreach ($this->roles() as $role)
                        <th class="px-3 py-2 text-center font-semibold">{{ $role->labelUz() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach ($this->permissions() as $permission)
                    <tr wire:key="perm-{{ $permission->value }}">
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ $permission->label() }}</div>
                            <div class="text-xs text-gray-400">{{ $permission->value }}</div>
                        </td>
                        @foreach ($this->roles() as $role)
                            @php
                                $on = $matrix[$role->value][$permission->value] ?? false;
                                $changed = $on !== ($defaults[$role->value][$permission->value] ?? false);
                                $locked = ! $editable || $role === \App\Enums\UserRole::OWNER || (! $on && ! $permission->isGrantable());
                            @endphp
                            <td class="px-3 py-2 text-center">
                                <button type="button"
                                    @if (! $locked) wire:click="toggle('{{ $role->value }}', '{{ $permission->value }}')" @else disabled @endif
                                    aria-label="{{ $role->labelUz() }}: {{ $permission->label() }}"
                                    @class([
                                        'inline-flex h-6 w-6 items-center justify-center rounded-md ring-1',
                                        'bg-primary-600 text-white ring-primary-600' => $on,
                                        'bg-white ring-gray-300 dark:bg-white/5 dark:ring-white/20' => ! $on,
                                        'opacity-50 cursor-not-allowed' => $locked,
                                        'outline outline-2 outline-offset-2 outline-warning-500' => $changed,
                                    ])>
                                    @if ($on) ✓ @endif
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
