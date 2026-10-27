<?php

namespace App\Services\Admin\Attention;

use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * P4-13: "Bugun → Diqqat talab qiladi". Asks every source tagged AttentionSource::TAG (each section registers its own
 * in its service provider) that the member has the permission for, and returns one group per source that has
 * anything: critical first, then warning, then info; the larger count first within a severity. A source that fails
 * is reported and left out, so one broken section never empties the list.
 *
 * A group's `kind` and `label` come from the source when it implements DescribesAttention, else from its first row's
 * kind and the label table below; `severity` is the most urgent of its rows.
 */
class AttentionCollector
{
    private const SEVERITY_RANK = ['critical' => 0, 'warning' => 1, 'info' => 2];

    /** Labels of the kinds the stage B sections emit, for sources that do not name their group themselves. */
    private const LABELS = [
        'settlement_failed' => "Hisob-kitob xatolari",
        'payout_pending' => "Pul yechish so'rovlari",
        'topup_pending' => "To'lanmay qolgan to'ldirishlar",
        'invoice_overdue' => "Muddati o'tgan hisob-fakturalar",
        'device_offline' => "Ish vaqtida oflayn kassalar",
        'fiscal_failed' => "Yiqilgan fiskal cheklar",
        'order_unaccepted' => "Qabul qilinmagan buyurtmalar",
        'order_late' => "Kechikayotgan buyurtmalar",
        'order_late_preparation' => "Tayyorlash kechikmoqda",
        'order_late_delivery' => "Yetkazish kechikmoqda",
        'order_no_courier' => "Kuryersiz buyurtmalar",
        'alert' => "Tizim ogohlantirishlari",
    ];

    public function __construct(
        private readonly Container $container,
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * @return list<array{kind: string, label: string, count: int, severity: string, items: list<array<string, mixed>>}>
     */
    public function collect(User $member, int $limit): array
    {
        $groups = [];

        foreach ($this->sources() as $source) {
            if (!$this->permissions->has($member, $source->permission())) {
                continue;
            }

            try {
                $result = $source->collect($limit);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            $items = array_slice($result['items'], 0, $limit);
            $count = max((int) $result['count'], count($items));
            if ($count === 0) {
                continue;
            }

            usort($items, fn (AttentionItem $a, AttentionItem $b) => $this->rank($a->severity) <=> $this->rank($b->severity));
            $kind = $source instanceof DescribesAttention ? $source->kind() : ($items[0]->kind ?? 'other');
            $label = $source instanceof DescribesAttention ? $source->label() : (self::LABELS[$kind] ?? $kind);
            $severity = $items !== [] ? $items[0]->severity : ($source instanceof DescribesAttention ? $source->severity() : 'info');

            $groups[] = [
                'kind' => $kind,
                'label' => $label,
                'count' => $count,
                'severity' => $severity,
                'items' => array_map(fn (AttentionItem $item) => $item->toArray(), $items),
            ];
        }

        usort($groups, fn (array $a, array $b) => [$this->rank($a['severity']), $b['count']] <=> [$this->rank($b['severity']), $a['count']]);

        return $groups;
    }

    /**
     * @return iterable<AttentionSource>
     */
    private function sources(): iterable
    {
        foreach ($this->container->tagged(AttentionSource::TAG) as $source) {
            if ($source instanceof AttentionSource) {
                yield $source;
            }
        }
    }

    private function rank(string $severity): int
    {
        return self::SEVERITY_RANK[$severity] ?? 3;
    }
}
