<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Sync\SyncSignature;
use App\Enums\DeviceType;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\IdempotencyKey;
use App\Services\Auth\DevicePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * Wave M5 S3-a (docs/scale/M5_DESIGN.md §4 "Lifecycle"): the till's queue signing key. Given once (the pairing answer,
 * or POST /terminal/sync-key for a till paired before), encrypted at rest, never in an audit record, a log or any other
 * answer; a second call rotates and keeps the previous key; a disconnected till's key is erased after 30 days.
 */
class SyncKeyTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
    }

    /**
     * @return array<string, mixed> the pairing answer's `data`
     */
    private function pairOver(string $type): array
    {
        $code = app(DevicePairingService::class)->createCode($this->store, $this->branch, DeviceType::from($type), 'Kassa', $this->owner)['code'];

        return $this->withBearer(null)->postJson('/api/v1/devices/pair', ['code' => $code, 'platform' => 'android'])->assertCreated()->json('data');
    }

    private function assertNowhereElse(string $key): void
    {
        foreach (AuditLog::all() as $log) {
            $text = (string) json_encode($log->getAttributes(), JSON_UNESCAPED_SLASHES);
            $this->assertStringNotContainsString($key, $text, $log->action);
            $this->assertStringNotContainsString(bin2hex((string) base64_decode($key, true)), $text, $log->action);
        }
        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_pairing_a_till_gives_its_key_once_and_keeps_it_encrypted(): void
    {
        $paired = $this->pairOver('pos');

        $this->assertSame(1, $paired['sync_key']['kid']);
        $this->assertSame(SyncSignature::KEY_BYTES, strlen((string) base64_decode($paired['sync_key']['key'], true)));

        $row = DB::table('devices')->where('uuid', $paired['device']['uuid'])->first();
        $this->assertSame(1, (int) $row->sync_key_id);
        $this->assertNotSame($paired['sync_key']['key'], $row->sync_key);
        $this->assertStringNotContainsString($paired['sync_key']['key'], (string) $row->sync_key);
        $this->assertSame($paired['sync_key']['key'], Crypt::decryptString((string) $row->sync_key));
        $this->assertNull($row->sync_key_prev);

        // Nowhere else: the terminal's own details, the owner's device list, the model's own array, the audit log
        $device = Device::where('uuid', $paired['device']['uuid'])->sole();
        $bodies = [
            (string) $this->withBearer($paired['token'])->getJson('/api/v1/terminal')->assertOk()->getContent(),
            (string) $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/devices')->assertOk()->getContent(),
            (string) json_encode($device->toArray()),
            (string) json_encode($device->toPublicArray()),
        ];
        foreach ($bodies as $body) {
            // (the key's number, `sync_key_id`, is no secret)
            $this->assertDoesNotMatchRegularExpression('/"sync_key"|sync_key_prev|sync_chain/', $body);
            $this->assertStringNotContainsString(str_replace('/', '\/', $paired['sync_key']['key']), $body);
        }
        $this->assertNowhereElse($paired['sync_key']['key']);

        // Another till has another key
        $this->assertNotSame($paired['sync_key']['key'], $this->pairOver('pos')['sync_key']['key']);
    }

    public function test_a_terminal_that_never_sends_a_queue_gets_no_key(): void
    {
        foreach (['kds', 'display'] as $type) {
            $paired = $this->pairOver($type);

            $this->assertNull($paired['sync_key']);
            $this->assertSame(0, (int) DB::table('devices')->where('uuid', $paired['device']['uuid'])->value('sync_key_id'));
            $this->withBearer($paired['token'])->postJson('/api/v1/terminal/sync-key')->assertStatus(403)->assertJsonPath('data.reason', 'terminal_required');
        }
    }

    public function test_a_till_paired_before_signing_asks_for_a_key_and_asking_again_rotates(): void
    {
        $paired = $this->pairOver('pos');
        DB::table('devices')->where('uuid', $paired['device']['uuid'])->update(['sync_key' => null, 'sync_key_id' => 0]);

        $first = $this->withBearer($paired['token'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/terminal/sync-key')->assertOk()->json('data');
        $this->assertSame(1, $first['kid']);
        $this->assertSame(SyncSignature::KEY_BYTES, strlen((string) base64_decode($first['key'], true)));
        $issued = AuditLog::where('action', 'device.sync_key_issued')->sole();
        $this->assertSame(1, $issued->payload_json['meta']['kid'] ?? $issued->payload_json['kid'] ?? null);

        $second = $this->withBearer($paired['token'])->postJson('/api/v1/terminal/sync-key')->assertOk()->json('data');
        $this->assertSame(2, $second['kid']);
        $this->assertNotSame($first['key'], $second['key']);

        $row = DB::table('devices')->where('uuid', $paired['device']['uuid'])->first();
        $this->assertSame(2, (int) $row->sync_key_id);
        $this->assertSame($second['key'], Crypt::decryptString((string) $row->sync_key));
        $this->assertSame($first['key'], Crypt::decryptString((string) $row->sync_key_prev));
        $this->assertSame(1, AuditLog::where('action', 'device.sync_key_rotated')->count());
        $this->assertNowhereElse($first['key']);
        $this->assertNowhereElse($second['key']);
    }

    public function test_a_till_whose_queue_is_not_empty_does_not_get_a_new_key(): void
    {
        $paired = $this->pairOver('pos');
        // What the till last said about its queue: operations are waiting, signed with the key it holds
        DB::table('devices')->where('uuid', $paired['device']['uuid'])->update(['sync_pending_ops' => 3, 'sync_oldest_op_at' => now()->subHour()]);

        $this->withBearer($paired['token'])->postJson('/api/v1/terminal/sync-key')->assertStatus(409)->assertJsonPath('data.reason', 'queue_not_empty')->assertJsonMissingPath('data.key');
        $row = DB::table('devices')->where('uuid', $paired['device']['uuid'])->first();
        $this->assertSame(1, (int) $row->sync_key_id);
        $this->assertSame($paired['sync_key']['key'], Crypt::decryptString((string) $row->sync_key));
        $this->assertSame(0, AuditLog::where('action', 'device.sync_key_rotated')->count());

        // The till says its queue is empty (GET /pos/sync/status?queue_size=0): now it may rotate
        $this->withBearer($paired['token'])->getJson('/api/v1/pos/sync/status?queue_size=0')->assertOk();
        $this->withBearer($paired['token'])->postJson('/api/v1/terminal/sync-key')->assertOk()->assertJsonPath('data.kid', 2);
    }

    public function test_a_till_without_a_key_gets_its_first_one_whatever_its_queue_holds(): void
    {
        $paired = $this->pairOver('pos');
        // Paired before signing existed, with unsigned operations waiting: nothing is signed with a key to lose
        DB::table('devices')->where('uuid', $paired['device']['uuid'])->update(['sync_key' => null, 'sync_key_id' => 0, 'sync_pending_ops' => 3]);

        $this->withBearer($paired['token'])->postJson('/api/v1/terminal/sync-key')->assertOk()->assertJsonPath('data.kid', 1);
    }

    public function test_only_the_tills_own_token_gets_a_key(): void
    {
        $this->withBearer(null)->postJson('/api/v1/terminal/sync-key')->assertUnauthorized();

        $paired = $this->pairOver('pos');
        $this->givePin($this->manager, '5926');
        $staff = (string) $this->withBearer($paired['token'])->postJson("/api/v1/devices/{$paired['device']['uuid']}/staff-session", ['user_id' => $this->manager->id, 'pin' => '5926'])->assertOk()->json('data.token');
        $this->withBearer($staff)->postJson('/api/v1/terminal/sync-key')->assertUnauthorized();
        $this->assertSame(1, (int) DB::table('devices')->where('uuid', $paired['device']['uuid'])->value('sync_key_id'));
    }

    public function test_asking_for_keys_is_rate_limited(): void
    {
        $paired = $this->pairOver('pos');
        for ($i = 0; $i < 6; $i++) {
            $this->withBearer($paired['token'])->postJson('/api/v1/terminal/sync-key')->assertOk();
        }

        $this->withBearer($paired['token'])->postJson('/api/v1/terminal/sync-key')->assertStatus(429);
        $this->assertSame(7, (int) DB::table('devices')->where('uuid', $paired['device']['uuid'])->value('sync_key_id'));
    }

    public function test_a_disconnected_tills_key_is_kept_30_days_then_erased(): void
    {
        $kept = Device::where('uuid', $this->pairOver('pos')['device']['uuid'])->sole();
        $gone = Device::where('uuid', $this->pairOver('pos')['device']['uuid'])->sole();
        $live = Device::where('uuid', $this->pairOver('pos')['device']['uuid'])->sole();
        SyncSignature::issue($gone);
        $kept->forceFill(['revoked_at' => now()->subDays(29)])->save();
        $gone->forceFill(['revoked_at' => now()->subDays(31), 'sync_chain' => '1:' . str_repeat('a', 64)])->save();

        $this->artisan('pos:erase-sync-keys')->assertSuccessful();

        $this->assertNotNull(DB::table('devices')->where('id', $kept->id)->value('sync_key'));
        $this->assertNotNull(DB::table('devices')->where('id', $live->id)->value('sync_key'));
        $row = DB::table('devices')->where('id', $gone->id)->first();
        $this->assertNull($row->sync_key);
        $this->assertNull($row->sync_key_prev);
        $this->assertNull($row->sync_chain);
        // The number stays: a key issued later never reuses a kid
        $this->assertSame(2, (int) $row->sync_key_id);
    }
}
