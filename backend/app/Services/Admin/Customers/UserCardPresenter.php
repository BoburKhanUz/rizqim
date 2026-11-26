<?php

namespace App\Services\Admin\Customers;

use App\Auth\AdminTokenGuard;
use App\Domain\Analytics\AnalyticsErasure;
use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderRefundRequest;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminPii;
use App\Services\Auth\TokenService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * P4-31: the user card of Mijozlar va yordam (GET /admin/users/{id}, admin.users.view): profile (phone through
 * AdminPii: masked unless admin.pii.view, a reveal is audited), the block state, order totals, the last orders,
 * refunds, keshbek balance and its movements, complaints, sessions and devices, store membership for staff (and other
 * accounts holding the same phone or Telegram), audit entries about the account, how many Mini App statistics rows are
 * stored (analytics.events, for a privacy request), and which actions the member may take. A part the member has no permission for comes back null: orders and refunds need admin.orders.view,
 * complaints admin.support.view, the audit entries admin.audit.view.
 */
class UserCardPresenter
{
    private const RECENT = 20;

    public function __construct(
        private readonly AdminPii $pii,
        private readonly AdminPermissionService $permissions,
        private readonly KeshbekHistory $keshbek,
        private readonly AnalyticsErasure $analytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function card(User $user, User $member): array
    {
        $user->loadMissing(['store:id,name,status', 'branch:id,name']);
        $can = fn (AdminPermission $permission) => $this->permissions->has($member, $permission);
        $manage = $can(AdminPermission::SUPPORT_MANAGE) && !$user->isPlatformAccount();

        return [
            'profile' => $this->profile($user, $member),
            'block' => $this->block($user),
            'stats' => $this->stats($user),
            'orders' => $can(AdminPermission::ORDERS_VIEW) ? $this->orders($user) : null,
            'refunds' => $can(AdminPermission::ORDERS_VIEW) ? $this->refunds($user) : null,
            'keshbek' => $this->keshbek->for($user),
            'complaints' => $can(AdminPermission::SUPPORT_VIEW) ? $this->complaints($user) : null,
            'sessions' => $this->sessions($user),
            'membership' => $this->membership($user),
            'linked_accounts' => $this->linkedAccounts($user),
            'audit' => $can(AdminPermission::AUDIT_VIEW) ? $this->audit($user) : null,
            // Privacy requests: how many Mini App statistics rows of the account are stored (one index read)
            'analytics' => ['events' => $this->analytics->count((int) $user->id)],
            'actions' => [
                'block' => $manage && !$user->isBlocked(),
                'unblock' => $manage && $user->isBlocked(),
                'close_sessions' => $manage,
                'forget_analytics' => $manage,
            ],
            'links' => [
                'audit' => '/audit?subject_type=User&subject_id=' . $user->id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user, User $member): array
    {
        $referrer = $user->referred_by ? User::query()->whereKey($user->referred_by)->first(['id', 'name', 'platform_role', 'role']) : null;

        return [
            'id' => (int) $user->id,
            'uuid' => (string) $user->uuid,
            'name' => (string) $user->name,
            'role' => $user->role->value,
            'role_label' => $user->role->labelUz(),
            'phone' => $this->pii->phone($member, $user->phone, 'user', (int) $user->id),
            'phone_verified' => $user->phone_verified_at !== null,
            'telegram' => [
                'linked' => $user->hasTelegram(),
                'username' => $user->hasTelegram() && filled($user->telegram_username) ? (string) $user->telegram_username : null,
            ],
            'courier_type' => $user->role === UserRole::COURIER ? ['value' => $user->courier_type->value, 'label' => $user->courier_type->labelUz()] : null,
            'referred_by' => $referrer && !$referrer->isPlatformAccount() ? ['id' => (int) $referrer->id, 'name' => (string) $referrer->name] : null,
            'referrals' => User::query()->where('referred_by', $user->id)->count(),
            // Owner decision 3: whether the referrer's bonus for this customer was granted, and why it may still wait
            'referral_bonus' => $user->referred_by ? $this->referralBonus($user) : null,
            // Owner's rule B: the bonuses this user (as a referrer) has this Asia/Tashkent month, of the cap; null when
            // they invited nobody
            'referral_month' => $this->referralMonth($user),
            'created_at' => CustomerFormat::iso($user->created_at),
        ];
    }

    /**
     * Owner decision 3 (and rules A, B, review finding 1): the referrer's bonus for this (referred) customer — `granted`
     * (the order that earned it; a bonus taken back on a full refund no longer counts) or `pending` with `reason`:
     * `booking_pending` (a qualifying order's ledger booking failed and is retried), `referrer_cap` (the referrer's
     * monthly cap was reached when a qualifying order was delivered), `taken_back` (the bonus went back on a full
     * refund; the next qualifying order earns it), `commission_below_bonus` (an order's commission was below the bonus),
     * `below_minimum` (a verified order below 50 000), `no_qualifying_order` (no delivered order whose payment the
     * platform verified: cash, keshbek and the store's own Click / Payme / Uzum / Paynet do not count) or `not_granted`
     * (a qualifying order delivered while the referral was not open, e.g. before the invite was linked).
     * `unverified_deliveries`: their delivered orders that did not count as verified. The rule itself is
     * ReferralService::qualifiesForReferralBonus; this only explains it.
     *
     * @return array{status: string, reason: string|null, label: string, order: array{uuid: string, short_code: string|null}|null, unverified_deliveries: int}
     */
    private function referralBonus(User $user): array
    {
        $ledger = app(\App\Services\Finance\LedgerService::class);
        $orders = \App\Models\Order::query()->withTrashed()->where('user_id', $user->id)
            ->whereIn('status', [\App\Enums\OrderStatus::DELIVERED->value, \App\Enums\OrderStatus::REFUNDED->value])
            ->orderBy('id')
            ->get(['id', 'uuid', 'short_code', 'store_id', 'status', 'payment_method', 'payment_owner', 'payment_status', 'total_price', 'commission_amount', 'settlement_failed_at', 'updated_at']);
        $unverified = $orders->reject(fn (\App\Models\Order $order) => \App\Services\Auth\ReferralService::paymentVerifiedByPlatform($order))->count();
        $amount = number_format(\App\Services\Auth\ReferralService::BONUS, 0, ',', ' ');
        $result = fn (string $status, ?string $reason, string $label, ?\App\Models\Order $order = null) => [
            'status' => $status,
            'reason' => $reason,
            'label' => $label,
            'order' => $order ? ['uuid' => (string) $order->uuid, 'short_code' => $order->short_code] : null,
            'unverified_deliveries' => $unverified,
        ];

        // The bonus rows on the customer's orders (soft-deleted ones too), per order: a positive sum is a standing bonus
        $bonusRows = \App\Models\StoreTransaction::query()->whereIn('order_id', $orders->pluck('id')->all() ?: [0])
            ->where(fn ($query) => \App\Services\Finance\LedgerService::whereReferralRewardOf($query, (int) $user->id)
                ->orWhere(fn ($row) => $row->where('type', \App\Enums\StoreTransactionType::REFERRAL_BONUS->value)->where('amount', '<', 0)))
            ->get(['order_id', 'amount']);
        $netByOrder = $bonusRows->groupBy('order_id')->map(fn ($rows) => \App\Support\Money::sum(...$rows->pluck('amount')->all()));
        if ($ledger->referralRewardedNet((int) $user->id)) {
            $orderId = $netByOrder->filter(fn (\App\Support\Money $net) => $net->isPositive())->keys()->first();
            $order = $orderId !== null ? $orders->firstWhere('id', (int) $orderId) : null;

            return $result('granted', null, "Taklif qilganga {$amount} ball berilgan" . ($order?->short_code ? " (#{$order->short_code})" : '') . '.', $order);
        }

        $takenBack = $bonusRows->contains(fn ($row) => \App\Support\Money::of((string) $row->amount)->isNegative());
        // Delivered and still paid (a refunded order earns nothing), not one whose bonus went back
        $delivered = $orders->filter(fn (\App\Models\Order $order) => $order->status === \App\Enums\OrderStatus::DELIVERED
            && $order->payment_status === \App\Enums\PaymentStatus::PAID && !$netByOrder->has($order->id))->keyBy('id');
        $why = $delivered->map(fn (\App\Models\Order $order) => \App\Services\Auth\ReferralService::whyNotQualifying($order));
        $qualifying = $delivered->filter(fn (\App\Models\Order $order) => $why[$order->id] === null);
        $cap = \App\Services\Auth\ReferralService::MONTHLY_CAP_PER_REFERRER;
        $referrerId = (int) $user->referred_by;
        $deliveredAt = fn (\App\Models\Order $order) => \App\Models\OrderStatusHistory::query()->where('order_id', $order->id)
            ->where('status', \App\Enums\OrderStatus::DELIVERED->value)->min('created_at') ?? $order->updated_at;
        $later = " Keyingi mos buyurtmadan so'ng beriladi.";

        if (($failed = $qualifying->first(fn (\App\Models\Order $order) => $order->settlement_failed_at !== null)) !== null) {
            return $result('pending', 'booking_pending', "Bonus kutilmoqda: #{$failed->short_code} buyurtmasining hisob-kitobi hali yozilmagan, qayta urinishda beriladi.", $failed);
        }
        if (($capped = $qualifying->first(fn (\App\Models\Order $order) => \App\Services\Auth\ReferralService::monthlyBonusesOf($referrerId, \Illuminate\Support\Carbon::parse($deliveredAt($order))) >= $cap)) !== null) {
            return $result('pending', 'referrer_cap', "Bonus kutilmoqda: taklif qiluvchining oylik chegarasi to'lgan (oyiga {$cap} ta bonus), #{$capped->short_code} uchun berilmadi." . $later, $capped);
        }
        if ($takenBack) {
            return $result('pending', 'taken_back', "Bonus qaytarib olindi: uni bergan buyurtma to'liq qaytarilgan." . $later);
        }
        if ($qualifying->isNotEmpty()) {
            return $result('pending', 'not_granted', "Bonus kutilmoqda: mos buyurtma taklif ochiq bo'lmagan paytda yetkazilgan (masalan, taklif keyin bog'langan)." . $later, $qualifying->first());
        }
        if ($why->contains('commission_below_bonus')) {
            return $result('pending', 'commission_below_bonus', "Bonus kutilmoqda: buyurtma komissiyasi bonusdan kam ({$amount} so'm), shuning uchun berilmadi." . $later);
        }
        if ($why->contains('below_minimum') && $delivered->contains(fn (\App\Models\Order $order) => \App\Services\Auth\ReferralService::paymentVerifiedByPlatform($order))) {
            return $result('pending', 'below_minimum', "Bonus kutilmoqda: onlayn to'langan yetkazilgan buyurtmalar " . number_format(\App\Services\Auth\ReferralService::MIN_ORDER, 0, ',', ' ') . " so'mdan kam edi." . $later);
        }

        return $result('pending', 'no_qualifying_order', "Bonus kutilmoqda: taklif qilganga {$amount} ball do'stning RIZQIM orqali onlayn to'langan (to'lovni platforma tasdiqlagan), " . number_format(\App\Services\Auth\ReferralService::MIN_ORDER, 0, ',', ' ') . " so'mdan kam bo'lmagan birinchi yetkazilgan buyurtmasidan so'ng beriladi. Naqd, keshbek va ovqatxonaning o'z Click, Payme, Uzum yoki Paynet hisobiga to'langan buyurtmalar hisobga olinmaydi"
            . ($unverified > 0 ? " (bunday yetkazilgan buyurtmalar: {$unverified})" : '') . '.');
    }

    /**
     * Owner's rule B on a referrer's card: their bonuses this Asia/Tashkent month (granted in the month and not taken back: a gross limit, ReferralService::monthlyBonusesOf) of the cap.
     *
     * @return array{count: int, cap: int, label: string}|null
     */
    private function referralMonth(User $user): ?array
    {
        if (!User::query()->where('referred_by', $user->id)->exists()) {
            return null;
        }
        $count = \App\Services\Auth\ReferralService::monthlyBonusesOf((int) $user->id, now());
        $cap = \App\Services\Auth\ReferralService::MONTHLY_CAP_PER_REFERRER;

        return ['count' => $count, 'cap' => $cap, 'label' => "Bu oy: {$count}/{$cap}"];
    }

    /**
     * @return array{blocked: bool, at: string|null, reason: string|null, by: array{id: int, name: string}|null}
     */
    private function block(User $user): array
    {
        $by = $user->blocked_by ? User::query()->whereKey($user->blocked_by)->first(['id', 'name']) : null;

        return [
            'blocked' => $user->isBlocked(),
            'at' => CustomerFormat::iso($user->blocked_at),
            'reason' => $user->isBlocked() ? $user->blocked_reason : null,
            'by' => $user->isBlocked() && $by ? ['id' => (int) $by->id, 'name' => (string) $by->name] : null,
        ];
    }

    /**
     * The user's analytics (as the old superadmin user page showed them): orders, delivered total and average check,
     * the segment.
     *
     * @return array<string, mixed>
     */
    private function stats(User $user): array
    {
        $row = Order::query()->where('user_id', $user->id)->selectRaw(
            'count(*) as total, count(*) filter (where status = ?) as delivered, coalesce(sum(total_price) filter (where status = ?), 0) as delivered_sum, round(avg(total_price) filter (where status = ?), 2) as avg_check, count(*) filter (where status in (?, ?)) as cancelled, max(created_at) as last_at',
            [OrderStatus::DELIVERED->value, OrderStatus::DELIVERED->value, OrderStatus::DELIVERED->value, OrderStatus::CANCELED->value, OrderStatus::REJECTED->value],
        )->toBase()->first();

        $total = (int) ($row->total ?? 0);
        $delivered = (int) ($row->delivered ?? 0);
        $sum = Money::of($row->delivered_sum ?? 0);
        $segment = match (true) {
            $total >= 10 => ['key' => 'vip', 'label' => 'Doimiy mijoz (VIP)'],
            $total > 1 => ['key' => 'returning', 'label' => 'Qaytgan mijoz'],
            $total === 1 => ['key' => 'new', 'label' => 'Yangi mijoz'],
            default => ['key' => 'none', 'label' => 'Buyurtma bermagan'],
        };

        return [
            'orders' => $total,
            'delivered' => $delivered,
            'cancelled' => (int) ($row->cancelled ?? 0),
            'delivered_sum' => $sum->toString(),
            'avg_check' => $delivered > 0 && isset($row->avg_check) ? Money::of((string) $row->avg_check)->toString() : null,
            'last_order_at' => isset($row->last_at) ? CustomerFormat::iso(\Carbon\CarbonImmutable::parse((string) $row->last_at)) : null,
            'segment' => $segment,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orders(User $user): array
    {
        return Order::query()->with('store:id,name')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::RECENT)
            ->get(['id', 'uuid', 'short_code', 'store_id', 'status', 'total_price', 'payment_method', 'payment_status', 'channel', 'created_at'])
            ->map(fn (Order $order) => [
                'uuid' => (string) $order->uuid,
                'short_code' => $order->short_code,
                'store' => $order->store ? ['id' => (int) $order->store->id, 'name' => (string) $order->store->name] : null,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total' => CustomerFormat::money($order->total_price),
                'payment_method' => $order->payment_method,
                'created_at' => CustomerFormat::iso($order->created_at),
                'href' => '/orders/' . $order->uuid,
            ])->values()->all();
    }

    /**
     * Refund requests (Boshqaruv markazi) for the person's orders.
     *
     * @return list<array<string, mixed>>
     */
    private function refunds(User $user): array
    {
        return OrderRefundRequest::query()->with('order:id,uuid,short_code')
            ->whereIn('order_id', Order::query()->select('id')->where('user_id', $user->id))
            ->orderByDesc('id')
            ->limit(self::RECENT)
            ->get(['id', 'uuid', 'order_id', 'amount', 'status', 'reason', 'created_at', 'decided_at'])
            ->map(fn (OrderRefundRequest $refund) => [
                'uuid' => (string) $refund->uuid,
                'order' => $refund->order ? ['uuid' => (string) $refund->order->uuid, 'short_code' => $refund->order->short_code] : null,
                'amount' => CustomerFormat::money($refund->amount),
                'status' => $refund->status,
                'status_label' => match ($refund->status) {
                    OrderRefundRequest::APPROVED => 'Tasdiqlangan',
                    OrderRefundRequest::REJECTED => 'Rad etilgan',
                    default => 'Kutilmoqda',
                },
                'reason' => $refund->reason,
                'created_at' => CustomerFormat::iso($refund->created_at),
                'decided_at' => CustomerFormat::iso($refund->decided_at),
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function complaints(User $user): array
    {
        return Complaint::query()->with('order:id,uuid,short_code')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::RECENT)
            ->get()
            ->map(fn (Complaint $complaint) => [
                'uuid' => (string) $complaint->uuid,
                'type' => (string) $complaint->type,
                'type_label' => ComplaintLabels::type($complaint->type),
                'support_status' => $complaint->support_status->value,
                'support_status_label' => $complaint->support_status->label(),
                'description' => CustomerFormat::short($complaint->description),
                'order' => $complaint->order ? ['uuid' => (string) $complaint->order->uuid, 'short_code' => $complaint->order->short_code] : null,
                'created_at' => CustomerFormat::iso($complaint->created_at),
            ])->values()->all();
    }

    /**
     * Live tokens (never an admin session), refresh families and the person's own devices.
     *
     * @return array<string, mixed>
     */
    private function sessions(User $user): array
    {
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->where('name', '!=', AdminTokenGuard::NAME)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('last_used_at')->orderByDesc('id')
            ->get(['id', 'name', 'device_id', 'created_at', 'last_used_at', 'expires_at']);

        $deviceNames = Device::query()->whereIn('id', $tokens->pluck('device_id')->filter()->unique()->values())->pluck('name', 'id');

        $devices = Device::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')->limit(self::RECENT)
            ->get(['id', 'uuid', 'type', 'name', 'platform', 'app_version', 'last_seen_at', 'created_at']);

        $last = $tokens->max('last_used_at');

        return [
            'active_tokens' => $tokens->count(),
            'refresh_sessions' => RefreshToken::query()->where('user_id', $user->id)->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
            'last_used_at' => $last ? CustomerFormat::iso(\Carbon\CarbonImmutable::parse((string) $last)) : null,
            'tokens' => $tokens->take(self::RECENT)->map(fn (PersonalAccessToken $token) => [
                'id' => (int) $token->id,
                'kind' => $this->tokenKind((string) $token->name),
                'kind_label' => $this->tokenLabel((string) $token->name),
                'device' => $token->getAttribute('device_id') ? ($deviceNames[$token->getAttribute('device_id')] ?? null) : null,
                'created_at' => CustomerFormat::iso($token->created_at),
                'last_used_at' => CustomerFormat::iso($token->last_used_at),
                'expires_at' => CustomerFormat::iso($token->expires_at),
            ])->values()->all(),
            'devices' => $devices->map(fn (Device $device) => [
                'uuid' => (string) $device->uuid,
                'type' => $device->type->value,
                'type_label' => $device->type->label(),
                'name' => (string) $device->name,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
                'last_seen_at' => CustomerFormat::iso($device->last_seen_at),
            ])->values()->all(),
        ];
    }

    private function tokenKind(string $name): string
    {
        return match ($name) {
            TokenService::ACCESS_TOKEN_NAME => 'app',
            TokenService::STAFF_SESSION_NAME => 'staff_pin',
            default => 'mini_app',
        };
    }

    private function tokenLabel(string $name): string
    {
        return match ($name) {
            TokenService::ACCESS_TOKEN_NAME => 'Ilova sessiyasi',
            TokenService::STAFF_SESSION_NAME => 'Kassa (PIN) sessiyasi',
            default => 'Mini App / Telegram',
        };
    }

    /**
     * The store a staff account belongs to (one account is one store; the same person in two stores has two).
     *
     * @return array<string, mixed>|null
     */
    private function membership(User $user): ?array
    {
        if (!$user->role->isStoreStaff()) {
            return null;
        }

        return [
            'store' => $user->store ? ['id' => (int) $user->store->id, 'name' => (string) $user->store->name, 'status' => $user->store->status->value] : null,
            'branch' => $user->branch ? ['id' => (int) $user->branch->id, 'name' => (string) $user->branch->name] : null,
            'role' => $user->role->value,
            'role_label' => $user->role->labelUz(),
            'login_phone' => $user->phone_verified_at !== null,
            'login_phone_proof' => $user->phone_verified_at !== null ? ($user->phone_verified_via ?? User::PHONE_VIA_SMS) : null,
            'pin_set' => $user->hasPin(),
        ];
    }

    /**
     * Other accounts (not the team's) holding the same phone or the same Telegram account: the same person as a
     * customer and as staff, or in two stores.
     *
     * @return list<array<string, mixed>>
     */
    private function linkedAccounts(User $user): array
    {
        $phone = is_string($user->phone) && trim($user->phone) !== '' ? trim($user->phone) : null;
        $telegram = $user->hasTelegram() ? (string) $user->telegram_id : null;
        if ($phone === null && $telegram === null) {
            return [];
        }

        return UserDirectory::query()->with('store:id,name')
            ->whereKeyNot($user->id)
            ->where(function (Builder $query) use ($phone, $telegram) {
                if ($phone !== null) {
                    $query->orWhereIn('phone', array_values(array_unique([$phone, ltrim($phone, '+'), '+' . ltrim($phone, '+')])));
                }
                if ($telegram !== null) {
                    $query->orWhere('telegram_id', $telegram);
                }
            })
            ->orderBy('id')->limit(10)
            ->get(['id', 'name', 'role', 'store_id', 'phone', 'telegram_id', 'blocked_at'])
            ->map(fn (User $other) => [
                'id' => (int) $other->id,
                'name' => (string) $other->name,
                'role' => $other->role->value,
                'role_label' => $other->role->labelUz(),
                'store' => $other->store ? ['id' => (int) $other->store->id, 'name' => (string) $other->store->name] : null,
                'same' => array_values(array_filter([
                    $phone !== null && ltrim((string) $other->phone, '+') === ltrim($phone, '+') ? 'phone' : null,
                    $telegram !== null && (string) $other->telegram_id === $telegram ? 'telegram' : null,
                ])),
                'blocked' => $other->isBlocked(),
                'href' => '/users/' . $other->id,
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function audit(User $user): array
    {
        return AuditLog::query()->with('user:id,name,role,platform_role,platform_roles')
            ->where('model_type', $user->getMorphClass())->where('model_id', $user->id)
            ->orderByDesc('id')->limit(30)->get()
            ->map(function (AuditLog $log) {
                $meta = $log->payload_json;

                return [
                    'id' => (int) $log->id,
                    'action' => $log->action,
                    'actor' => $log->user ? ['id' => (int) $log->user->id, 'name' => (string) $log->user->name, 'team' => $log->user->platform_role !== null] : null,
                    'reason' => is_array($meta) && is_string($meta['reason'] ?? null) ? $meta['reason'] : null,
                    'before' => $log->before,
                    'after' => $log->after,
                    'created_at' => CustomerFormat::iso($log->created_at),
                ];
            })->values()->all();
    }
}
