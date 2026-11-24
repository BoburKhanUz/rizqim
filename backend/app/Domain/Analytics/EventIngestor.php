<?php

namespace App\Domain\Analytics;

use App\Enums\OrderChannel;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md 4.1, A.4): stores one Mini App batch (POST /api/v1/analytics/events) in
 * analytics_events.
 *
 * - Every event is held to its allowlist (AnalyticsEvent::spec()); one that is off it is dropped, never stored in
 *   part. The answer only counts accepted and dropped events (it never says which).
 * - Stored per row: the token's user id (never one from the body), the session and event ids of the app, the event,
 *   the platform code, the app version (a strict version shape only), the ids the event is about and allowlisted
 *   props. Never an IP address, a User-Agent, a Telegram id, the raw start_param or any free text.
 * - Staff (any role but customer, an account tied to a store, a RIZQIM team account) and blocked accounts are never
 *   counted (owner decision 9); with config analytics.enabled off nothing is stored and no daily counter moves (the
 *   route's per-request limiter has counted the request before this runs).
 * - A session is the user's: (user_id, session_id). Another user sending the same session id writes into a session
 *   of their own, never into the first user's (and is never told "accepted" for the first user's rows).
 * - Idempotent: an event is (user_id, session_id, event_uid); a repeated one in a batch, or a replayed batch (a retry
 *   whose first answer was lost), is answered as accepted and not stored again. The lookup has no time window (a
 *   client clock that ran ahead cannot get past one): one index probe of (session_id, event_uid) per month. The check
 *   and the INSERT run in one short transaction under an advisory lock of (user, session), so two copies of one batch
 *   arriving together store it once.
 * - order_placed: only for the caller's own customer order created within the 24 hours before the batch arrived, and
 *   at most one row per order (in the batch and against stored rows, under a per-user lock: the same order can come
 *   from two sessions).
 * - Daily limits per user, in the limiter's cache store: a budget of stored events (spent first, atomically, and what
 *   does not fit given back) and a cap on new session ids (sessions_per_day; a session past it is dropped whole).
 * - Cost: one indexed read of the session's events, one INSERT of every row; with order_placed one indexed read of
 *   orders and one of analytics_events by order_id (from the order's month on). analytics_events is RevisionMap NONE:
 *   no ETag moves; no queue, no cache write other than the limits (AnalyticsLimits). A month not attached yet lands in
 *   DEFAULT: no DDL here.
 */
final class EventIngestor
{
    /** The event fields the Mini App may send */
    private const EVENT_KEYS = ['uid', 'name', 'at', 'store_id', 'product_id', 'category_id', 'props'];

    private const PROPS_MAX_BYTES = 512;

    /** The daily counters' window (the RateLimiter decay) */
    private const DAY = 86400;

    /**
     * A release number (1.4.2, 2.10, 1.4.2+57) or a lowercase git hash of 7-12 digits with at least one letter (the
     * Mini App image's VITE_SENTRY_RELEASE is the commit's first 12: .github/workflows/deploy.yml). A run of digits
     * alone (a phone number) is never one. At most 16 characters (the column).
     */
    private const APP_VERSION = '/^(?:\d{1,3}(?:\.\d{1,4}){1,3}(?:\+\d{1,8})?|(?=[0-9a-f]*[a-f])[0-9a-f]{7,12})$/';

    /**
     * @param  array{session_id: string, platform: mixed, app_version?: mixed, start_param?: string|null, events: list<mixed>}  $batch
     * @return array{accepted: int, dropped: int}
     */
    public function ingest(User $user, array $batch): array
    {
        $total = count($batch['events']);
        if (!config('analytics.enabled') || self::excluded($user)) {
            return ['accepted' => 0, 'dropped' => $total];
        }

        $userId = (int) $user->getKey();
        $receivedAt = CarbonImmutable::now('UTC')->startOfSecond();
        $session = strtolower($batch['session_id']);
        $start = array_key_exists('start_param', $batch) && $batch['start_param'] !== null ? StartParam::parse($batch['start_param']) : null;
        $common = [
            'received_at' => $receivedAt->format('Y-m-d H:i:s'),
            'session_id' => $session,
            'user_id' => $userId,
            'source' => AnalyticsEvent::SOURCE_MINIAPP,
            'platform' => AnalyticsPlatform::fromTelegram($batch['platform'])->value,
            'app_version' => self::appVersion($batch['app_version'] ?? null),
            // Campaign links arrive in step 4 (campaigns table): until then there is no id to resolve a code to
            'campaign_id' => null,
        ];

        $rows = [];
        $copies = [];
        $orders = false;
        foreach ($batch['events'] as $event) {
            $row = $this->row($event, $receivedAt, $start);
            if ($row === null) {
                continue;
            }
            // A repeated event in the batch is the same event: stored once, each copy answered as accepted
            $rows[$row['event_uid']] ??= $row;
            $copies[$row['event_uid']] = ($copies[$row['event_uid']] ?? 0) + 1;
            $orders = $orders || isset($row['order_uuid']);
        }
        if ($rows === []) {
            return ['accepted' => 0, 'dropped' => $total];
        }

        $kept = DB::transaction(function () use ($user, $userId, $session, $receivedAt, $rows, $common, $orders): array {
            // The user's order lock first (taken only by batches with an order_placed, always before a session lock):
            // one order may be sent from two sessions at once
            if ($orders) {
                self::lock('analytics_events:orders:' . $userId);
            }
            self::lock('analytics_events:' . $userId . ':' . $session);
            if (!$this->sessionWithinCap($userId, $session)) {
                return [];
            }
            // Already stored (a replay): accepted, not again. No received_at bound: whatever the client clock said
            $stored = array_map('strval', DB::table('analytics_events')
                ->where('session_id', $session)
                ->whereIn('event_uid', array_keys($rows))
                ->where('user_id', $userId)
                ->pluck('event_uid')->all());
            $new = array_diff_key($rows, array_flip($stored));
            $new = $this->resolveOrders($user, $new, $receivedAt);
            // What the user's daily budget still takes, in the batch's order (spent here, before the INSERT)
            $new = $this->spendBudget($userId, $new);
            if ($new !== []) {
                try {
                    DB::table('analytics_events')->insert(array_values(array_map(fn (array $row) => $common + $row, $new)));
                } catch (\Throwable $e) {
                    RateLimiter::decrement(AnalyticsLimits::dailyKey($userId), self::DAY, count($new));

                    throw $e;
                }
            }

            return array_merge($stored, array_map('strval', array_keys($new)));
        });

        $accepted = array_sum(array_intersect_key($copies, array_flip($kept)));

        return ['accepted' => $accepted, 'dropped' => $total - $accepted];
    }

    /**
     * Who is never counted: anything but a plain customer (store staff of any role, an account tied to a store, the
     * RIZQIM team) and a blocked account (whose tokens are refused before this anyway).
     */
    public static function excluded(User $user): bool
    {
        return $user->role !== UserRole::CUSTOMER
            || $user->store_id !== null
            || $user->isPlatformAccount()
            || $user->blocked_at !== null;
    }

    private static function lock(string $key): void
    {
        DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
    }

    /**
     * Whether this session of the user may still be stored today: a session id the user has not used in the day counts
     * once against sessions_per_day (an add-if-absent marker per user and session, then the user's counter). Past the
     * cap the session is dropped whole and leaves no marker (its later batches are counted and dropped again). Runs
     * under the session's lock, so two first batches of one session count it once. No SQL.
     */
    private function sessionWithinCap(int $userId, string $session): bool
    {
        $cache = Cache::store(config('cache.limiter'));
        $marker = AnalyticsLimits::sessionMarkerKey($userId, $session);
        if (!$cache->add($marker, 1, self::DAY)) {
            return true;
        }
        if (RateLimiter::increment(AnalyticsLimits::sessionsKey($userId), self::DAY) <= (int) config('analytics.limits.sessions_per_day', 50)) {
            return true;
        }
        $cache->forget($marker);

        return false;
    }

    /**
     * The new events the user's daily budget takes, in the batch's order. Spent first (one atomic increment of the
     * whole batch), then what did not fit is given back, so two batches of the same user in two sessions (two locks)
     * can never store more than the budget between them. None fits: 429 (unless there is nothing new to store).
     *
     * @param  array<string, array<string, mixed>>  $new
     * @return array<string, array<string, mixed>>
     */
    private function spendBudget(int $userId, array $new): array
    {
        if ($new === []) {
            return [];
        }
        $key = AnalyticsLimits::dailyKey($userId);
        $wanted = count($new);
        $spent = RateLimiter::increment($key, self::DAY, $wanted);
        $fits = max(0, min($wanted, (int) config('analytics.limits.events_per_day', 3000) - ($spent - $wanted)));
        if ($fits < $wanted) {
            RateLimiter::decrement($key, self::DAY, $wanted - $fits);
        }
        if ($fits === 0) {
            throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => max(1, RateLimiter::availableIn($key))]);
        }

        return array_slice($new, 0, $fits, true);
    }

    /**
     * One event as a row (without the batch's common columns), or null when it is off the allowlist.
     *
     * @return array<string, mixed>|null
     */
    private function row(mixed $event, CarbonImmutable $receivedAt, ?StartParam $start): ?array
    {
        if (!is_array($event) || ($event !== [] && array_is_list($event)) || array_diff(array_keys($event), self::EVENT_KEYS) !== []) {
            return null;
        }
        $type = AnalyticsEvent::fromName($event['name'] ?? null);
        $uid = $event['uid'] ?? null;
        $at = $event['at'] ?? null;
        if ($type === null || !is_string($uid) || !Str::isUuid($uid) || !is_int($at) || $at <= 0 || $at > 100_000_000_000_000) {
            return null;
        }
        // Client time (ms) clamped to [received - 24 h, received + 5 min]; older is dropped
        $occurredAt = CarbonImmutable::createFromTimestampMs($at, 'UTC')->startOfSecond();
        if ($occurredAt < $receivedAt->subDay()) {
            return null;
        }
        $occurredAt = $occurredAt->min($receivedAt->addMinutes(5));

        $spec = $type->spec();
        $ids = ['store_id' => null, 'product_id' => null, 'category_id' => null];
        foreach (array_keys($ids) as $field) {
            $value = $event[$field] ?? null;
            if ($value === null) {
                if ($spec['ids'][$field] ?? false) {
                    return null;
                }

                continue;
            }
            if (!array_key_exists($field, $spec['ids']) || !is_int($value) || $value < 1) {
                return null;
            }
            $ids[$field] = $value;
        }

        $props = $this->props($event['props'] ?? null, $spec['props']);
        if ($props === null) {
            return null;
        }
        if ($ids['category_id'] !== null) {
            $props = ['category_id' => $ids['category_id']] + $props;
        }
        if ($type === AnalyticsEvent::APP_OPEN) {
            // The kind of the envelope's start_param as the server reads it; without one, the app's own (validated)
            // kind. The campaign code is never kept: step 4 resolves it to campaign_id
            unset($props['campaign_code']);
            $props['start_kind'] = $start?->kind->value ?? ($props['start_kind'] ?? StartKind::NONE->value);
        }
        $orderUuid = null;
        if ($type === AnalyticsEvent::ORDER_PLACED) {
            $orderUuid = strtolower((string) $props['order_uuid']);
            unset($props['order_uuid']);
        }
        $encoded = $props === [] ? null : json_encode($props, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($encoded !== null && ($encoded === false || strlen($encoded) > self::PROPS_MAX_BYTES)) {
            return null;
        }

        $row = [
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
            'event_uid' => strtolower($uid),
            'event' => $type->value,
            'store_id' => $ids['store_id'],
            'product_id' => $ids['product_id'],
            'order_id' => null,
            'props' => $encoded,
        ];
        if ($orderUuid !== null) {
            $row['order_uuid'] = $orderUuid;
        }

        return $row;
    }

    /**
     * The allowlisted props, normalised; null when a key is off the list, a value breaks its rule or a required key
     * is missing.
     *
     * @param  array<string, string>  $rules
     * @return array<string, mixed>|null
     */
    private function props(mixed $props, array $rules): ?array
    {
        if ($props === null) {
            $props = [];
        }
        if (!is_array($props) || ($props !== [] && array_is_list($props))) {
            return null;
        }
        $out = [];
        foreach ($props as $key => $value) {
            $rule = $rules[$key] ?? null;
            if ($rule === null) {
                return null;
            }
            $normalised = self::prop(ltrim($rule, '!'), $value);
            if ($normalised === null) {
                return null;
            }
            $out[$key] = $normalised;
        }
        foreach ($rules as $key => $rule) {
            if (str_starts_with($rule, '!') && !array_key_exists($key, $out)) {
                return null;
            }
        }

        return $out;
    }

    private static function prop(string $rule, mixed $value): int|float|string|null
    {
        return match ($rule) {
            'start_kind' => is_string($value) && StartKind::tryFrom($value) !== null ? $value : null,
            'campaign_code' => is_string($value) && preg_match(StartParam::CAMPAIGN_CODE, $value) === 1 ? $value : null,
            // Rounded first, then held to the range: 0.0001 is 0.0, not a quantity
            'quantity' => self::decimal($value, 3, 0, 10000, aboveMin: true),
            'amount' => self::decimal($value, 2, 0, 10_000_000_000, aboveMin: false),
            'count' => is_int($value) && $value >= 0 && $value <= 100000 ? $value : null,
            'length' => is_int($value) && $value >= 0 && $value <= 256 ? $value : null,
            'id' => is_int($value) && $value >= 1 ? $value : null,
            'payment_method' => is_string($value) && in_array($value, AnalyticsEvent::PAYMENT_METHODS, true) ? $value : null,
            'order_type' => is_string($value) && in_array($value, AnalyticsEvent::orderTypes(), true) ? $value : null,
            'error_code' => is_string($value) && in_array($value, AnalyticsEvent::ERROR_CODES, true) ? $value : null,
            'uuid' => is_string($value) && Str::isUuid($value) ? $value : null,
            default => null,
        };
    }

    /** A JSON number rounded to $places, then within [$min (or above it), $max]; null otherwise */
    private static function decimal(mixed $value, int $places, int $min, int $max, bool $aboveMin): int|float|null
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }
        $rounded = is_int($value) ? $value : round($value, $places);

        return ($aboveMin ? $rounded > $min : $rounded >= $min) && $rounded <= $max ? $rounded : null;
    }

    /**
     * order_placed: the order's id and store from the server, only for the caller's own customer order (channel
     * telegram/mobile, not deleted) created within the 24 hours before the batch arrived; an unknown uuid, another
     * user's order, a POS order the user's account is on, an older order or an order that already has its
     * order_placed row (stored, or earlier in this batch) drops the event. One query of orders (by uuid, unique) and one
     * of analytics_events (by the partial order_id index, received between the earliest of the orders' created_at and
     * now: only the months in between, never a client time) per batch.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function resolveOrders(User $user, array $rows, CarbonImmutable $receivedAt): array
    {
        $uuids = array_values(array_unique(array_filter(array_column($rows, 'order_uuid'))));
        if ($uuids === []) {
            return $rows;
        }
        $orders = DB::table('orders')
            ->whereIn('uuid', $uuids)
            ->where('user_id', $user->getKey())
            ->whereIn('channel', OrderChannel::customerValues())
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $receivedAt->subDay()->format('Y-m-d H:i:s'))
            ->get(['id', 'uuid', 'store_id', 'created_at'])
            ->keyBy(fn (object $order) => strtolower((string) $order->uuid));

        $seen = [];
        if ($orders->isNotEmpty()) {
            // An earlier order_placed of these orders was received after the order was made; a few minutes' margin for
            // clocks of two app containers
            $since = CarbonImmutable::parse((string) $orders->min('created_at'), 'UTC')->subMinutes(5);
            $seen = array_fill_keys(array_map('intval', DB::table('analytics_events')
                ->where('event', AnalyticsEvent::ORDER_PLACED->value)
                ->whereIn('order_id', $orders->pluck('id')->map(fn ($id) => (int) $id)->all())
                ->whereBetween('received_at', [$since->format('Y-m-d H:i:s'), $receivedAt->format('Y-m-d H:i:s')])
                ->pluck('order_id')->all()), true);
        }

        foreach ($rows as $uid => $row) {
            if (!array_key_exists('order_uuid', $row)) {
                continue;
            }
            $order = $orders->get($row['order_uuid']);
            unset($row['order_uuid']);
            if ($order === null || isset($seen[(int) $order->id])) {
                unset($rows[$uid]);

                continue;
            }
            $seen[(int) $order->id] = true;
            $row['order_id'] = (int) $order->id;
            $row['store_id'] = (int) $order->store_id;
            $rows[$uid] = $row;
        }

        return $rows;
    }

    /** A release number or a git hash (APP_VERSION); anything else, of any type, is not kept. */
    private static function appVersion(mixed $version): ?string
    {
        return is_string($version) && strlen($version) <= 16 && preg_match(self::APP_VERSION, $version) === 1 ? $version : null;
    }
}
