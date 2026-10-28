<?php

namespace App\Services\Admin\Customers;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\OrderRefundRequest;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P4-31: Mijozlar va yordam → Shubhali holatlar (read-only, admin.support.view). Three signals, each row explained in
 * words and linked to the user card; nothing is decided automatically. Every query reads one indexed time window and
 * returns at most support.suspicious.limit rows (thresholds in config/support.php):
 *
 * - cancellations: a customer whose orders were cancelled many times in the last N days (they cancelled themselves,
 *   asked the store to, or did not come for a pickup) — orders(status, created_at);
 * - promo_sharing: one promo code used from several customer accounts that share a phone number or a Telegram
 *   account — orders(created_at), promo_code_id set. The apps tie a device to one account (devices.uuid is unique and
 *   a push token moves to the last account signed in), so there is no shared-device signal to read yet;
 * - refunds: a customer whose refund requests (pending or approved) in the window add up to well above the average
 *   refund: at least `refund_factor` times the average request and at least `refund_min_amount` —
 *   order_refund_requests(status, created_at).
 */
class SuspiciousCases
{
    /**
     * @return array{generated_at: string|null, groups: list<array{kind: string, label: string, description: string, rows: list<array<string, mixed>>}>}
     */
    public function all(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'generated_at' => CustomerFormat::iso($now),
            'groups' => [
                $this->cancellations($now),
                $this->promoSharing($now),
                $this->refunds($now),
            ],
        ];
    }

    /**
     * @return array{kind: string, label: string, description: string, rows: list<array<string, mixed>>}
     */
    public function cancellations(CarbonImmutable $now): array
    {
        $days = max(1, (int) config('support.suspicious.cancellations_days', 7));
        $min = max(2, (int) config('support.suspicious.cancellations_min', 3));
        $customerReasons = [CancelReason::CUSTOMER_REQUEST->value, CancelReason::NO_SHOW->value];

        $rows = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.status', OrderStatus::CANCELED->value)
            ->where('orders.created_at', '>=', $now->subDays($days))
            ->where('users.role', UserRole::CUSTOMER->value)
            ->whereNull('users.platform_role')
            ->where(fn ($query) => $query->whereColumn('orders.cancelled_by', 'orders.user_id')->orWhereIn('orders.cancel_reason', $customerReasons))
            ->groupBy('orders.user_id')
            ->havingRaw('count(*) >= ?', [$min])
            ->selectRaw(
                'orders.user_id, count(*) as total, count(*) filter (where orders.cancelled_by = orders.user_id) as by_self, count(*) filter (where orders.cancel_reason = ?) as no_show, max(orders.created_at) as last_at',
                [CancelReason::NO_SHOW->value],
            )
            ->orderByDesc('total')->orderByDesc('last_at')
            ->limit($this->limit())
            ->get();

        $users = $this->users($rows->pluck('user_id'));

        return [
            'kind' => 'cancellations',
            'label' => "Ko'p bekor qilish",
            'description' => "Oxirgi {$days} kunda kamida {$min} ta buyurtmasi mijoz tufayli bekor qilingan mijozlar.",
            'rows' => $rows->map(function (object $row) use ($users, $days) {
                $user = $users->get((int) $row->user_id);
                $self = (int) $row->by_self;
                $noShow = (int) $row->no_show;
                $other = (int) $row->total - $self - $noShow;
                $parts = array_values(array_filter([
                    $self > 0 ? "{$self} tasini o'zi bekor qildi" : null,
                    $noShow > 0 ? "{$noShow} tasini olib ketmadi" : null,
                    $other > 0 ? "{$other} tasi uning so'rovi bilan bekor qilindi" : null,
                ]));

                return [
                    'kind' => 'cancellations',
                    'user' => $this->userBrief($user, (int) $row->user_id),
                    'accounts' => [],
                    'reason' => "{$days} kunda {$row->total} ta buyurtma bekor qilindi: " . implode(', ', $parts) . '.',
                    'metric' => (int) $row->total,
                    'since' => $this->time($row->last_at),
                    'href' => '/users/' . $row->user_id,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array{kind: string, label: string, description: string, rows: list<array<string, mixed>>}
     */
    public function promoSharing(CarbonImmutable $now): array
    {
        $days = max(1, (int) config('support.suspicious.promo_days', 30));
        $min = max(2, (int) config('support.suspicious.promo_min_accounts', 2));
        $limit = $this->limit();

        $found = [];
        foreach (['phone' => 'users.phone', 'telegram' => 'users.telegram_id'] as $by => $column) {
            $groups = DB::table('orders')
                ->join('users', 'users.id', '=', 'orders.user_id')
                ->join('promo_codes', 'promo_codes.id', '=', 'orders.promo_code_id')
                ->whereNotNull('orders.promo_code_id')
                ->where('orders.created_at', '>=', $now->subDays($days))
                ->whereNull('users.platform_role')
                ->whereNotNull($column)->where($column, '!=', '')
                ->when($by === 'telegram', fn ($query) => $query->where('users.telegram_id', 'not like', 'temp\_%'))
                // Older rows may lack the plus: one number either way
                ->groupBy('orders.promo_code_id', 'promo_codes.code')
                ->groupByRaw($by === 'phone' ? "ltrim(users.phone, '+')" : 'users.telegram_id')
                ->havingRaw('count(distinct orders.user_id) >= ?', [$min])
                ->selectRaw('orders.promo_code_id, promo_codes.code, count(distinct orders.user_id) as accounts, count(*) as uses, max(orders.created_at) as last_at, array_agg(distinct orders.user_id) as user_ids')
                ->orderByDesc('accounts')->orderByDesc('last_at')
                ->limit($limit)
                ->get();

            foreach ($groups as $group) {
                $found[] = ['by' => $by, 'group' => $group, 'ids' => $this->intArray($group->user_ids)];
            }
        }

        usort($found, fn (array $a, array $b) => [(int) $b['group']->accounts, (string) $b['group']->last_at] <=> [(int) $a['group']->accounts, (string) $a['group']->last_at]);
        $found = array_slice($found, 0, $limit);
        $users = $this->users(collect($found)->flatMap(fn (array $item) => $item['ids']));

        return [
            'kind' => 'promo_sharing',
            'label' => "Promo-kodni ko'p hisobdan ishlatish",
            'description' => "Oxirgi {$days} kunda bitta promo-kod bir xil telefon raqami yoki Telegram akkauntiga ega {$min} va undan ko'p hisobdan ishlatilgan.",
            'rows' => array_map(function (array $item) use ($users, $days) {
                $group = $item['group'];
                $ids = $item['ids'];
                sort($ids);
                $same = $item['by'] === 'phone' ? 'bir xil telefon raqamli' : 'bitta Telegram akkauntiga bog\'langan';

                return [
                    'kind' => 'promo_sharing',
                    'user' => $this->userBrief($users->get($ids[0] ?? 0), $ids[0] ?? 0),
                    'accounts' => array_map(fn (int $id) => $this->userBrief($users->get($id), $id), $ids),
                    'promo_code' => (string) $group->code,
                    'shared_by' => $item['by'],
                    'reason' => "{$group->code} kodi {$days} kunda {$same} {$group->accounts} ta hisobdan {$group->uses} marta ishlatildi.",
                    'metric' => (int) $group->accounts,
                    'since' => $this->time($group->last_at),
                    'href' => '/users/' . ($ids[0] ?? 0),
                ];
            }, $found),
        ];
    }

    /**
     * @return array{kind: string, label: string, description: string, rows: list<array<string, mixed>>}
     */
    public function refunds(CarbonImmutable $now): array
    {
        $days = max(1, (int) config('support.suspicious.refund_days', 30));
        $factor = max(1, (float) config('support.suspicious.refund_factor', 3));
        $minAmount = Money::of((string) config('support.suspicious.refund_min_amount', '100000'));
        $since = $now->subDays($days);
        $statuses = [OrderRefundRequest::PENDING, OrderRefundRequest::APPROVED];

        $average = DB::table('order_refund_requests')
            ->whereIn('status', $statuses)->where('created_at', '>=', $since)
            ->selectRaw('round(avg(amount), 2) as average')
            ->first()?->average;
        $average = Money::of(is_numeric($average) ? (string) $average : '0');
        $threshold = Money::max($minAmount, $average->times((string) $factor));

        $rows = $average->isPositive() ? DB::table('order_refund_requests')
            ->join('orders', 'orders.id', '=', 'order_refund_requests.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->whereIn('order_refund_requests.status', $statuses)
            ->where('order_refund_requests.created_at', '>=', $since)
            ->whereNull('users.platform_role')
            ->groupBy('orders.user_id')
            ->havingRaw('sum(order_refund_requests.amount) >= ?', [$threshold->toString()])
            ->selectRaw('orders.user_id, sum(order_refund_requests.amount) as total, count(*) as requests, max(order_refund_requests.created_at) as last_at')
            ->orderByDesc('total')
            ->limit($this->limit())
            ->get() : collect();

        $users = $this->users($rows->pluck('user_id'));
        $averageWords = CustomerFormat::words($average);

        return [
            'kind' => 'refunds',
            'label' => "O'rtachadan ko'p qaytarish",
            'description' => "Oxirgi {$days} kunda qaytarish so'rovlari jami o'rtacha qaytarishdan kamida " . rtrim(rtrim(number_format($factor, 1, '.', ''), '0'), '.') . ' baravar ko\'p va ' . CustomerFormat::words($minAmount) . "dan ortiq bo'lgan mijozlar.",
            'rows' => $rows->map(function (object $row) use ($users, $days, $average, $averageWords) {
                $total = Money::of((string) $row->total);
                $times = $average->isPositive() ? round($total->toFloat() / $average->toFloat(), 1) : null;

                return [
                    'kind' => 'refunds',
                    'user' => $this->userBrief($users->get((int) $row->user_id), (int) $row->user_id),
                    'accounts' => [],
                    'reason' => "{$days} kunda {$row->requests} ta qaytarish, jami " . CustomerFormat::words($total) . " — o'rtacha qaytarishdan ({$averageWords})" . ($times !== null ? ' ' . rtrim(rtrim(number_format($times, 1, '.', ''), '0'), '.') . ' baravar ko\'p' : '') . '.',
                    'metric' => $total->toString(),
                    'since' => $this->time($row->last_at),
                    'href' => '/users/' . $row->user_id,
                ];
            })->values()->all(),
        ];
    }

    private function limit(): int
    {
        return max(1, min(200, (int) config('support.suspicious.limit', 50)));
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<int, User>
     */
    private function users(Collection $ids): Collection
    {
        $ids = $ids->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()->whereIn('id', $ids)->get(['id', 'name', 'role', 'blocked_at'])->keyBy('id');
    }

    /**
     * @return array{id: int, name: string, role_label: string|null, blocked: bool}
     */
    private function userBrief(?User $user, int $id): array
    {
        return [
            'id' => $id,
            'name' => $user ? (string) $user->name : '—',
            'role_label' => $user?->role->labelUz(),
            'blocked' => $user?->isBlocked() ?? false,
        ];
    }

    private function time(mixed $value): ?string
    {
        return $value !== null ? CustomerFormat::iso(CarbonImmutable::parse((string) $value)) : null;
    }

    /**
     * A PostgreSQL integer array ("{3,5,9}") as ints.
     *
     * @return list<int>
     */
    private function intArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('intval', $value));
        }

        return array_values(array_filter(array_map('intval', explode(',', trim((string) $value, '{}')))));
    }
}
