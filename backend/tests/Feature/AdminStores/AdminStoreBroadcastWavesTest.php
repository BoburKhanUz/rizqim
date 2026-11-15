<?php

namespace Tests\Feature\AdminStores;

use App\Jobs\SendStoreBroadcastJob;
use App\Jobs\SendTelegramRequest;
use App\Models\StoreBroadcast;
use App\Models\StoreBroadcastRecipient;
use App\Services\Admin\Stores\StoreBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Wave M1: an ommaviy e'lon leaves in waves at admin.store_broadcasts.rate_per_second instead of all at once; each
 * owner chat is queued once (a repeated or retried run sends nobody twice), a broadcast that failed half way resumes
 * with the chats not reached yet, and the preview says how long sending takes.
 */
class AdminStoreBroadcastWavesTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    /** @var list<string> */
    private array $chats = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.bot_token' => '1:token',
            'admin.store_broadcasts.rate_per_second' => 2,
            'admin.store_broadcasts.wave_size' => 3,
        ]);

        for ($i = 1; $i <= 7; $i++) {
            $store = $this->makeStore();
            $this->makeOwner($store, null, (string) (5000 + $i));
            $this->chats[] = (string) (5000 + $i);
        }
        // An owner of a second store is one chat
        $this->makeOwner($this->makeStore(), null, '5001');
    }

    public function test_the_messages_leave_in_waves_at_the_rate_and_each_chat_once(): void
    {
        Queue::fake([SendTelegramRequest::class, SendStoreBroadcastJob::class]);
        $broadcast = $this->broadcast();
        $service = app(StoreBroadcastService::class);

        // Wave 1: 3 chats, at most 2 a second (delays 0, 0, 1), the next wave in 2 s
        $service->deliver($broadcast, ['audience' => 'all']);
        $this->assertSame([0, 0, 1], $this->delays());
        Queue::assertPushed(SendStoreBroadcastJob::class, fn (SendStoreBroadcastJob $job) => $job->broadcastId === $broadcast->id && $job->delay === 2);
        $broadcast->refresh();
        $this->assertSame('sending', $broadcast->status);
        $this->assertSame(3, $broadcast->queued);
        $this->assertSame(7, StoreBroadcastRecipient::where('store_broadcast_id', $broadcast->id)->count());

        $service->deliver($broadcast, ['audience' => 'all']);
        $service->deliver($broadcast, ['audience' => 'all']);
        $this->assertSame('sent', $broadcast->refresh()->status);
        $this->assertSame(7, $broadcast->queued);
        $this->assertNotNull($broadcast->finished_at);

        // A run again (a retried job): nobody is messaged twice
        $service->deliver($broadcast, ['audience' => 'all']);
        Queue::assertPushed(SendTelegramRequest::class, 7);
        $this->assertSame($this->chats, $this->sentChats());
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->notification['type'] === 'admin_store_broadcast'
            && $job->notification['user_id'] !== null && str_contains($job->params['text'], 'Texnik ishlar'));
    }

    public function test_the_job_runs_every_wave_through_the_queue(): void
    {
        Queue::fake([SendTelegramRequest::class]);
        $broadcast = $this->broadcast();

        // The sync queue runs the delayed next waves at once
        SendStoreBroadcastJob::dispatch($broadcast->id, ['audience' => 'all']);

        $this->assertSame('sent', $broadcast->refresh()->status);
        $this->assertSame(7, $broadcast->queued);
        $this->assertSame($this->chats, $this->sentChats());
        $this->assertSame([0, 0, 1, 0, 0, 1, 0], $this->delays());
    }

    public function test_a_failed_broadcast_resumes_with_the_chats_not_reached(): void
    {
        Queue::fake([SendTelegramRequest::class, SendStoreBroadcastJob::class]);
        $broadcast = $this->broadcast();
        app(StoreBroadcastService::class)->deliver($broadcast, ['audience' => 'all']);
        (new SendStoreBroadcastJob($broadcast->id, ['audience' => 'all']))->failed(new \RuntimeException('worker died'));
        $this->assertSame('failed', $broadcast->refresh()->status);

        Queue::fake([SendTelegramRequest::class]);
        $this->assertSame(0, Artisan::call('admin:store-broadcast-resume', ['uuid' => $broadcast->uuid]));
        $this->assertSame('sent', $broadcast->refresh()->status);
        $this->assertSame(7, $broadcast->queued);
        // Only the 4 chats the first wave had not reached
        $this->assertSame(array_slice($this->chats, 3), $this->sentChats());

        // Nothing to resume any more
        $this->assertSame(1, Artisan::call('admin:store-broadcast-resume', ['uuid' => $broadcast->uuid]));
    }

    public function test_the_preview_and_the_send_say_how_long_it_takes(): void
    {
        Queue::fake([SendTelegramRequest::class]);
        $token = $this->signIn($sales = $this->makeMember(\App\Enums\PlatformRole::SALES));
        $this->adminJson('POST', '/store-broadcasts/preview', $token, ['audience' => 'all'])->assertOk()
            ->assertJsonPath('data.recipients', 7)
            ->assertJsonPath('data.estimated_seconds', 4);

        $this->stepUp($sales, $token);
        $this->adminJson('POST', '/store-broadcasts', $token, ['audience' => 'all', 'text' => 'Texnik ishlar', 'recipients' => 7, 'reason' => 'Server yangilanishi'])
            ->assertCreated()->assertJsonPath('data.estimated_seconds', 4);

        config(['admin.store_broadcasts.rate_per_second' => 0]);
        $this->assertSame(1000, StoreBroadcastService::estimatedSeconds(1000));
        $this->assertSame(0, StoreBroadcastService::estimatedSeconds(0));
    }

    private function broadcast(): StoreBroadcast
    {
        return StoreBroadcast::create([
            'audience' => 'all', 'text' => 'Texnik ishlar', 'reason' => 'Server', 'stores' => 8, 'recipients' => 7, 'status' => 'queued',
        ]);
    }

    /**
     * @return list<int>
     */
    private function delays(): array
    {
        return Queue::pushed(SendTelegramRequest::class)->map(fn (SendTelegramRequest $job) => (int) $job->delay)->values()->all();
    }

    /**
     * @return list<string>
     */
    private function sentChats(): array
    {
        return Queue::pushed(SendTelegramRequest::class)->map(fn (SendTelegramRequest $job) => (string) $job->params['chat_id'])->values()->all();
    }
}
