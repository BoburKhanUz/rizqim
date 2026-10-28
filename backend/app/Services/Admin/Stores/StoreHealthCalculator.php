<?php

namespace App\Services\Admin\Stores;

use App\Enums\DeviceType;
use App\Enums\StoreHealthLevel;
use App\Enums\StoreStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\StoreHealthScore;
use App\Models\User;
use App\Services\Admin\Today\PlatformStats;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P4-22: a store's health — `good`, `attention` (e'tibor kerak) or `churn_risk` (ketish xavfi) — and the reasons, so
 * the team sees why (docs/API.md "Ovqatxonalar", sog'liq bahosi). A handful of grouped queries per batch of stores:
 *
 * | signal | attention | churn risk |
 * |---|---|---|
 * | orders, last 14 days vs the 14 before (10+ before) | 25% fewer | 50% fewer |
 * | no orders in 14 days (a store older than 14 days that had orders) | | yes |
 * | never had an order (older than 14 days) | yes | |
 * | POS/KDS terminals silent for 3+ days | some | all of them |
 * | failed fiscal receipts, last 7 days | 1+ (10+ weigh more) | |
 * | commission debt (debt − unpaid subscription invoices) over the credit limit | | yes |
 * | an unpaid invoice past its due date | 1+ day | 7+ days |
 * | complaints, last 30 days | 3+ | 6+ |
 * | owner and managers: last sign-in or app use | 14+ days | 30+ days (a store older than 30 days) |
 * | status suspended, payment required or finance hold | yes | |
 *
 * Score: 100 minus each reason's points (never below 0). Level: churn_risk with a churn reason or a score under 50,
 * attention with any other reason or a score under 80, else good. Only stores past onboarding (status active,
 * payment_required, finance_hold, suspended) get trend and activity reasons; a store still being onboarded is `good`
 * unless debt or devices say otherwise.
 */
class StoreHealthCalculator
{
    public const TREND_DAYS = 14;

    public const TREND_MIN_ORDERS = 10;

    public const DEVICE_SILENT_DAYS = 3;

    public const FISCAL_DAYS = 7;

    public const COMPLAINT_DAYS = 30;

    private const LIVE_STATUSES = [StoreStatus::ACTIVE, StoreStatus::PAYMENT_REQUIRED, StoreStatus::FINANCE_HOLD, StoreStatus::SUSPENDED];

    /**
     * Compute and save the health of the given stores (all stores when null), 500 at a time. Returns how many rows
     * were written.
     *
     * @param  list<int>|null  $storeIds
     */
    public function refresh(?array $storeIds = null, ?CarbonImmutable $now = null): int
    {
        $now ??= PlatformStats::now();
        $written = 0;

        // subscription_debt too: the credit limit counts the commission debt only (P4-21, Store::commissionDebt)
        $query = Store::query()->select(['id', 'status', 'created_at', 'debt_balance', 'subscription_debt', 'credit_limit'])->orderBy('id');
        if ($storeIds !== null) {
            $query->whereIn('id', $storeIds);
        }

        $query->chunkById(500, function ($stores) use ($now, &$written) {
            $rows = [];
            foreach ($this->compute($stores->all(), $now) as $storeId => $health) {
                $rows[] = [
                    'store_id' => $storeId,
                    'level' => $health['level']->value,
                    'score' => $health['score'],
                    'reasons' => json_encode($health['reasons'], JSON_UNESCAPED_UNICODE),
                    'metrics' => json_encode($health['metrics'], JSON_UNESCAPED_UNICODE),
                    'computed_at' => PlatformStats::column($now),
                ];
            }
            if ($rows !== []) {
                StoreHealthScore::query()->upsert($rows, ['store_id'], ['level', 'score', 'reasons', 'metrics', 'computed_at']);
                $written += count($rows);
            }
        });

        // Deleted stores leave the list
        if ($storeIds === null) {
            StoreHealthScore::query()->whereIn('store_id', Store::onlyTrashed()->select('id'))->delete();
        }

        return $written;
    }

    /**
     * One store, computed now and saved (the 360° card).
     *
     * @return array{level: StoreHealthLevel, score: int, reasons: list<array{key: string, level: string, points: int, label: string}>, metrics: array<string, mixed>, computed_at: string}
     */
    public function forStore(Store $store, ?CarbonImmutable $now = null): array
    {
        $now ??= PlatformStats::now();
        $this->refresh([(int) $store->id], $now);
        $health = $this->compute([$store], $now)[(int) $store->id];

        return [...$health, 'computed_at' => $now->toIso8601String()];
    }

    /**
     * @param  list<Store>  $stores
     * @return array<int, array{level: StoreHealthLevel, score: int, reasons: list<array{key: string, level: string, points: int, label: string}>, metrics: array<string, mixed>}>
     */
    public function compute(array $stores, CarbonImmutable $now): array
    {
        if ($stores === []) {
            return [];
        }

        $ids = array_map(fn (Store $store) => (int) $store->id, $stores);
        $at = fn (CarbonImmutable $time) => PlatformStats::column($time);
        $trendFrom = $now->subDays(self::TREND_DAYS);
        $prevFrom = $now->subDays(self::TREND_DAYS * 2);

        $orders = DB::table('orders')
            ->whereIn('store_id', $ids)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['canceled', 'rejected'])
            ->where('created_at', '>=', $at($prevFrom))
            ->groupBy('store_id')
            ->selectRaw('store_id, count(*) filter (where created_at >= ?) as recent, count(*) filter (where created_at < ?) as previous', [$at($trendFrom), $at($trendFrom)])
            ->get()->keyBy('store_id');

        $lastOrders = DB::table('orders')
            ->whereIn('store_id', $ids)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['canceled', 'rejected'])
            ->groupBy('store_id')
            ->selectRaw('store_id, max(created_at) as last_at')
            ->pluck('last_at', 'store_id');

        $silentBefore = $at($now->subDays(self::DEVICE_SILENT_DAYS));
        $devices = DB::table('devices')
            ->whereIn('store_id', $ids)
            ->whereNull('revoked_at')
            ->whereIn('type', array_map(fn (DeviceType $type) => $type->value, DeviceType::terminals()))
            ->groupBy('store_id')
            ->selectRaw('store_id, count(*) as terminals, count(*) filter (where coalesce(last_seen_at, created_at) < ?) as silent, max(last_seen_at) as last_seen_at', [$silentBefore])
            ->get()->keyBy('store_id');

        $fiscal = DB::table('fiscal_receipts')
            ->whereIn('store_id', $ids)
            ->where('status', 'failed')
            ->where('created_at', '>=', $at($now->subDays(self::FISCAL_DAYS)))
            ->groupBy('store_id')
            ->selectRaw('store_id, count(*) as failed')
            ->pluck('failed', 'store_id');

        $complaints = DB::table('complaints')
            ->join('orders', 'orders.id', '=', 'complaints.order_id')
            ->whereIn('orders.store_id', $ids)
            ->where('complaints.created_at', '>=', $at($now->subDays(self::COMPLAINT_DAYS)))
            ->groupBy('orders.store_id')
            ->selectRaw("orders.store_id as store_id, count(*) as total, count(*) filter (where complaints.status != 'closed') as open")
            ->get()->keyBy('store_id');

        $today = PlatformStats::dayStart($now)->format('Y-m-d');
        $overdue = DB::table('subscription_invoices')
            ->whereIn('store_id', $ids)
            ->where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->where('due_date', '<', $today)
            ->groupBy('store_id')
            ->selectRaw('store_id, min(due_date) as oldest_due, count(*) as invoices')
            ->get()->keyBy('store_id');

        $activity = DB::table('personal_access_tokens')
            ->join('users', 'users.id', '=', 'personal_access_tokens.tokenable_id')
            ->where('personal_access_tokens.tokenable_type', (new User())->getMorphClass())
            ->whereIn('users.store_id', $ids)
            ->whereIn('users.role', [UserRole::OWNER->value, UserRole::MANAGER->value])
            ->whereNull('users.deleted_at')
            ->groupBy('users.store_id')
            ->selectRaw('users.store_id as store_id, max(greatest(coalesce(personal_access_tokens.last_used_at, personal_access_tokens.created_at), personal_access_tokens.created_at)) as last_at')
            ->pluck('last_at', 'store_id');

        $result = [];
        foreach ($stores as $store) {
            $id = (int) $store->id;
            $result[$id] = $this->judge($store, $now, [
                'orders' => $orders->get($id),
                'last_order_at' => $lastOrders->get($id),
                'devices' => $devices->get($id),
                'fiscal_failed' => (int) ($fiscal->get($id) ?? 0),
                'complaints' => $complaints->get($id),
                'overdue' => $overdue->get($id),
                'last_activity_at' => $activity->get($id),
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{level: StoreHealthLevel, score: int, reasons: list<array{key: string, level: string, points: int, label: string}>, metrics: array<string, mixed>}
     */
    private function judge(Store $store, CarbonImmutable $now, array $data): array
    {
        $reasons = [];
        $add = function (string $key, StoreHealthLevel $level, int $points, string $label) use (&$reasons) {
            $reasons[] = ['key' => $key, 'level' => $level->value, 'points' => $points, 'label' => $label];
        };

        $status = $store->status;
        $live = in_array($status, self::LIVE_STATUSES, true);
        $ageDays = $store->created_at !== null ? (int) floor($store->created_at->diffInDays($now, true)) : 0;
        $recent = (int) ($data['orders']->recent ?? 0);
        $previous = (int) ($data['orders']->previous ?? 0);
        $lastOrderAt = $data['last_order_at'] !== null ? PlatformStats::fromColumn((string) $data['last_order_at']) : null;

        if ($live) {
            if ($previous >= self::TREND_MIN_ORDERS && $recent > 0) {
                $drop = (int) round((1 - $recent / $previous) * 100);
                if ($drop >= 50) {
                    $add('orders_dropped', StoreHealthLevel::CHURN_RISK, 30, "Buyurtmalar {$drop}% kamaydi: oxirgi 14 kunda {$recent}, oldingi 14 kunda {$previous}");
                } elseif ($drop >= 25) {
                    $add('orders_dropped', StoreHealthLevel::ATTENTION, 15, "Buyurtmalar {$drop}% kamaydi: oxirgi 14 kunda {$recent}, oldingi 14 kunda {$previous}");
                }
            }
            if ($recent === 0 && $ageDays > self::TREND_DAYS) {
                if ($lastOrderAt !== null) {
                    $days = (int) floor($lastOrderAt->diffInDays($now, true));
                    $add('no_orders', StoreHealthLevel::CHURN_RISK, 35, "{$days} kundan beri buyurtma yo'q");
                } else {
                    $add('no_first_order', StoreHealthLevel::ATTENTION, 10, "Hali birinchi buyurtma yo'q");
                }
            }
        }

        $devices = $data['devices'];
        $terminals = (int) ($devices->terminals ?? 0);
        $silent = (int) ($devices->silent ?? 0);
        if ($terminals > 0 && $silent > 0) {
            if ($silent === $terminals) {
                $add('devices_silent', StoreHealthLevel::CHURN_RISK, 25, $terminals === 1
                    ? 'Kassa ' . self::DEVICE_SILENT_DAYS . ' kundan beri aloqa qilmagan'
                    : "Barcha {$terminals} ta terminal " . self::DEVICE_SILENT_DAYS . ' kundan beri aloqa qilmagan');
            } else {
                $add('devices_silent', StoreHealthLevel::ATTENTION, 10, "{$silent} ta terminal (jami {$terminals}) " . self::DEVICE_SILENT_DAYS . ' kundan beri aloqa qilmagan');
            }
        }

        $fiscalFailed = (int) $data['fiscal_failed'];
        if ($fiscalFailed > 0) {
            $add('fiscal_failed', StoreHealthLevel::ATTENTION, $fiscalFailed >= 10 ? 20 : 10, "Oxirgi 7 kunda {$fiscalFailed} ta fiskal chek yiqilgan");
        }

        if ($store->exceedsCreditLimit()) {
            $add('debt_over_limit', StoreHealthLevel::CHURN_RISK, 25, 'Komissiya qarzi kredit limitidan oshgan: ' . self::money($store->commissionDebt()) . ' (limit ' . self::money(Money::of($store->credit_limit)->abs()) . ')');
        }

        $overdue = $data['overdue'];
        if ($overdue !== null) {
            $due = PlatformStats::dayStartOf((string) $overdue->oldest_due);
            $days = (int) floor($due->diffInDays(PlatformStats::dayStart($now), true));
            $count = (int) $overdue->invoices;
            $label = ($count > 1 ? "{$count} ta hisob-faktura to'lanmagan" : "Hisob-faktura to'lanmagan") . ", muddatidan {$days} kun o'tdi";
            $days >= 7
                ? $add('invoice_overdue', StoreHealthLevel::CHURN_RISK, 20, $label)
                : $add('invoice_overdue', StoreHealthLevel::ATTENTION, 10, $label);
        }

        $complaints = $data['complaints'];
        $complaintCount = (int) ($complaints->total ?? 0);
        if ($complaintCount >= 6) {
            $add('complaints', StoreHealthLevel::CHURN_RISK, 20, "Oxirgi 30 kunda {$complaintCount} ta shikoyat");
        } elseif ($complaintCount >= 3) {
            $add('complaints', StoreHealthLevel::ATTENTION, 10, "Oxirgi 30 kunda {$complaintCount} ta shikoyat");
        }

        $lastActivityAt = $data['last_activity_at'] !== null ? PlatformStats::fromColumn((string) $data['last_activity_at']) : null;
        $inactiveDays = $lastActivityAt !== null ? (int) floor($lastActivityAt->diffInDays($now, true)) : null;
        if ($live) {
            if (($inactiveDays === null || $inactiveDays >= 30) && $ageDays >= 30) {
                $add('owner_inactive', StoreHealthLevel::CHURN_RISK, 20, $inactiveDays === null
                    ? "Egasi va menejerlar 30 kundan beri tizimga kirmagan"
                    : "Egasi va menejerlar {$inactiveDays} kundan beri tizimga kirmagan");
            } elseif (($inactiveDays !== null && $inactiveDays >= 14) || ($inactiveDays === null && $ageDays >= 14)) {
                $add('owner_inactive', StoreHealthLevel::ATTENTION, 10, $inactiveDays === null
                    ? "Egasi va menejerlar tizimga kirmagan"
                    : "Egasi va menejerlar {$inactiveDays} kundan beri tizimga kirmagan");
            }
        }

        if (in_array($status, [StoreStatus::SUSPENDED, StoreStatus::PAYMENT_REQUIRED, StoreStatus::FINANCE_HOLD], true)) {
            $add('status', StoreHealthLevel::ATTENTION, 10, 'Holati: ' . StoreLabels::status($status));
        }

        $score = max(0, 100 - array_sum(array_column($reasons, 'points')));
        $hasChurn = in_array(StoreHealthLevel::CHURN_RISK->value, array_column($reasons, 'level'), true);
        $level = match (true) {
            $hasChurn || $score < 50 => StoreHealthLevel::CHURN_RISK,
            $reasons !== [] || $score < 80 => StoreHealthLevel::ATTENTION,
            default => StoreHealthLevel::GOOD,
        };

        // The most serious first
        usort($reasons, fn (array $a, array $b) => [$b['level'] === 'churn_risk', $b['points']] <=> [$a['level'] === 'churn_risk', $a['points']]);

        return [
            'level' => $level,
            'score' => $score,
            'reasons' => $reasons,
            'metrics' => [
                'orders_recent' => $recent,
                'orders_previous' => $previous,
                'last_order_at' => $lastOrderAt?->toIso8601String(),
                'terminals' => $terminals,
                'terminals_silent' => $silent,
                'terminal_last_seen_at' => isset($devices->last_seen_at) ? PlatformStats::fromColumn((string) $devices->last_seen_at)->toIso8601String() : null,
                'fiscal_failed' => $fiscalFailed,
                'complaints' => $complaintCount,
                'complaints_open' => (int) ($complaints->open ?? 0),
                'invoices_overdue' => (int) ($overdue->invoices ?? 0),
                'debt_balance' => Money::of($store->debt_balance)->toString(),
                'subscription_debt' => Money::of($store->subscription_debt)->toString(),
                'commission_debt' => $store->commissionDebt()->toString(),
                'debt_over_limit' => $store->exceedsCreditLimit(),
                'last_activity_at' => $lastActivityAt?->toIso8601String(),
            ],
        ];
    }

    private static function money(Money|string|int|float|null $amount): string
    {
        return number_format(Money::of($amount)->toFloat(), 0, '.', ' ') . " so'm";
    }
}
