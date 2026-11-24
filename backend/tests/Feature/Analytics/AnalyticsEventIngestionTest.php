<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsLimits;
use App\Domain\Analytics\AnalyticsPlatform;
use App\Domain\Realtime\ChangeRevision;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Partitions\PartitionManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Feature\AccessFixtures;
use Tests\Feature\AdminFixtures;
use Tests\TestCase;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md 4.1, A.3, A.4): POST /api/v1/analytics/events, the Mini App's first-party
 * events. A signed-in customer's batch is stored as one INSERT into the partitioned analytics_events; everything not
 * on the allowlist is dropped (or the envelope refused), staff never count, nothing identifying beyond the token's
 * user id is kept, and nothing a polled screen reads moves.
 */
class AnalyticsEventIngestionTest extends TestCase
{
    use AccessFixtures;
    use AdminFixtures;
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
            // The nil UUID is what a client without a session generator sends: every such app would share one session
            'the nil uuid as the session' => ['session_id' => '00000000-0000-0000-0000-000000000000'],
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

        // An unknown platform or an odd version is no reason to lose the batch (docs/API.md: never refused): stored as
        // unknown / without a version, whatever its type or length
        $odd = [
            ['platform' => 'tizen', 'app_version' => '<script>'],
            ['platform' => str_repeat('p', 200), 'app_version' => str_repeat('1', 200)],
            ['platform' => 7, 'app_version' => 142],
            ['platform' => ['ios'], 'app_version' => ['1.4.2']],
            ['platform' => true, 'app_version' => false],
        ];
        foreach ($odd as $i => $override) {
            $this->send($this->batch([$this->event('app_open')], $override + ['session_id' => (string) Str::uuid()]))
                ->assertStatus(202)->assertJsonPath('data.accepted', 1);
            $row = $this->rows()->last();
            $this->assertSame(AnalyticsPlatform::UNKNOWN->value, (int) $row->platform, "case $i");
            $this->assertNull($row->app_version, "case $i");
        }
    }

    public function test_app_version_is_kept_only_in_a_strict_version_shape(): void
    {
        $versions = [
            // The shapes a build really has: a release number, and the Mini App image's VITE_SENTRY_RELEASE (the
            // first 12 hex digits of the commit, .github/workflows/deploy.yml) or a shorter git hash
            '1.4.2' => '1.4.2',
            '0.0.0' => '0.0.0',
            '2.10' => '2.10',
            '1.4.2+57' => '1.4.2+57',
            '1707bfa0a3c2' => '1707bfa0a3c2',
            'abc1234' => 'abc1234',
            // Anything that could be a phone number, a name or other free text is not kept
            '+998901234567' => null,
            '998901234567' => null,
            '99890123456' => null,
            '1.4.2-998901234567' => null,
            'Aziz' => null,
            '1707BFA0A3C2' => null,
            '1.4.2+998901234567' => null,
            '' => null,
        ];
        foreach ($versions as $sent => $kept) {
            $this->send($this->batch([$this->event('app_open')], ['session_id' => (string) Str::uuid(), 'app_version' => (string) $sent]))
                ->assertStatus(202)->assertJsonPath('data.accepted', 1);
            $this->assertSame($kept, $this->rows()->last()->app_version, "app_version '$sent'");
        }
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

    public function test_switched_off_no_event_is_stored_and_no_daily_counter_moves_but_the_request_limits_still_count(): void
    {
        config(['analytics.enabled' => false]);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $session = '7d2f8a3c-1b4e-4c6a-9f0d-2e5b8c7a1f30';
        $this->send($this->batch([$this->event('app_open'), $this->event('store_view', ['store_id' => $this->store->id])]))
            ->assertStatus(202)->assertJsonPath('data', ['accepted' => 0, 'dropped' => 2]);

        // Untouched: the table (not even a read), the daily event budget and the daily session count
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'analytics_events') || str_contains($sql, 'advisory'))));
        $this->assertSame(0, RateLimiter::attempts(AnalyticsLimits::dailyKey($this->customer->id)));
        $this->assertSame(0, RateLimiter::attempts(AnalyticsLimits::sessionsKey($this->customer->id)));
        // The route's per-request limiter runs before the controller, switch or no switch: its user, session and
        // address counters (the throttle middleware's md5 of limiter name + key) are written
        $this->assertSame(1, RateLimiter::attempts(md5('analytics-events' . 'user:' . $this->customer->id)));
        $this->assertSame(1, RateLimiter::attempts(md5('analytics-events' . 'session:' . $session)));
        $this->assertSame(1, RateLimiter::attempts(md5('analytics-events' . AnalyticsLimits::ipKey(self::IP))));
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

    public function test_a_replay_is_found_however_far_ahead_the_client_clock_was(): void
    {
        // A phone whose clock runs three days ahead: its events are kept (occurred_at clamped to received + 5 min)
        $ahead = $this->event('store_view', ['store_id' => $this->store->id, 'at' => now()->addDays(3)->getTimestampMs()]);
        $body = $this->batch([$ahead]);
        $this->send($body)->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 0]);

        // Its keepalive retry arrives 26 hours later: `at` is still within the day, the stored copy is older than a
        // day of received_at. Accepted (the same answer) and not stored again
        $this->travel(26)->hours();
        $this->send($body)->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 0]);
        $this->assertSame(1, $this->rows()->count());
    }

    public function test_another_user_sending_the_same_session_and_uid_gets_an_event_of_their_own(): void
    {
        $view = $this->event('store_view', ['store_id' => $this->store->id]);
        $this->send($this->batch([$view]))->assertStatus(202)->assertJsonPath('data.accepted', 1);

        // B copies A's session and event ids: never told "accepted" for A's row. B's event is B's own, in B's own
        // (user, session); A's row is untouched and A's replay is still recognised
        $other = $this->makeCustomer()->refresh();
        $this->send($this->batch([$view]), $other)->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 0]);
        $this->send($this->batch([$view]), $other)->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 0]);
        $this->send($this->batch([$view]))->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 0]);

        $rows = $this->rows();
        $this->assertSame([$this->customer->id, $other->id], $rows->pluck('user_id')->map(fn ($u) => (int) $u)->all());
        $this->assertSame([$view['uid'], $view['uid']], $rows->pluck('event_uid')->all());
    }

    public function test_the_replay_lookup_is_an_index_probe_per_partition_without_a_time_window(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query;
        });
        $this->send($this->batch([$this->event('app_open')]))->assertStatus(202);

        $lookups = array_values(array_filter($queries, fn (QueryExecuted $q) => preg_match('/^select .* from "analytics_events"/i', $q->sql) === 1 && str_contains($q->sql, '"event_uid" in')));
        $this->assertCount(1, $lookups);
        $this->assertStringNotContainsString('received_at', $lookups[0]->sql, 'no window a skewed client clock can get out of');
        $this->assertStringContainsString('"user_id" = ?', $lookups[0]->sql);

        DB::statement('SET LOCAL enable_seqscan = off');
        $plan = collect(DB::select('EXPLAIN ' . $lookups[0]->sql, $lookups[0]->bindings))->pluck('QUERY PLAN')->implode("\n");
        $this->assertStringContainsString('analytics_events_default', $plan);
        $this->assertDoesNotMatchRegularExpression('/Seq Scan/', $plan);
        // Every month (and DEFAULT) by its (session_id, event_uid) index
        $probes = preg_match_all('/(?:Bitmap Index Scan on|Index (?:Only )?Scan using) analytics_events_\w+?_session_id_event_uid_idx/', $plan);
        $this->assertSame(count(app(PartitionManager::class)->partitions('analytics_events')), $probes, $plan);
    }

    public function test_the_daily_budget_is_spent_atomically_by_batches_of_two_sessions_at_once(): void
    {
        config(['analytics.limits.events_per_day' => 5]);
        $three = fn () => array_map(fn () => $this->event('app_open'), range(1, 3));
        $second = $this->batch($three(), ['session_id' => (string) Str::uuid()]);
        $ingestor = app(\App\Domain\Analytics\EventIngestor::class);
        $answers = [];

        // A batch of another session of the same user is stored while the first one is being written (the second
        // session's lock is not the first one's): it must see the budget the first one has taken
        $fired = false;
        DB::listen(function (QueryExecuted $query) use (&$fired, &$answers, $ingestor, $second) {
            if (!$fired && preg_match('/^insert into "analytics_events"/i', $query->sql) === 1) {
                $fired = true;
                $answers[] = $ingestor->ingest($this->customer, $second);
            }
        });
        $this->send($this->batch($three(), ['session_id' => (string) Str::uuid()]))->assertStatus(202)->assertJsonPath('data', ['accepted' => 3, 'dropped' => 0]);

        $this->assertSame([['accepted' => 2, 'dropped' => 1]], $answers);
        $this->assertSame(5, $this->rows()->count(), 'never more than the day\'s budget');
        $this->assertSame(5, RateLimiter::attempts(AnalyticsLimits::dailyKey($this->customer->id)), 'what did not fit is given back');
    }

    public function test_sessions_beyond_the_daily_cap_per_user_are_dropped_without_any_sql(): void
    {
        config(['analytics.limits.sessions_per_day' => 2]);
        $sessions = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        $send = fn (string $session, int $events = 1) => $this->send($this->batch(array_map(fn () => $this->event('app_open'), range(1, $events)), ['session_id' => $session]));

        $send($sessions[0])->assertStatus(202)->assertJsonPath('data.accepted', 1);
        $send($sessions[1], 2)->assertStatus(202)->assertJsonPath('data.accepted', 2);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });
        // The third new session of the day: dropped silently, and no query of the table or of orders for it (the cap
        // is the limiter's cache, checked under the session's lock so two first batches of a session count it once)
        $send($sessions[2], 3)->assertStatus(202)->assertJsonPath('data', ['accepted' => 0, 'dropped' => 3]);
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql) => preg_match('/analytics_events|"orders"/', $sql) === 1)));
        $send($sessions[2])->assertStatus(202)->assertJsonPath('data', ['accepted' => 0, 'dropped' => 1]);

        // The sessions within the cap go on; replays and another user are not affected
        $send($sessions[0])->assertStatus(202)->assertJsonPath('data.accepted', 1);
        $send($sessions[1])->assertStatus(202)->assertJsonPath('data.accepted', 1);
        $this->send($this->batch([$this->event('app_open')], ['session_id' => $sessions[2]]), $this->makeCustomer()->refresh())
            ->assertStatus(202)->assertJsonPath('data.accepted', 1);
        $this->assertSame(6, $this->rows()->count());
        $this->assertSame(0, DB::table('analytics_events')->where('session_id', $sessions[2])->where('user_id', $this->customer->id)->count());

        // A day later the count starts again
        $this->travel(25)->hours();
        $send((string) Str::uuid())->assertStatus(202)->assertJsonPath('data.accepted', 1);
    }

    public function test_order_placed_is_stored_once_per_order_and_only_for_a_recent_customer_order_of_the_caller(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $placed = fn (string $uuid) => $this->event('order_placed', ['props' => ['order_uuid' => $uuid]]);

        // Two order_placed events (two uids) of one order in a batch: one stored
        $first = $this->batch([$placed($order->uuid), $placed($order->uuid)]);
        $this->send($first)->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 1]);
        // ...and in a later batch of another session: already stored, dropped
        $this->send($this->batch([$placed($order->uuid)], ['session_id' => (string) Str::uuid()]))
            ->assertStatus(202)->assertJsonPath('data', ['accepted' => 0, 'dropped' => 1]);

        // An order made more than 24 hours before the batch arrives, a POS order of the till that carries the
        // customer's account, a deleted order: dropped
        $old = $this->makeOrder(OrderStatus::NEW);
        DB::table('orders')->where('id', $old->id)->update(['created_at' => now()->subHours(25)]);
        $pos = $this->makeOrder(OrderStatus::NEW, ['channel' => 'pos_hall']);
        $deleted = $this->makeOrder(OrderStatus::NEW);
        $deleted->delete();
        $recent = $this->makeOrder(OrderStatus::NEW);
        DB::table('orders')->where('id', $recent->id)->update(['created_at' => now()->subHours(23)]);
        $this->send($this->batch([$placed($old->uuid), $placed($pos->uuid), $placed($deleted->uuid), $placed($recent->uuid)], ['session_id' => (string) Str::uuid()]))
            ->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 3]);

        $this->assertSame([$order->id, $recent->id], $this->rows()->pluck('order_id')->map(fn ($o) => (int) $o)->all());

        // The replay of the first batch: the same answer, nothing stored again
        $this->send($first)->assertStatus(202)->assertJsonPath('data', ['accepted' => 1, 'dropped' => 1]);
        $this->assertSame(2, $this->rows()->count());
    }

    public function test_the_stored_order_check_is_an_index_probe_of_the_months_since_the_order(): void
    {
        // Mid-month (of the months the migration attached), so the window never crosses a month's start
        $this->travelTo(PartitionManager::monthStart(now())->addDays(14)->setTime(12, 0));
        $order = $this->makeOrder(OrderStatus::NEW);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query;
        });
        $this->send($this->batch([$this->event('order_placed', ['props' => ['order_uuid' => $order->uuid]])]))->assertStatus(202)->assertJsonPath('data.accepted', 1);

        $checks = array_values(array_filter($queries, fn (QueryExecuted $q) => preg_match('/^select .* from "analytics_events"/i', $q->sql) === 1 && str_contains($q->sql, '"order_id" in')));
        $this->assertCount(1, $checks);
        $this->assertStringContainsString('"received_at" between ? and ?', $checks[0]->sql, 'bounded by server times: the order\'s creation and now');

        DB::statement('SET LOCAL enable_seqscan = off');
        $plan = collect(DB::select('EXPLAIN ' . $checks[0]->sql, $checks[0]->bindings))->pluck('QUERY PLAN')->implode("\n");
        $this->assertDoesNotMatchRegularExpression('/Seq Scan/', $plan);
        // Only the months between the order's creation and now are probed, by their order_id index: an order of today
        // is this month alone (DEFAULT is pruned too, the window lies inside an attached month; two near a month's end)
        $probes = '/(?:Bitmap Index Scan on|Index (?:Only )?Scan using) (analytics_events_\w+?)_order_id_idx/';
        preg_match_all($probes, $plan, $m);
        $this->assertSame([PartitionManager::monthName('analytics_events', PartitionManager::monthStart(now()))], $m[1], $plan);
    }

    public function test_a_quantity_is_rounded_before_it_must_be_above_zero(): void
    {
        $add = fn (float|int $qty) => $this->event('add_to_cart', ['store_id' => $this->store->id, 'product_id' => $this->product->id, 'props' => ['qty' => $qty]]);
        $this->send($this->batch([$add(0.0001), $add(0.0005), $add(1.25), $add(10000.0004)]))
            ->assertStatus(202)->assertJsonPath('data', ['accepted' => 3, 'dropped' => 1]);
        $this->assertSame([['qty' => 0.001], ['qty' => 1.25], ['qty' => 10000.0]], $this->rows()->map(fn ($r) => json_decode((string) $r->props, true))->all());
    }

    public function test_the_per_address_limit_allows_a_carrier_nat(): void
    {
        $request = \Illuminate\Http\Request::create(self::URL, 'POST', server: ['REMOTE_ADDR' => self::IP]);
        $limits = AnalyticsLimits::forRequest($request);
        $this->assertCount(1, $limits);
        $this->assertSame(600, $limits[0]->maxAttempts);
        $this->assertSame(AnalyticsLimits::ipKey(self::IP), $limits[0]->key);
    }

    public function test_terminal_admin_and_platform_tokens_are_refused(): void
    {
        $body = $this->batch([$this->event('app_open')]);
        $superadmin = $this->makeMember(\App\Enums\PlatformRole::SUPERADMIN);
        $platform = $this->makeMember(\App\Enums\PlatformRole::OPERATOR, false);
        $tokens = [
            // A paired till's own token (auth:sanctum is for users only)
            'terminal' => $this->pairTerminal($this->owner)['token'],
            // A control-center session (the admin guard's token)
            'admin session' => $this->signIn($superadmin),
            // A RIZQIM team account's store-level token
            'platform account' => $platform->createToken('mini-app')->plainTextToken,
        ];
        foreach ($tokens as $kind => $token) {
            $this->withServerVariables(['REMOTE_ADDR' => self::IP]);
            $this->bearerJson('POST', self::URL, $token, $body)->assertStatus(401);
        }
        $this->assertSame(0, $this->rows()->count());
        // The customer's own token works
        $this->bearerJson('POST', self::URL, $this->customer->createToken('mini-app')->plainTextToken, $body)->assertStatus(202)->assertJsonPath('data.accepted', 1);
    }
}
