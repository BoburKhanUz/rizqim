<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\ChangeRevision;
use App\Enums\UserRole;
use App\Http\Middleware\ConditionalGet;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Marketplace\MarketplaceFixtures;
use Tests\Feature\PosQueue\PosQueueFixtures;
use Tests\TestCase;

/**
 * Review M2 R2-1 and R2-2 (ConditionalGet): nothing a caller puts in If-None-Match reaches the revision store before
 * a MAC over the request context, the expiry and the scope names has been checked; the 304 path never creates a
 * token; a tag only names scopes the server issued for this actor and URL; at most two candidates are looked at.
 * X-Revision / X-Revision-Scope only reach an actor entitled to follow that scope.
 */
class ConditionalGetForgeryTest extends TestCase
{
    use MarketplaceFixtures;
    use PosQueueFixtures;
    use RefreshDatabase;

    /** @var list<string> revision-store keys read or written while recording */
    private array $touched = [];

    private bool $recording = false;

    protected function setUp(): void
    {
        parent::setUp();
        $record = function (string $key): void {
            if ($this->recording && str_starts_with($key, 'rev:')) {
                $this->touched[] = $key;
            }
        };
        Event::listen(RetrievingKey::class, fn (RetrievingKey $e) => $record($e->key));
        Event::listen(WritingKey::class, fn (WritingKey $e) => $record($e->key));
        Event::listen(RetrievingManyKeys::class, function (RetrievingManyKeys $e) use ($record) {
            foreach ($e->keys as $key) {
                $record((string) $key);
            }
        });
        Event::listen(WritingManyKeys::class, function (WritingManyKeys $e) use ($record) {
            foreach ($e->keys as $key) {
                $record((string) $key);
            }
        });
    }

    /**
     * @return list<string> the revision keys the request read or wrote
     */
    private function revisionKeys(callable $request): array
    {
        $this->touched = [];
        $this->recording = true;
        try {
            $request();
        } finally {
            $this->recording = false;
        }

        return $this->touched;
    }

    private function rawToken(string $key): mixed
    {
        return Cache::store((string) config('realtime.revision_store'))->getStore()->get($key);
    }

    /**
     * The reviewer's header: as many candidates as fit in 4096 bytes, 64 caller-chosen branch ids each, a far expiry
     * and a garbage MAC.
     */
    private static function forged(int $base): string
    {
        $candidates = [];
        $n = $base;
        while (true) {
            $scopes = [];
            for ($i = 0; $i < 64; $i++) {
                $scopes[] = 'b' . ($n++);
            }
            $tag = '"c1.' . base_convert((string) (time() + 10 * 365 * 86400), 10, 36) . '.' . implode('-', $scopes) . '.' . str_repeat('A', 22) . '"';
            if (strlen(implode(',', [...$candidates, $tag])) > 4096) {
                break;
            }
            $candidates[] = $tag;
        }

        return implode(',', $candidates);
    }

    private function customerToken(): array
    {
        $user = User::create(['name' => 'Mijoz', 'role' => UserRole::CUSTOMER, 'telegram_id' => '777001']);

        return [$user, $user->createToken('miniapp')->plainTextToken];
    }

    private function poll(string $token, string $uri, ?string $etag = null): TestResponse
    {
        $this->app['auth']->forgetGuards(); // each request authenticates its own bearer
        $this->withHeader('Authorization', 'Bearer ' . $token);
        $this->withHeader('If-None-Match', $etag ?? '');

        return $this->getJson('/api/v1' . $uri);
    }

    public function test_a_forged_if_none_match_reads_and_creates_no_revision_key(): void
    {
        [$user, $token] = $this->customerToken();
        $this->poll($token, '/notifications')->assertOk(); // the user's token exists
        $plain = $this->revisionKeys(fn () => $this->poll($token, '/notifications')->assertOk());

        $header = self::forged(1_000_000);
        $this->assertGreaterThan(2, substr_count($header, ','));
        $forged = $this->revisionKeys(fn () => $this->poll($token, '/notifications', $header)->assertOk());

        // The forged request touches exactly what a request without If-None-Match touches: its own user token
        $this->assertSame($plain, $forged);
        $this->assertSame(['rev:u:' . $user->id], array_values(array_unique($forged)));
        $this->assertNull($this->rawToken('rev:b:1000000'));
        $this->assertNull($this->rawToken('rev:b:1000063'));
    }

    public function test_a_tag_naming_scopes_the_server_did_not_issue_for_this_request_is_rejected_before_any_read(): void
    {
        [$user, $token] = $this->customerToken();
        $tag = (string) $this->poll($token, '/notifications')->assertOk()->headers->get('ETag');
        $this->poll($token, '/notifications', $tag)->assertStatus(304);

        // Another scope name with the same MACs: refused without reading u:<other> (or anything)
        $other = User::create(['name' => 'Boshqa', 'role' => UserRole::CUSTOMER, 'telegram_id' => '777002']);
        $swapped = str_replace('.u' . $user->id . '.', '.u' . $other->id . '.', $tag);
        $this->assertNotSame($tag, $swapped);
        $keys = $this->revisionKeys(fn () => $this->poll($token, '/notifications', $swapped)->assertOk());
        $this->assertSame(['rev:u:' . $user->id], array_values(array_unique($keys)), 'only the 200 path reads its own token');
        $this->assertNull($this->rawToken('rev:u:' . $other->id));

        // A real tag of another actor, or of another URL: refused before any read
        $otherToken = $other->createToken('miniapp')->plainTextToken;
        $keys = $this->revisionKeys(fn () => $this->poll($otherToken, '/notifications', $tag)->assertOk());
        $this->assertSame(['rev:u:' . $other->id], array_values(array_unique($keys)));
    }

    public function test_the_304_path_never_creates_a_token_and_looks_at_two_candidates_at_most(): void
    {
        [$user, $token] = $this->customerToken();
        $tag = (string) $this->poll($token, '/notifications')->assertOk()->headers->get('ETag');

        // Two garbage candidates first: the valid third one is not looked at
        $this->poll($token, '/notifications', '"x", "y", ' . $tag)->assertOk();
        $this->poll($token, '/notifications', '"x", ' . $tag)->assertStatus(304);

        // The token expired (or Redis was flushed): a valid tag finds nothing and issues nothing. The middleware alone
        // (resolver `none`, a body that declares nothing), so only the conditional check can have touched the key.
        $middleware = app(ConditionalGet::class);
        $request = function (?string $etag) use ($user): Request {
            $request = Request::create('/api/v1/probe', 'GET');
            $request->setUserResolver(fn () => $user);
            if ($etag !== null) {
                $request->headers->set('If-None-Match', $etag);
            }

            return $request;
        };
        $issued = $middleware->handle($request(null), function (Request $r) use ($user) {
            ConditionalGet::depends($r, 'user', (int) $user->id);

            return new Response('{}', 200);
        }, 'none', '60');
        $probeTag = (string) $issued->headers->get('ETag');
        $this->assertStringStartsWith('"', $probeTag);
        $this->assertSame(304, $middleware->handle($request($probeTag), fn () => new Response('{}', 200), 'none', '60')->getStatusCode());

        Cache::store((string) config('realtime.revision_store'))->forget('rev:u:' . $user->id);
        $answer = $middleware->handle($request($probeTag), fn () => new Response('{}', 200), 'none', '60');
        $this->assertSame(200, $answer->getStatusCode());
        $this->assertNull($this->rawToken('rev:u:' . $user->id), 'the 304 check issued no token');
    }

    public function test_an_overlong_tag_is_ignored(): void
    {
        [, $token] = $this->customerToken();
        $tag = (string) $this->poll($token, '/notifications')->assertOk()->headers->get('ETag');
        $long = substr($tag, 0, -1) . str_repeat('A', 3000) . '"';
        $keys = $this->revisionKeys(fn () => $this->poll($token, '/notifications', $long)->assertOk());
        $this->assertCount(1, array_unique($keys));
    }

    // --- R2-2: who gets X-Revision ---------------------------------------------------------------------------------

    public function test_a_store_token_is_never_named_to_a_customer(): void
    {
        [$user, $token] = $this->customerToken();
        $store = $this->marketStore('Osh markazi', 41.3110, 69.2400, 5);
        $this->marketProduct($store, 'Osh', '30000');

        // /catalog/availability's body has exactly one scope (the store): still no header for a customer
        foreach (["/catalog/availability?store_id={$store->uuid}", "/catalog?store_id={$store->uuid}", '/stores?lat=41.3110&lng=69.2400'] as $uri) {
            $signed = $this->poll($token, $uri)->assertOk();
            $this->assertNotNull($signed->headers->get('ETag'), $uri);
            $this->assertNull($signed->headers->get('X-Revision'), "{$uri}: a customer");
            $this->assertNull($signed->headers->get('X-Revision-Scope'), "{$uri}: a customer");
        }

        // A customer's own scope is theirs to follow
        $own = $this->poll($token, '/notifications')->assertOk();
        $this->assertSame('user:' . $user->id, $own->headers->get('X-Revision-Scope'));
        $this->assertSame(app(ChangeRevision::class)->user($user->id), $own->headers->get('X-Revision'));
    }

    public function test_staff_and_devices_still_get_their_branch_token(): void
    {
        $this->setUpPosQueue('kafe');
        $response = $this->poll($this->cashierToken, '/pos/floor')->assertOk();
        $this->assertSame('branch:' . $this->branch->id, $response->headers->get('X-Revision-Scope'));
        $this->assertSame(app(ChangeRevision::class)->branch($this->branch->id), $response->headers->get('X-Revision'));
    }
}
