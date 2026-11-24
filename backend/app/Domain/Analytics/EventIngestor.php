<?php

namespace App\Domain\Analytics;

use App\Enums\OrderChannel;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
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
 *   the platform code, the app version, the ids the event is about and allowlisted props. Never an IP address, a
 *   User-Agent, a Telegram id, the raw start_param or any free text.
 * - Staff (any role but customer, an account tied to a store, a RIZQIM team account) and blocked accounts are never
 *   counted (owner decision 9); with config analytics.enabled off nothing is written at all.
 * - Idempotent: an event is (session_id, event_uid); a repeated one in a batch, or a replayed batch (a retry whose
 *   first answer was lost), is answered as accepted and not stored again. The check and the INSERT run under a
 *   transaction-scoped advisory lock of the session, so two copies of one batch arriving together store it once.
 * - Cost: at most one indexed read of orders (order_placed), one indexed read of the session's last day of events
 *   and one INSERT of every row. analytics_events is RevisionMap NONE: no ETag moves; no queue, no cache write other
 *   than the per-user event budget (AnalyticsLimits). A month not attached yet lands in DEFAULT: no DDL here.
 */
final class EventIngestor
{
    /** The event fields the Mini App may send */
    private const EVENT_KEYS = ['uid', 'name', 'at', 'store_id', 'product_id', 'category_id', 'props'];

    private const PROPS_MAX_BYTES = 512;

    /**
     * @param  array{session_id: string, platform: string, app_version?: string|null, start_param?: string|null, events: list<mixed>}  $batch
     * @return array{accepted: int, dropped: int}
     */
    public function ingest(User $user, array $batch): array
    {
        $total = count($batch['events']);
        if (!config('analytics.enabled') || self::excluded($user)) {
            return ['accepted' => 0, 'dropped' => $total];
        }

        $receivedAt = CarbonImmutable::now('UTC')->startOfSecond();
        $session = strtolower($batch['session_id']);
        $start = array_key_exists('start_param', $batch) && $batch['start_param'] !== null ? StartParam::parse($batch['start_param']) : null;
        $common = [
            'received_at' => $receivedAt->format('Y-m-d H:i:s'),
            'session_id' => $session,
            'user_id' => $user->getKey(),
            'source' => AnalyticsEvent::SOURCE_MINIAPP,
            'platform' => AnalyticsPlatform::fromTelegram($batch['platform'])->value,
            'app_version' => self::appVersion($batch['app_version'] ?? null),
            // Campaign links arrive in step 4 (campaigns table): until then there is no id to resolve a code to
            'campaign_id' => null,
        ];

        $rows = [];
        $copies = [];
        $orderUuids = [];
        foreach ($batch['events'] as $event) {
            $row = $this->row($event, $receivedAt, $start);
            if ($row === null) {
                continue;
            }
            // A repeated event in the batch is the same event: stored once, each copy answered as accepted
            $rows[$row['event_uid']] ??= $row;
            $copies[$row['event_uid']] = ($copies[$row['event_uid']] ?? 0) + 1;
            if (isset($row['order_uuid'])) {
                $orderUuids[$row['order_uuid']] = true;
            }
        }
        $rows = $this->resolveOrders($user, $rows, array_keys($orderUuids));
        if ($rows === []) {
            return ['accepted' => 0, 'dropped' => $total];
        }

        $kept = DB::transaction(function () use ($user, $session, $receivedAt, $rows, $common): array {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['analytics_events:' . $session]);
            // Already stored (a replay): accepted, not again. Events older than a day are dropped, so a day (and an
            // hour for a client clock that ran ahead) back is all a replay can reach
            $stored = DB::table('analytics_events')
                ->where('session_id', $session)
                ->whereIn('event_uid', array_keys($rows))
                ->where('received_at', '>=', $receivedAt->subHours(25)->format('Y-m-d H:i:s'))
                ->pluck('event_uid')->all();
            $new = array_diff_key($rows, array_flip($stored));
            // What the user's daily budget still takes, in the batch's order
            $new = array_slice($new, 0, $this->budget($user, $new === []), true);
            if ($new !== []) {
                DB::table('analytics_events')->insert(array_values(array_map(fn (array $row) => $common + $row, $new)));
                RateLimiter::increment(AnalyticsLimits::dailyKey($user->getKey()), 86400, count($new));
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

    /**
     * How many new events the user's daily budget still takes; none left: 429 (unless there is nothing new to store).
     */
    private function budget(User $user, bool $nothingNew): int
    {
        $key = AnalyticsLimits::dailyKey($user->getKey());
        $left = max(0, (int) config('analytics.limits.events_per_day', 3000) - RateLimiter::attempts($key));
        if ($left === 0 && !$nothingNew) {
            throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => max(1, RateLimiter::availableIn($key))]);
        }

        return $left;
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
            'quantity' => (is_int($value) || is_float($value)) && $value > 0 && $value <= 10000 ? (is_int($value) ? $value : round($value, 3)) : null,
            'amount' => (is_int($value) || is_float($value)) && $value >= 0 && $value <= 10_000_000_000 ? (is_int($value) ? $value : round($value, 2)) : null,
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

    /**
     * order_placed: the order's id and store from the server, only for the caller's own customer order (one query
     * for the batch); an unknown uuid, another user's order or a POS order the user took as staff drops the event.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  list<string>  $uuids
     * @return array<string, array<string, mixed>>
     */
    private function resolveOrders(User $user, array $rows, array $uuids): array
    {
        $orders = $uuids === [] ? collect() : DB::table('orders')
            ->whereIn('uuid', $uuids)
            ->where('user_id', $user->getKey())
            ->whereIn('channel', OrderChannel::customerValues())
            ->whereNull('deleted_at')
            ->get(['id', 'uuid', 'store_id'])
            ->keyBy(fn (object $order) => strtolower((string) $order->uuid));

        foreach ($rows as $uid => $row) {
            if (!array_key_exists('order_uuid', $row)) {
                continue;
            }
            $order = $orders->get($row['order_uuid']);
            unset($row['order_uuid']);
            if ($order === null) {
                unset($rows[$uid]);

                continue;
            }
            $row['order_id'] = (int) $order->id;
            $row['store_id'] = (int) $order->store_id;
            $rows[$uid] = $row;
        }

        return $rows;
    }

    /** A version like `1.4.2` or `2026.11.24+5`; anything else is not kept. */
    private static function appVersion(mixed $version): ?string
    {
        return is_string($version) && preg_match('/^[0-9A-Za-z._+-]{1,16}$/', $version) === 1 ? $version : null;
    }
}
