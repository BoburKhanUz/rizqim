<?php

namespace Tests\Feature\AdminStores;

use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Jobs\SendTelegramRequest;
use App\Models\AuditLog;
use App\Models\StoreBroadcast;
use App\Services\Admin\Stores\StoreBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P4-22: ommaviy e'lon: preview with counts, the count confirmed, step-up and reason, one message per owner on the
 * queue, limits per member and per day, audited.
 */
class AdminStoreBroadcastTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => '1:token']);
        Queue::fake([SendTelegramRequest::class]);
    }

    public function test_preview_then_send_to_every_owner_once(): void
    {
        $a = $this->makeStore();
        $b = $this->makeStore();
        $c = $this->makeStore(['status' => StoreStatus::SUSPENDED->value]);
        $this->makeStore(); // no owner at all
        $this->makeOwner($a, null, '1001');
        $this->makeOwner($b, null, '1002');
        $this->makeOwner($c); // not on Telegram yet

        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->signIn($sales);
        $preview = $this->adminJson('POST', '/store-broadcasts/preview', $token, ['audience' => 'all'])->assertOk()->json('data');
        $this->assertSame(4, $preview['stores']);
        $this->assertSame(2, $preview['recipients']);
        $this->assertSame(2, $preview['without_telegram']);
        $this->assertSame(['all' => 4], ['all' => count($preview['sample'])]);
        $this->assertFalse(AuditLog::where('action', 'admin.stores.broadcasts.preview')->exists());

        $selected = $this->adminJson('POST', '/store-broadcasts/preview', $token, ['audience' => 'selected', 'store_ids' => [$a->id, $c->id]])->json('data');
        $this->assertSame(['stores' => 2, 'recipients' => 1, 'without_telegram' => 1], array_intersect_key($selected, array_flip(['stores', 'recipients', 'without_telegram'])));
        $this->assertSame(1, $this->adminJson('POST', '/store-broadcasts/preview', $token, ['audience' => 'all', 'statuses' => ['suspended']])->json('data.stores'));

        $send = ['audience' => 'all', 'text' => "Bugun 23:00 da texnik ishlar", 'recipients' => 2, 'reason' => 'Server yangilanishi'];
        $this->adminJson('POST', '/store-broadcasts', $token, $send)->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($sales, $token);
        $this->adminJson('POST', '/store-broadcasts', $token, [...$send, 'recipients' => 5])
            ->assertStatus(409)->assertJsonPath('code', 'count_changed')->assertJsonPath('data.preview.recipients', 2);

        $this->adminJson('POST', '/store-broadcasts', $token, $send)->assertCreated()->assertJsonPath('data.recipients', 2);

        $broadcast = StoreBroadcast::sole();
        $this->assertSame('sent', $broadcast->status);
        $this->assertSame(2, $broadcast->queued);
        Queue::assertPushed(SendTelegramRequest::class, 2);
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '1001'
            && str_contains($job->params['text'], 'texnik ishlar') && !isset($job->params['parse_mode']));

        $audit = AuditLog::where('action', 'admin.stores.broadcast_sent')->sole();
        $this->assertSame(2, $audit->payload_json['recipients']);
        $this->assertSame('Server yangilanishi', $audit->payload_json['reason']);

        $list = $this->adminJson('GET', '/store-broadcasts', $token)->assertOk();
        $this->assertSame(1, $list->json('data.sent_today'));
        $this->assertSame($sales->id, $list->json('data.broadcasts.0.created_by.id'));
    }

    public function test_limits_and_permissions(): void
    {
        $store = $this->makeStore();
        $this->makeOwner($store, null, '2001');
        $send = ['audience' => 'all', 'text' => "E'lon", 'recipients' => 1, 'reason' => 'Yangilik bor'];

        $token = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));
        $this->adminJson('POST', '/store-broadcasts/preview', $token, ['audience' => 'all'])->assertForbidden();
        $this->adminJson('POST', '/store-broadcasts', $token, $send)->assertForbidden();

        // The daily cap, platform-wide
        for ($i = 0; $i < StoreBroadcastService::LIMIT_PER_DAY; $i++) {
            StoreBroadcast::create(['audience' => 'all', 'text' => 'x', 'reason' => 'x', 'status' => 'sent']);
        }
        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->confirmedSession($sales);
        $this->adminJson('POST', '/store-broadcasts', $token, $send)->assertStatus(429)->assertJsonPath('code', 'daily_limit');

        // 5 attempts an hour per member (the refused one above counted too)
        StoreBroadcast::query()->delete();
        for ($i = 0; $i < 4; $i++) {
            $this->adminJson('POST', '/store-broadcasts', $token, $send)->assertCreated();
        }
        $this->adminJson('POST', '/store-broadcasts', $token, $send)->assertStatus(429);
    }
}
