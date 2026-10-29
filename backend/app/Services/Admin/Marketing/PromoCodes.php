<?php

namespace App\Services\Admin\Marketing;

use App\Models\Order;
use App\Models\PromoCode;
use App\Services\Promo\PromoCodeService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * P4-31: Marketing -> Promo-kodlar (what PromoCodeResource of the old /superadmin panel did), with the usage numbers
 * that panel only hinted at.
 *
 * The rules are the order flow's own (PromoCodeService, called from CreateOrderAction): the code is stored upper case
 * and trimmed; a `percentage` code takes 0-100 % (with an optional cap in so'm), a `fixed` one a whole amount in so'm
 * (the order flow rounds it to whole so'm and ignores the cap); the minimum order amount, the total and the
 * per-customer limits, the expiry and the store are checked there, so the same fields are the ones edited here.
 *
 * "Used" (= redemptions) is what counts against the limits: orders with the code that are not cancelled, rejected or
 * refunded (PromoCode::RELEASED_ORDER_STATUSES). Once ANY order carries the code, what it gives is locked: the code
 * itself, its type, value, cap and store. Changing the discount under customers who already got the old one would
 * rewrite what the orders and the reports say; a new campaign is a new code. The limits, the minimum amount, the
 * expiry and on/off stay editable.
 */
class PromoCodes
{
    public const TYPES = ['percentage' => 'Foiz', 'fixed' => 'Belgilangan summa'];

    public const STATUSES = ['active' => 'Faol', 'inactive' => "O'chirilgan", 'expired' => 'Muddati tugagan', 'exhausted' => 'Limit tugagan'];

    /** Fields that cannot change once an order carries the code. */
    public const LOCKED_WHEN_USED = ['code', 'type', 'value', 'max_discount_amount', 'store_id'];

    public function __construct(
        private readonly PromoCodeService $codes,
    ) {}

    /**
     * @param  array{q?: string|null, status?: string|null, store_id?: int|null, sort?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $now = now();
        $query = PromoCode::query()->with('store:id,name');

        if (($filters['q'] ?? null) !== null && $filters['q'] !== '') {
            $query->where('code', 'ilike', '%' . addcslashes($this->codes->normalize($filters['q']), '%_\\') . '%');
        }
        if (isset($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }
        match ($filters['status'] ?? null) {
            'inactive' => $query->where('is_active', false),
            'expired' => $query->where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<=', $now),
            'exhausted' => $query->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))->whereRaw($this->exhaustedSql()),
            'active' => $query->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))->whereRaw('NOT (' . $this->exhaustedSql() . ')'),
            default => null,
        };

        match ($filters['sort'] ?? 'newest') {
            'code' => $query->orderBy('code'),
            'expiry' => $query->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        return $query->paginate($perPage);
    }

    /**
     * @param  iterable<PromoCode>  $promos
     * @return list<array<string, mixed>>
     */
    public function rows(iterable $promos): array
    {
        $promos = collect($promos);
        $stats = $this->stats($promos->pluck('id')->map(fn ($id) => (int) $id)->all());
        $now = now();

        return $promos->map(fn (PromoCode $promo) => $this->present($promo, $stats[(int) $promo->id] ?? $this->emptyStats(), $now))->values()->all();
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public function present(PromoCode $promo, array $stats, ?CarbonInterface $now = null): array
    {
        $status = $this->status($promo, (int) $stats['uses'], $now ?? now());

        return [
            'id' => (int) $promo->id,
            'code' => (string) $promo->code,
            'type' => (string) $promo->type,
            'type_label' => self::TYPES[$promo->type] ?? (string) $promo->type,
            'value' => $this->decimal($promo->value),
            'min_order_amount' => Money::of($promo->min_order_amount)->toString(),
            'max_discount_amount' => $promo->max_discount_amount === null ? null : Money::of($promo->max_discount_amount)->toString(),
            'usage_limit' => $promo->usage_limit,
            'per_user_limit' => $promo->per_user_limit,
            'store' => $promo->store ? ['id' => (int) $promo->store->id, 'name' => (string) $promo->store->name] : null,
            'is_active' => (bool) $promo->is_active,
            'expires_at' => MarketingTime::iso($promo->expires_at),
            'status' => $status,
            'status_label' => self::STATUSES[$status],
            'stats' => [
                'uses' => (int) $stats['uses'],
                'unique_customers' => (int) $stats['unique_customers'],
                'discount_total' => (string) $stats['discount_total'],
                'gmv' => (string) $stats['gmv'],
            ],
            // Any order (even a cancelled one) carries it: what it gives can no longer change
            'locked' => (bool) $stats['has_orders'],
            'created_at' => MarketingTime::iso($promo->created_at),
            'updated_at' => MarketingTime::iso($promo->updated_at),
        ];
    }

    /**
     * The code's card: the row and the last orders that used it.
     *
     * @return array<string, mixed>
     */
    public function card(PromoCode $promo): array
    {
        $promo->loadMissing('store:id,name');
        $row = $this->present($promo, $this->stats([(int) $promo->id])[(int) $promo->id] ?? $this->emptyStats());

        $orders = Order::query()
            ->where('promo_code_id', $promo->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'uuid', 'status', 'discount', 'total_price', 'created_at'])
            ->map(fn (Order $order) => [
                'uuid' => (string) $order->uuid,
                'status' => $order->status->value,
                'discount' => Money::of($order->discount)->toString(),
                'total' => Money::of($order->total_price)->toString(),
                'created_at' => MarketingTime::iso($order->created_at),
            ])->all();

        return $row + ['recent_orders' => $orders];
    }

    /**
     * Usage per code in one query over the (promo_code_id, user_id) index: orders that count against the limits.
     * `has_orders` looks at every order, released ones too.
     *
     * @param  list<int>  $ids
     * @return array<int, array{uses: int, unique_customers: int, discount_total: string, gmv: string, has_orders: bool}>
     */
    public function stats(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $released = PromoCode::RELEASED_ORDER_STATUSES;
        $rows = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereIn('promo_code_id', $ids)
            ->selectRaw('promo_code_id, COUNT(*) AS orders_all')
            ->selectRaw('COUNT(*) FILTER (WHERE status NOT IN (?, ?, ?)) AS uses', $released)
            ->selectRaw('COUNT(DISTINCT user_id) FILTER (WHERE status NOT IN (?, ?, ?)) AS customers', $released)
            ->selectRaw('COALESCE(SUM(discount) FILTER (WHERE status NOT IN (?, ?, ?)), 0) AS discount_total', $released)
            ->selectRaw('COALESCE(SUM(total_price) FILTER (WHERE status NOT IN (?, ?, ?)), 0) AS gmv', $released)
            ->groupBy('promo_code_id')
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row->promo_code_id] = [
                'uses' => (int) $row->uses,
                'unique_customers' => (int) $row->customers,
                'discount_total' => Money::of($row->discount_total)->toString(),
                'gmv' => Money::of($row->gmv)->toString(),
                'has_orders' => (int) $row->orders_all > 0,
            ];
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $data  validated (the controller's rules)
     *
     * @throws MarketingException
     */
    public function create(array $data): PromoCode
    {
        $this->assertValueFits((string) $data['type'], (string) $data['value']);

        $attributes = $this->attributes($data);
        if ($attributes['type'] === 'fixed') {
            $attributes['max_discount_amount'] = null;
        }

        $promo = new PromoCode();
        $promo->forceFill($attributes + [
            'min_order_amount' => '0.00',
            'is_active' => true,
        ])->save();

        return $promo->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MarketingException
     */
    public function update(PromoCode $promo, array $data): PromoCode
    {
        // Review C#5: under the row lock checkout takes (PromoCodeService::find(..., true)), so no order is placed
        // with the old value between the check for orders below and the save
        return DB::transaction(function () use ($promo, $data) {
            $this->lock($promo);

            return $this->applyUpdate($promo, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MarketingException
     */
    private function applyUpdate(PromoCode $promo, array $data): PromoCode
    {
        $attributes = $this->attributes($data);
        $type = $attributes['type'] ?? $promo->type;
        $value = $attributes['value'] ?? $promo->value;

        if (array_key_exists('type', $attributes) || array_key_exists('value', $attributes)) {
            $this->assertValueFits((string) $type, (string) $value);
        }
        // A fixed code has no cap (the order flow ignores it): drop a stale one when the type changes
        if ($type === 'fixed' && (array_key_exists('type', $attributes) || array_key_exists('max_discount_amount', $attributes))) {
            $attributes['max_discount_amount'] = null;
        }

        $locked = $this->lockedChanges($promo, $attributes);
        if ($locked !== [] && $this->hasOrders($promo)) {
            throw new MarketingException(
                "Bu promo-koddan buyurtma berilgan, shuning uchun uning chegirmasini o'zgartirib bo'lmaydi. Yangi promo-kod yarating, eskisini o'chiring.",
                'promo_code_used',
                422,
                ['fields' => $locked],
            );
        }

        $promo->forceFill($attributes)->save();

        return $promo->refresh();
    }

    /**
     * Only a code nobody ordered with may be removed; any other is switched off instead (its orders keep pointing at it).
     *
     * @throws MarketingException
     */
    public function delete(PromoCode $promo): void
    {
        DB::transaction(function () use ($promo) {
            // Review C#5: the row lock checkout takes, so an order placed meanwhile is seen here
            $this->lock($promo);
            if ($this->hasOrders($promo)) {
                throw new MarketingException("Bu promo-koddan buyurtma berilgan, uni o'chirib bo'lmaydi. O'chirib qo'ying (faolsizlantiring).", 'promo_code_used', 409);
            }
            $promo->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(PromoCode $promo): array
    {
        return [
            'promo' => (string) $promo->code,
            'type' => (string) $promo->type,
            'value' => $this->decimal($promo->value),
            'min_order_amount' => Money::of($promo->min_order_amount)->toString(),
            'max_discount_amount' => $promo->max_discount_amount === null ? null : Money::of($promo->max_discount_amount)->toString(),
            'usage_limit' => $promo->usage_limit,
            'per_user_limit' => $promo->per_user_limit,
            'store_id' => $promo->store_id,
            'is_active' => (bool) $promo->is_active,
            'expires_at' => MarketingTime::iso($promo->expires_at),
        ];
    }

    public function status(PromoCode $promo, int $uses, CarbonInterface $now): string
    {
        return match (true) {
            !$promo->is_active => 'inactive',
            $promo->expires_at !== null && $promo->expires_at->lte($now) => 'expired',
            $promo->usage_limit !== null && $uses >= $promo->usage_limit => 'exhausted',
            default => 'active',
        };
    }

    /**
     * The value must make sense for the type, as the order flow reads it.
     *
     * @throws MarketingException
     */
    public function assertValueFits(string $type, string $value): void
    {
        $amount = Money::of($value);
        if ($type === 'percentage' && ($amount->isLessThanOrEqualTo(0) || $amount->isGreaterThan(100))) {
            throw new MarketingException("Foiz 0 dan katta va 100 dan oshmasligi kerak.", 'invalid_value');
        }
        if ($type === 'fixed') {
            if ($amount->isLessThanOrEqualTo(0) || !$amount->equals($amount->roundTo(0))) {
                throw new MarketingException("Belgilangan chegirma butun so'm bo'lishi kerak (0 dan katta).", 'invalid_value');
            }
        }
    }

    /**
     * Locks the code's row for the rest of the transaction and takes its current values.
     */
    private function lock(PromoCode $promo): void
    {
        $current = PromoCode::query()->whereKey($promo->getKey())->lockForUpdate()->first();
        if ($current !== null) {
            $promo->setRawAttributes($current->getAttributes(), true);
        }
    }

    public function hasOrders(PromoCode $promo): bool
    {
        return Order::withTrashed()->where('promo_code_id', $promo->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function lockedChanges(PromoCode $promo, array $attributes): array
    {
        $changed = [];
        foreach (self::LOCKED_WHEN_USED as $field) {
            if (!array_key_exists($field, $attributes)) {
                continue;
            }
            $now = $attributes[$field];
            $was = $promo->getAttribute($field);
            $same = match ($field) {
                'value', 'max_discount_amount' => ($now === null || $was === null) ? $now === $was : Money::of($now)->equals(Money::of($was)),
                'store_id' => (($now === null) ? null : (int) $now) === (($was === null) ? null : (int) $was),
                default => (string) $now === (string) $was,
            };
            if (!$same) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = [];
        if (array_key_exists('code', $data)) {
            $attributes['code'] = $this->codes->normalize((string) $data['code']);
        }
        if (array_key_exists('type', $data)) {
            $attributes['type'] = (string) $data['type'];
        }
        foreach (['value', 'min_order_amount'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = Money::of((string) $data[$field])->toString();
            }
        }
        if (array_key_exists('max_discount_amount', $data)) {
            $attributes['max_discount_amount'] = $data['max_discount_amount'] === null ? null : Money::of((string) $data['max_discount_amount'])->toString();
        }
        foreach (['usage_limit', 'per_user_limit', 'store_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field] === null ? null : (int) $data[$field];
            }
        }
        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) $data['is_active'];
        }
        if (array_key_exists('expires_at', $data)) {
            $attributes['expires_at'] = $data['expires_at'];
        }

        return $attributes;
    }

    /**
     * @return array{uses: int, unique_customers: int, discount_total: string, gmv: string, has_orders: bool}
     */
    private function emptyStats(): array
    {
        return ['uses' => 0, 'unique_customers' => 0, 'discount_total' => '0.00', 'gmv' => '0.00', 'has_orders' => false];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function exhaustedSql(): string
    {
        $released = "'" . implode("', '", PromoCode::RELEASED_ORDER_STATUSES) . "'";

        return "promo_codes.usage_limit IS NOT NULL AND (SELECT COUNT(*) FROM orders WHERE orders.promo_code_id = promo_codes.id AND orders.deleted_at IS NULL AND orders.status NOT IN ({$released})) >= promo_codes.usage_limit";
    }
}
