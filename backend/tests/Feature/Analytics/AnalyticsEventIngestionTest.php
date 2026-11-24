<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsLimits;
use App\Domain\Analytics\AnalyticsPlatform;
use App\Domain\Realtime\ChangeRevision;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md 4.1, A.3, A.4): POST /api/v1/analytics/events, the Mini App's first-party
 * events. A signed-in customer's batch is stored as one INSERT into the partitioned analytics_events; everything not
 * on the allowlist is dropped (or the envelope refused), staff never count, nothing identifying beyond the token's
 * user id is kept, and nothing a polled screen reads moves.
 */
class AnalyticsEventIngestionTest extends TestCase
{
    use OrderLifecycleFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/analytics/events';

    private const IP = '203.0.113.77';

    private const USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) TelegramWebApp';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        // As the token guard loads it: the whole row, the role's column default included
        $this->customer->refresh();
        config(['analytics.enabled' => true]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    private function batch(array $events, array $envelope = []): array
    {
        return array_merge([
            'session_id' => '7d2f8a3c-1b4e-4c6a-9f0d-2e5b8c7a1f30',
            'app_version' => '1.4.2',
            'platform' => 'ios',
            'events' => $events,
        ], $envelope);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function event(string $name, array $extra = []): array
    {
        return array_merge(['uid' => (string) Str::uuid(), 'name' => $name, 'at' => (int) (now()->getTimestampMs())], $extra);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(array $body, ?User $as = null, string $ip = self::IP): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->customer, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', self::USER_AGENT)
            ->postJson(self::URL, $body);
    }

    private function rows(): \Illuminate\Support\Collection
    {
        return DB::table('analytics_events')->orderBy('id')->get();
    }

    public function test_a_valid_batch_is_stored_once_with_the_token_user_and_only_the_allowlisted_fields(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $events = [
            $this->event('app_open', ['props' => ['start_kind' => 'campaign', 'campaign_code' => 'tg_ads_1']]),
            $this->event('store_view', ['store_id' => $this->store->id]),
            $this->event('category_view', ['store_id' => $this->store->id, 'category_id' => 31]),
            $this->event('product_view', ['store_id' => $this->store->id, 'product_id' => $this->product->id]),
            $this->event('add_to_cart', ['store_id' => $this->store->id, 'product_id' => $this->product->id, 'props' => ['qty' => 2]]),
            $this->event('cart_view', ['store_id' => $this->store->id, 'props' => ['items' => 2, 'value' => 100000]]),
            $this->event('checkout_start', ['store_id' => $this->store->id, 'props' => ['items' => 2, 'value' => 100000, 'payment_method' => 'click', 'order_type' => 'delivery']]),
            $this->event('checkout_error', ['store_id' => $this->store->id, 'props' => ['code' => 'min_order']]),
            $this->event('order_placed', ['props' => ['order_uuid' => $order->uuid]]),
            $this->event('payment_redirect', ['store_id' => $this->store->id]),
            $this->event('search', ['props' => ['results' => 0, 'length' => 7]]),
            $this->event('promo_banner_click', ['props' => ['promo_id' => 5]]),
        ];

        $this->send($this->batch($events, ['start_param' => 'c_tg_ads_1']))
            ->assertStatus(202)
            ->assertExactJson(['success' => true, 'message' => '', 'data' => ['accepted' => 12, 'dropped' => 0]]);

        $rows = $this->rows();
        $this->assertCount(12, $rows);
        foreach ($rows as $i => $row) {
            $this->assertSame($this->customer->id, (int) $row->user_id);
            $this->assertSame('7d2f8a3c-1b4e-4c6a-9f0d-2e5b8c7a1f30', $row->session_id);
            $this->assertSame($events[$i]['uid'], $row->event_uid);
            $this->assertSame(AnalyticsEvent::fromName($events[$i]['name'])?->value, (int) $row->event);
            $this->assertSame(1, (int) $row->source);
            $this->assertSame(AnalyticsPlatform::IOS->value, (int) $row->platform);
            $this->assertSame('1.4.2', $row->app_version);
            $this->assertNull($row->campaign_id, 'campaigns arrive in step 4: no id to resolve yet');
        }
        // The server's start kind, never the raw param nor the client's campaign code
        $this->assertSame(['start_kind' => 'campaign'], json_decode((string) $rows[0]->props, true));
        $this->assertSame(['category_id' => 31], json_decode((string) $rows[2]->props, true));
        $this->assertSame((int) $this->product->id, (int) $rows[3]->product_id);
        $this->assertSame(['qty' => 2], json_decode((string) $rows[4]->props, true));
        $this->assertEquals(['payment_method' => 'click', 'order_type' => 'delivery', 'items' => 2, 'value' => 100000], json_decode((string) $rows[6]->props, true));
        // order_placed: the order's id and store from the server, the uuid is not kept
        $this->assertSame($order->id, (int) $rows[8]->order_id);
        $this->assertSame($this->store->id, (int) $rows[8]->store_id);
        $this->assertNull($rows[8]->props);
        $this->assertEquals(['results' => 0, 'length' => 7], json_decode((string) $rows[10]->props, true));

        // Nothing identifying beyond the user id: no IP, no User-Agent, no Telegram id, no raw start_param, no phone
        $columns = array_keys((array) $rows[0]);
        sort($columns);
        $this->assertSame(['app_version', 'campaign_id', 'event', 'event_uid', 'id', 'occurred_at', 'order_id', 'platform', 'product_id', 'props', 'received_at', 'session_id', 'source', 'store_id', 'user_id'], $columns);
        $dump = json_encode($rows->all());
        foreach ([self::IP, 'iPhone', 'c_tg_ads_1', 'tg_ads_1', (string) $this->customer->phone, $order->uuid] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, (string) $dump);
        }
    }

    public function test_a_replayed_batch_and_a_repeated_event_are_stored_once(): void
    {
        $view = $this->event('store_view', ['store_id' => $this->store->id]);
        $open = $this->event('app_open');
        $body = $this->batch([$open, $view, $view]);

        $this->send($body)->assertStatus(202)->assertJsonPath('data', ['accepted' => 3, 'dropped' => 0]);
        // The keepalive retry of the same batch (the first answer never reached the app): same answer, no new rows
        $this->send($body)->assertStatus(202)->assertJsonPath('data', ['accepted' => 3, 'dropped' => 0]);
        $this->assertSame(2, $this->rows()->count());

        // The same uid in another session is another event (ids are per session)
        $this->send($this->batch([$view], ['session_id' => (string) Str::uuid()]))->assertStatus(202);
        $this->assertSame(3, $this->rows()->count());
    }

    public function test_unknown_names_props_and_fields_and_wrong_values_are_dropped_silently(): void
    {
        $other = $this->makeCustomer();
        $othersOrder = $this->makeOrder(OrderStatus::NEW, ['user_id' => $other->id]);
        $events = [
            $this->event('store_view', ['store_id' => $this->store->id]), // the one kept
            $this->event('screen_shot'), // unknown name
            $this->event('search', ['props' => ['results' => 3, 'length' => 5, 'query' => 'osh 998901234567']]), // free text key
            $this->event('store_view', ['store_id' => (string) $this->store->id]), // not an integer
            $this->event('store_view'), // required store missing
            $this->event('store_view', ['store_id' => $this->store->id, 'user_id' => $other->id]), // a field off the list
            $this->event('product_view', ['store_id' => $this->store->id, 'product_id' => 1.5]),
            $this->event('add_to_cart', ['store_id' => $this->store->id, 'product_id' => $this->product->id, 'props' => ['qty' => 'two']]),
            $this->event('checkout_error', ['store_id' => $this->store->id, 'props' => ['code' => 'Karta raqami noto\'g\'ri']]),
            $this->event('checkout_start', ['store_id' => $this->store->id, 'props' => ['payment_method' => 'bitcoin']]),
            $this->event('app_open', ['props' => ['start_kind' => 'campaign', 'campaign_code' => 'Bad Code!']]),
            $this->event('order_placed', ['props' => ['order_uuid' => $othersOrder->uuid]]), // another user's order
            $this->event('order_placed', ['props' => ['order_uuid' => 'not-a-uuid']]),
            $this->event('store_view', ['uid' => 'not-a-uuid', 'store_id' => $this->store->id]),
            $this->event('store_view', ['at' => 'yesterday', 'store_id' => $this->store->id]),
            $this->event('store_view', ['store_id' => $this->store->id, 'props' => 'x']),
            'not an object',
        ];

        $this->send($this->batch($events))->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 16]);
        $this->assertSame([AnalyticsEvent::STORE_VIEW->value], $this->rows()->pluck('event')->map(fn ($e) => (int) $e)->all());
        $this->assertStringNotContainsString('998901234567', (string) json_encode($this->rows()->all()));
    }

    public function test_a_malformed_envelope_is_refused_with_422(): void
    {
        $ok = [$this->event('app_open')];
        $bad = [
            'no session' => ['session_id' => null],
            'session not a uuid' => ['session_id' => 'abc'],
            'no events' => ['events' => []],
            'events not a list' => ['events' => ['a' => $this->event('app_open')]],
            'too many events' => ['events' => array_map(fn () => $this->event('app_open'), range(1, 51))],
            'no platform' => ['platform' => null],
            'a user id from the body' => ['user_id' => 5],
            'free text' => ['search_text' => 'osh'],
            'start_param too long' => ['start_param' => str_repeat('a', 513)],
        ];
        foreach ($bad as $case => $override) {
            $this->send(array_filter($this->batch($ok, $override), fn ($v) => $v !== null))
                ->assertStatus(422)->assertJsonPath('success', false);
            $this->assertSame(0, $this->rows()->count(), $case);
        }

        // An unknown platform or an odd version is no reason to lose the batch: stored as unknown / without a version
        $this->send($this->batch($ok, ['platform' => 'tizen', 'app_version' => '<script>']))->assertStatus(202);
        $row = $this->rows()->first();
        $this->assertSame(AnalyticsPlatform::UNKNOWN->value, (int) $row->platform);
        $this->assertNull($row->app_version);
    }

    public function test_a_body_over_32_kb_is_refused_before_it_is_read(): void
    {
        $padding = str_repeat('a', 33000);
        $this->send($this->batch([$this->event('app_open')], ['start_param' => $padding]))
            ->assertStatus(413)
            ->assertJsonPath('data.reason', 'payload_too_large');
        $this->assertSame(0, $this->rows()->count());
    }

    public function test_events_older_than_a_day_are_dropped_and_future_ones_clamped(): void
    {
        $now = now()->startOfSecond();
        $events = [
            $this->event('store_view', ['store_id' => $this->store->id, 'at' => $now->copy()->subHours(25)->getTimestampMs()]),
            $this->event('store_view', ['store_id' => $this->store->id, 'at' => $now->copy()->subHours(23)->getTimestampMs()]),
            $this->event('store_view', ['store_id' => $this->store->id, 'at' => $now->copy()->addDays(3)->getTimestampMs()]),
        ];
        $this->send($this->batch($events))->assertStatus(202)->assertJsonPath('data', ['accepted' => 2, 'dropped' => 1]);

        $rows = $this->rows();
        $this->assertSame($now->copy()->subHours(23)->format('Y-m-d H:i:s'), $rows[0]->occurred_at);
        $this->assertSame($now->copy()->addMinutes(5)->format('Y-m-d H:i:s'), $rows[1]->occurred_at, 'a clock in the future is clamped to received + 5 min');
        $this->assertSame($now->format('Y-m-d H:i:s'), $rows[1]->received_at);
    }

    public function test_events_from_before_sign_in_link_to_the_signed_in_user_and_anonymous_calls_are_refused(): void
    {
        $session = (string) Str::uuid();
        $launch = now()->subSeconds(3);

        // No token yet: the endpoint takes nothing (the app keeps the events in memory until the sign-in has finished)
        $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->postJson(self::URL, $this->batch([$this->event('app_open')], ['session_id' => $session]))
            ->assertStatus(401);
        $this->assertSame(0, $this->rows()->count());

        // After the sign-in the buffered app_open (timed before it) and the later events carry the token's user
        $this->send($this->batch([$this->event('app_open', ['at' => $launch->getTimestampMs()])], ['session_id' => $session]))->assertStatus(202);
        $this->send($this->batch([$this->event('store_view', ['store_id' => $this->store->id])], ['session_id' => $session]))->assertStatus(202);

        $rows = $this->rows();
        $this->assertCount(2, $rows);
        $this->assertSame([$this->customer->id, $this->customer->id], $rows->pluck('user_id')->map(fn ($u) => (int) $u)->all());
        $this->assertSame([$session, $session], $rows->pluck('session_id')->all());
        $this->assertSame($launch->format('Y-m-d H:i:s'), $rows[0]->occurred_at);
    }

    public function test_store_staff_and_blocked_accounts_are_never_counted(): void
    {
        $body = $this->batch([$this->event('app_open'), $this->event('store_view', ['store_id' => $this->store->id])]);
        foreach ([$this->owner, $this->manager, $this->courier, $this->makeStaff(UserRole::CASHIER, $this->branch)] as $staff) {
            $this->send($body, $staff)->assertStatus(202)->assertJsonPath('data', ['accepted' => 0, 'dropped' => 2]);
        }
        // A customer account that is attached to a store anyway
        $odd = $this->makeCustomer()->refresh();
        DB::table('users')->where('id', $odd->id)->update(['store_id' => $this->store->id]);
        $this->send($body, $odd->refresh())->assertStatus(202)->assertJsonPath('data.accepted', 0);
        // A blocked customer: no token of it works
        $blocked = $this->makeCustomer();
        $token = $blocked->createToken('miniapp')->plainTextToken;
        DB::table('users')->where('id', $blocked->id)->update(['blocked_at' => now()]);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson(self::URL, $body)->assertStatus(401);

        $this->assertSame(0, $this->rows()->count());
    }

    public function test_switched_off_nothing_is_written_and_no_budget_is_used(): void
    {
        config(['analytics.enabled' => false]);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->send($this->batch([$this->event('app_open'), $this->event('store_view', ['store_id' => $this->store->id])]))
            ->assertStatus(202)->assertJsonPath('data', ['accepted' => 0, 'dropped' => 2]);

        $this->assertSame([], array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'analytics_events'))));
        $this->assertSame(0, RateLimiter::attempts(AnalyticsLimits::dailyKey($this->customer->id)));
        config(['analytics.enabled' => true]);
        $this->assertSame(0, $this->rows()->count());
    }

    public function test_one_insert_per_batch_no_change_token_moves_and_the_shared_api_limit_is_untouched(): void
    {
        $revisions = app(ChangeRevision::class);
        $order = $this->makeOrder(OrderStatus::NEW);
        $before = [$revisions->user($this->customer->id), $revisions->store($this->store->id), $revisions->branch($this->branch->id)];

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $events = array_map(fn () => $this->event('store_view', ['store_id' => $this->store->id]), range(1, 50));
        $events[49] = $this->event('order_placed', ['props' => ['order_uuid' => $order->uuid]]);
        $this->send($this->batch($events))->assertStatus(202)->assertJsonPath('data.accepted', 50);

        $inserts = array_values(array_filter($queries, fn (string $sql) => preg_match('/^insert into "analytics_events"/i', $sql) === 1));
        $this->assertCount(1, $inserts, 'all rows in one INSERT');
        $orderReads = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'from "orders"')));
        $this->assertCount(1, $orderReads, 'order_placed is resolved with one query per batch');
        $this->assertSame($before, [$revisions->user($this->customer->id), $revisions->store($this->store->id), $revisions->branch($this->branch->id)], 'no ETag moves');

        // Its own limiter only: the `api` limit that guards the order and payment calls is not spent by analytics
        $this->assertSame(0, RateLimiter::attempts(md5('api' . 'user:' . $this->customer->id)));
    }

    public function test_requests_are_limited_per_user_per_session_and_per_ip(): void
    {
        // The limits are checked in this order and each one passed is counted (Laravel's throttle), refused or not
        config(['analytics.limits.requests_per_minute_user' => 4, 'analytics.limits.requests_per_minute_session' => 2, 'analytics.limits.requests_per_minute_ip' => 4]);
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();

        // Per session: the third request of one session in a minute
        $this->send($this->batch([$this->event('app_open')], ['session_id' => $a]))->assertStatus(202);
        $this->send($this->batch([$this->event('app_open')], ['session_id' => $a]))->assertStatus(202);
        $this->send($this->batch([$this->event('app_open')], ['session_id' => $a]))->assertStatus(429)->assertHeader('Retry-After');
        // Per user: a new session does not reset the user's limit
        $this->send($this->batch([$this->event('app_open')], ['session_id' => $b]))->assertStatus(202);
        $this->send($this->batch([$this->event('app_open')], ['session_id' => (string) Str::uuid()]))->assertStatus(429);

        // Per IP: another customer from the same address gets the address's last request
        $other = $this->makeCustomer()->refresh();
        $this->send($this->batch([$this->event('app_open')], ['session_id' => (string) Str::uuid()]), $other)->assertStatus(202);
        $this->send($this->batch([$this->event('app_open')], ['session_id' => (string) Str::uuid()]), $other)->assertStatus(429);
        // ...and from another address it goes on
        $this->send($this->batch([$this->event('app_open')], ['session_id' => (string) Str::uuid()]), $other, '198.51.100.9')->assertStatus(202);

        $this->assertSame(5, $this->rows()->count());
    }

    public function test_the_daily_event_budget_per_user_keeps_what_fits_then_refuses(): void
    {
        config(['analytics.limits.events_per_day' => 5]);
        $three = fn () => $this->batch(array_map(fn () => $this->event('app_open'), range(1, 3)), ['session_id' => (string) Str::uuid()]);

        $this->send($three())->assertStatus(202)->assertJsonPath('data', ['accepted' => 3, 'dropped' => 0]);
        $this->send($three())->assertStatus(202)->assertJsonPath('data', ['accepted' => 2, 'dropped' => 1]);
        $this->send($three())->assertStatus(429);
        $this->assertSame(5, $this->rows()->count());

        // Staff batches and dropped events spend nothing
        $this->assertSame(5, RateLimiter::attempts(AnalyticsLimits::dailyKey($this->customer->id)));
        $this->travel(25)->hours();
        $this->send($three())->assertStatus(202)->assertJsonPath('data.accepted', 3);
    }

    public function test_no_limiter_key_holds_the_address(): void
    {
        config(['cache.limiter' => 'array']);
        $this->send($this->batch([$this->event('app_open')]))->assertStatus(202);

        $store = Cache::store('array')->getStore();
        $keys = array_keys((fn () => $this->storage)->call($store));
        $this->assertNotSame([], $keys);
        foreach ($keys as $key) {
            $this->assertStringNotContainsString(self::IP, $key);
            // The throttle middleware md5()s its keys; the address inside them is an HMAC with the app key, so the
            // stored key cannot be turned back into the address by hashing every IPv4 address
            $this->assertNotSame(md5('analytics-events' . 'ip:' . self::IP), $key);
            $this->assertNotSame(md5('analytics-events' . self::IP), $key);
        }
        $this->assertStringNotContainsString(self::IP, AnalyticsLimits::ipKey(self::IP));
        $this->assertSame(AnalyticsLimits::ipKey(self::IP), AnalyticsLimits::ipKey(self::IP));
        $this->assertNotSame(AnalyticsLimits::ipKey(self::IP), AnalyticsLimits::ipKey('198.51.100.9'));
    }

    public function test_a_month_not_attached_yet_lands_in_default_without_any_ddl(): void
    {
        $ddl = [];
        DB::listen(function (QueryExecuted $query) use (&$ddl) {
            if (preg_match('/^\s*(create|alter|drop|lock)\b/i', $query->sql) === 1) {
                $ddl[] = $query->sql;
            }
        });
        // Past the months db:partitions:ensure has attached (now + 3)
        $this->travelTo(now()->addMonths(6));
        $this->send($this->batch([$this->event('app_open')]))->assertStatus(202)->assertJsonPath('data.accepted', 1);

        $this->assertSame([], $ddl);
        $this->assertSame('analytics_events_default', DB::selectOne('SELECT tableoid::regclass::text AS p FROM analytics_events')->p);
    }
}
