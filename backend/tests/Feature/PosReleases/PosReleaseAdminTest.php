<?php

namespace Tests\Feature\PosReleases;

use App\Domain\Pos\Releases\PosAppReleases;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\PosAppRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\AdminPos\AdminPosFixtures;
use Tests\TestCase;

/**
 * Wave M5 S5-a: the registry endpoints of the control centre (permission, step-up, reason, audit) and the public,
 * cached, throttled manifest.
 */
class PosReleaseAdminTest extends TestCase
{
    use AdminPosFixtures;
    use PosReleaseFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'UTC'));
        $this->setUpManagement();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function body(array $over = []): array
    {
        return $over + [
            'app' => 'cashier', 'platform' => 'android', 'version' => '1.4.0', 'build' => 7,
            'file_url' => 'https://pos.rizqim.uz/files/cashier/android/rizqim-cashier-1.4.0.apk',
            'sha256' => str_repeat('AB', 32), 'size_bytes' => 26_000_000, 'notes' => "Chek chiqarish tezlashdi.",
            'reason' => 'Pilot sinovidan o\'tdi',
        ];
    }

    public function test_permissions_step_up_and_reason_guard_every_write(): void
    {
        $release = $this->release('1.0.0');
        $writes = [
            ['POST', '/pos/releases', $this->body()],
            ['PATCH', "/pos/releases/{$release->uuid}", ['notes' => 'x', 'reason' => 'Tuzatish']],
            ['POST', "/pos/releases/{$release->uuid}/withdraw", ['reason' => 'Xato']],
            ['POST', "/pos/releases/{$release->uuid}/restore", ['reason' => 'Xato']],
            ['DELETE', "/pos/releases/{$release->uuid}", ['reason' => 'Xato']],
        ];

        // Nobody signed in
        foreach ($writes as [$method, $uri, $data]) {
            $this->adminJson($method, $uri, null, $data)->assertUnauthorized();
        }
        // The operator reads devices but may not manage them; no write passes even with a step-up
        $operator = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));
        foreach ($writes as [$method, $uri, $data]) {
            $this->adminJson($method, $uri, $operator, $data)->assertForbidden();
        }
        // The right permission without a step-up
        $sales = $this->signIn($this->makeMember(PlatformRole::SALES));
        foreach ($writes as [$method, $uri, $data]) {
            $this->adminJson($method, $uri, $sales, $data)->assertForbidden()->assertJsonPath('code', 'reauth_required');
        }
        $this->assertSame(1, PosAppRelease::count());
        $this->assertNull($release->refresh()->withdrawn_at);
        $this->assertSame(0, AuditLog::where('action', 'like', 'admin.pos.release_%')->count());

        // Reads: devices.view is enough
        $this->adminJson('GET', '/pos/releases', $operator)->assertOk();
        $this->adminJson('GET', "/pos/releases/{$release->uuid}", $operator)->assertOk();

        // A reason is required on every write
        $member = $this->makeMember(PlatformRole::SALES);
        $token = $this->confirmedSession($member);
        foreach ([['POST', '/pos/releases', $this->body(['reason' => ''])],
            ['PATCH', "/pos/releases/{$release->uuid}", ['notes' => 'x']],
            ['POST', "/pos/releases/{$release->uuid}/withdraw", []],
            ['POST', "/pos/releases/{$release->uuid}/restore", []],
            ['DELETE', "/pos/releases/{$release->uuid}", []]] as [$method, $uri, $data]) {
            $this->adminJson($method, $uri, $token, $data)->assertStatus(422)->assertJsonValidationErrors('reason');
        }
    }

    public function test_adding_a_release_is_validated_stored_and_audited(): void
    {
        $member = $this->makeMember(PlatformRole::SALES);
        $token = $this->confirmedSession($member);

        $created = $this->adminJson('POST', '/pos/releases', $token, $this->body())->assertCreated()
            ->assertJsonPath('data.label', '1.4.0+7')->assertJsonPath('data.is_latest', true)
            ->assertJsonPath('data.is_minimum', false)->assertJsonPath('data.minimum_from', null)
            ->assertJsonPath('data.sha256', str_repeat('ab', 32))->assertJsonPath('data.withdrawn_at', null);
        $uuid = $created->json('data.uuid');
        $this->assertNotEmpty($uuid);

        $release = PosAppRelease::where('uuid', $uuid)->sole();
        $this->assertSame([$member->id, 7, 'android'], [$release->created_by, $release->build, $release->platform]);

        $audit = AuditLog::where('action', 'admin.pos.release_created')->sole();
        $this->assertSame([$member->id, null, 'Pilot sinovidan o\'tdi'], [$audit->user_id, $audit->before, $audit->payload_json['reason']]);
        $this->assertSame($release->id, (int) $audit->model_id);
        $this->assertSame(['cashier', 'android', '1.4.0', 7], [$audit->payload_json['app'], $audit->payload_json['platform'], $audit->payload_json['version'], $audit->payload_json['build']]);
        $this->assertSame(str_repeat('ab', 32), $audit->after['sha256']);

        // Twice the same: 409
        $this->adminJson('POST', '/pos/releases', $token, $this->body())->assertStatus(409)->assertJsonPath('code', 'release_exists');
        // Same version, another build or platform: fine
        $this->adminJson('POST', '/pos/releases', $token, $this->body(['build' => 8]))->assertCreated();
        $this->adminJson('POST', '/pos/releases', $token, $this->body(['platform' => 'windows']))->assertCreated();

        // Validation
        foreach ([
            ['app' => 'pos'], ['platform' => 'ios'], ['version' => 'v1'], ['version' => '1.4.0-beta'], ['build' => -1],
            ['file_url' => 'http://pos.rizqim.uz/a.apk'], ['file_url' => 'javascript:alert(1)'], ['sha256' => 'abc'], ['size_bytes' => 'big'],
        ] as $bad) {
            $this->adminJson('POST', '/pos/releases', $token, $this->body($bad + ['version' => '9.9.9']))->assertStatus(422);
        }
        $this->assertSame(3, PosAppRelease::count());
    }

    public function test_declaring_a_minimum_sets_the_red_notice_fourteen_days_ahead(): void
    {
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SALES));

        $row = $this->adminJson('POST', '/pos/releases', $token, $this->body(['is_minimum' => true]))->assertCreated()->json('data');
        $this->assertTrue($row['is_minimum']);
        $this->assertSame(now()->addDays(14)->toIso8601String(), $row['minimum_from']);

        // Staff may choose another day
        $other = $this->adminJson('POST', '/pos/releases', $token, $this->body(['version' => '1.5.0', 'is_minimum' => true, 'minimum_from' => '2026-12-31T00:00:00Z']))->assertCreated()->json('data');
        $this->assertSame('2026-12-31T00:00:00+00:00', $other['minimum_from']);

        // A plain release that later becomes the minimum gets the default; clearing it clears the day
        $plain = $this->adminJson('POST', '/pos/releases', $token, $this->body(['version' => '1.6.0']))->assertCreated()->json('data.uuid');
        $this->adminJson('PATCH', "/pos/releases/{$plain}", $token, ['is_minimum' => true, 'reason' => 'Eskilar xavfli'])->assertOk()
            ->assertJsonPath('data.minimum_from', now()->addDays(14)->toIso8601String());
        $this->adminJson('PATCH', "/pos/releases/{$plain}", $token, ['notes' => 'Yangi matn', 'reason' => 'Matn'])->assertOk()
            ->assertJsonPath('data.minimum_from', now()->addDays(14)->toIso8601String())->assertJsonPath('data.notes', 'Yangi matn');
        $this->adminJson('PATCH', "/pos/releases/{$plain}", $token, ['is_minimum' => false, 'reason' => 'Orqaga'])->assertOk()
            ->assertJsonPath('data.minimum_from', null)->assertJsonPath('data.is_minimum', false);

        $audit = AuditLog::where('action', 'admin.pos.release_updated')->orderBy('id')->first();
        $this->assertSame([false, true], [$audit->before['is_minimum'], $audit->after['is_minimum']]);
        $this->assertSame(3, AuditLog::where('action', 'admin.pos.release_updated')->count());

        // Identity fields cannot be changed
        $this->adminJson('PATCH', "/pos/releases/{$plain}", $token, ['version' => '9.9.9', 'reason' => 'Xato'])->assertOk()->assertJsonPath('data.version', '1.6.0');
    }

    public function test_withdraw_restore_and_delete_are_audited_and_idempotent(): void
    {
        $member = $this->makeMember(PlatformRole::SALES);
        $token = $this->confirmedSession($member);
        $old = $this->release('1.0.0');
        $new = $this->release('2.0.0', ['is_minimum' => true, 'minimum_from' => now()]);
        $service = app(PosAppReleases::class);

        $this->assertSame($new->id, $service->rows('cashier', 'android')['latest']->id);

        $this->adminJson('POST', "/pos/releases/{$new->uuid}/withdraw", $token, ['reason' => 'Xato topildi'])->assertOk()
            ->assertJsonPath('data.is_latest', false)->assertJsonPath('data.withdrawn_at', now()->toIso8601String());
        $this->assertSame($old->id, $service->rows('cashier', 'android')['latest']->id);
        $this->assertNull($service->rows('cashier', 'android')['minimum']);

        $audit = AuditLog::where('action', 'admin.pos.release_withdrawn')->sole();
        $this->assertSame([null, now()->toIso8601String(), 'Xato topildi'], [$audit->before['withdrawn_at'], $audit->after['withdrawn_at'], $audit->payload_json['reason']]);
        // Again: nothing changes, nothing more is audited
        $this->adminJson('POST', "/pos/releases/{$new->uuid}/withdraw", $token, ['reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.release_withdrawn')->count());

        $this->adminJson('POST', "/pos/releases/{$new->uuid}/restore", $token, ['reason' => 'Xato emas ekan'])->assertOk()
            ->assertJsonPath('data.is_latest', true)->assertJsonPath('data.withdrawn_at', null);
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.release_restored')->count());

        $this->adminJson('DELETE', "/pos/releases/{$old->uuid}", $token, ['reason' => "Xato qo'shilgan"])->assertOk()->assertJsonPath('data.uuid', $old->uuid);
        $this->assertNull(PosAppRelease::find($old->id));
        $deleted = AuditLog::where('action', 'admin.pos.release_deleted')->sole();
        $this->assertSame(['1.0.0', 'cashier'], [$deleted->payload_json['version'], $deleted->payload_json['app']]);
        $this->assertSame($old->file_url, $deleted->before['file_url']);

        $stranger = \Illuminate\Support\Str::uuid();
        $this->adminJson('POST', "/pos/releases/{$stranger}/withdraw", $token, ['reason' => 'Yo\'q'])->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->adminJson('GET', "/pos/releases/{$stranger}", $token)->assertNotFound();
    }

    public function test_the_list_shows_withdrawn_rows_and_filters(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $this->release('1.0.0');
        $this->release('1.10.0');
        $this->release('1.2.0', ['withdrawn_at' => now()]);
        $this->release('3.0.0', ['app' => 'kds', 'platform' => 'windows']);

        $rows = $this->adminJson('GET', '/pos/releases', $token)->assertOk()->json('data.data');
        $this->assertSame(['cashier 1.10.0', 'cashier 1.2.0', 'cashier 1.0.0', 'kds 3.0.0'], array_map(fn ($row) => $row['app'] . ' ' . $row['version'], $rows));
        $this->assertSame([true, false, false, true], array_column($rows, 'is_latest'));
        $this->assertCount(1, $this->adminJson('GET', '/pos/releases?withdrawn=1', $token)->json('data.data'));
        $this->assertCount(3, $this->adminJson('GET', '/pos/releases?withdrawn=0', $token)->json('data.data'));
        $this->assertCount(1, $this->adminJson('GET', '/pos/releases?app=kds', $token)->json('data.data'));
        $this->assertCount(0, $this->adminJson('GET', '/pos/releases?platform=windows&app=cashier', $token)->json('data.data'));
        $this->adminJson('GET', '/pos/releases?app=bogus', $token)->assertStatus(422);
    }

    // ---- the public manifest ----

    public function test_the_public_manifest_needs_no_auth_hides_ids_and_withdrawn_rows(): void
    {
        $this->getJson('/api/v1/pos/releases')->assertOk()->assertJsonPath('data.releases', []);
        Cache::forget(PosAppReleases::MANIFEST_CACHE_KEY);

        $this->release('1.0.0');
        $this->release('1.4.0', ['build' => 7, 'is_minimum' => true, 'minimum_from' => now()->addDays(14)]);
        $this->release('2.0.0', ['withdrawn_at' => now()]);
        $this->release('3.0.0', ['app' => 'kds', 'platform' => 'windows']);

        $response = $this->getJson('/api/v1/pos/releases')->assertOk();
        $rows = $response->json('data.releases');
        $this->assertSame(['cashier 1.4.0', 'cashier 1.0.0', 'kds 3.0.0'], array_map(fn ($row) => $row['app'] . ' ' . $row['version'], $rows));
        $this->assertSame([true, false, true], array_column($rows, 'is_latest'));
        $this->assertSame([
            'app', 'platform', 'version', 'build', 'file_url', 'sha256', 'size_bytes', 'notes', 'released_at', 'is_minimum', 'minimum_from', 'is_latest',
        ], array_keys($rows[0]));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('uuid', $response->getContent());
    }

    public function test_the_public_manifest_is_cached_five_minutes_and_dropped_by_a_registry_change(): void
    {
        $this->release('1.0.0');
        $this->assertCount(1, $this->getJson('/api/v1/pos/releases')->json('data.releases'));

        // Written behind the cache's back: the cached copy is still served
        $this->release('1.1.0');
        $this->assertCount(1, $this->getJson('/api/v1/pos/releases')->json('data.releases'));

        // A change through the control centre drops it at once
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SALES));
        $this->adminJson('POST', '/pos/releases', $token, $this->body(['version' => '1.2.0']))->assertCreated();
        $this->assertCount(3, $this->getJson('/api/v1/pos/releases')->json('data.releases'));

        // Or by itself after the TTL
        $this->release('1.3.0');
        Carbon::setTestNow(now()->addSeconds(PosAppReleases::MANIFEST_TTL + 1));
        $this->assertCount(4, $this->getJson('/api/v1/pos/releases')->json('data.releases'));
    }

    public function test_the_public_manifest_is_throttled_per_address(): void
    {
        config(['pos.releases.manifest_per_minute' => 3]);
        RateLimiter::clear('ip:127.0.0.1');

        foreach (range(1, 3) as $i) {
            $this->getJson('/api/v1/pos/releases')->assertOk();
        }
        $this->getJson('/api/v1/pos/releases')->assertStatus(429);
        // Another address has its own budget
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])->getJson('/api/v1/pos/releases')->assertOk();
    }
}
