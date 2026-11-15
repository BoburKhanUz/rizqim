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

    /**
     * Review R1-2: the job failed every try while listing its recipients (no recipient rows): nobody was messaged, so
     * resume starts the listing again, with the audience's filters as sent (an `all` broadcast narrowed by status).
     */
    public function test_a_broadcast_that_failed_while_listing_resumes_from_the_listing(): void
    {
        \App\Models\Store::query()->whereIn('id', \App\Models\User::query()->whereIn('telegram_id', ['5006', '5007'])->select('store_id'))
            ->update(['status' => \App\Enums\StoreStatus::SUSPENDED->value]);
        Queue::fake([SendTelegramRequest::class, SendStoreBroadcastJob::class]);
        $broadcast = app(StoreBroadcastService::class)->send(['audience' => 'all', 'statuses' => ['active']], 'Texnik ishlar', 5, $this->makeMember(\App\Enums\PlatformRole::SALES), 'Server');
        (new SendStoreBroadcastJob($broadcast->id, ['audience' => 'all', 'statuses' => ['active']]))->failed(new \RuntimeException('database went away'));
        $this->assertSame('failed', $broadcast->refresh()->status);
        $this->assertSame(0, StoreBroadcastRecipient::where('store_broadcast_id', $broadcast->id)->count());

        Queue::fake([SendTelegramRequest::class]);
        $this->assertSame(0, Artisan::call('admin:store-broadcast-resume', ['uuid' => $broadcast->uuid]));
        $this->assertSame('sent', $broadcast->refresh()->status);
        $this->assertSame(5, $broadcast->queued);
        // The suspended stores' owners are still left out
        $this->assertSame(array_slice($this->chats, 0, 5), $this->sentChats());
    }

    /**
     * Review R1-2: a broadcast from before the audience filters were kept, failed while listing: its filters are
     * unknown, so it is not resumed (it would message a wider audience).
     */
    public function test_a_broadcast_with_unknown_filters_is_not_relisted(): void
    {
        Queue::fake([SendTelegramRequest::class, SendStoreBroadcastJob::class]);
        $broadcast = $this->broadcast();
        $broadcast->forceFill(['status' => 'failed'])->save();

        $this->assertSame(1, Artisan::call('admin:store-broadcast-resume', ['uuid' => $broadcast->uuid]));
        $this->assertSame('failed', $broadcast->refresh()->status);
        Queue::assertNothingPushed();
    }

    /**
     * Review R1-3: the Telegram queue went down while a wave was handed over (the push after commit failed). The chats
     * whose message never reached the queue are not left marked as queued: the retried wave (or a resume) sends them,
     * and the chat that did reach the queue is not sent twice.
     */
    public function test_chats_not_handed_to_a_queue_that_went_down_are_sent_later_and_none_twice(): void
    {
        $queue = $this->flakyQueue();
        Queue::fake([SendStoreBroadcastJob::class]);
        $broadcast = $this->broadcast();
        $service = app(StoreBroadcastService::class);

        // Wave 1: the first message reaches the queue, then the queue is down
        $queue->acceptOnly = 1;
        try {
            $service->deliver($broadcast, ['audience' => 'all']);
            $this->fail('the push failure must surface (the job retries)');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e, $e->getMessage());
            $this->assertSame('queue down', $e->getMessage());
        }
        $this->assertSame([$this->chats[0]], $queue->chats);
        $this->assertSame(1, StoreBroadcastRecipient::where('store_broadcast_id', $broadcast->id)->whereNotNull('queued_at')->count());
        $this->assertSame(1, $broadcast->refresh()->queued);

        // The job gives up; the queue is back; the member resumes it
        (new SendStoreBroadcastJob($broadcast->id, ['audience' => 'all']))->failed($e);
        $queue->acceptOnly = null;
        Queue::fake([SendStoreBroadcastJob::class]);
        $this->assertSame(0, Artisan::call('admin:store-broadcast-resume', ['uuid' => $broadcast->uuid]));
        while ($broadcast->refresh()->status === 'sending') {
            $service->deliver($broadcast, ['audience' => 'all']);
        }

        $this->assertSame('sent', $broadcast->status);
        $this->assertSame(7, $broadcast->queued);
        $this->assertSame($this->chats, $queue->chats);
    }

    /**
     * Review R1-4: resuming from the console is audited, with no member (the console acted).
     */
    public function test_a_resume_is_audited_as_the_console(): void
    {
        Queue::fake([SendTelegramRequest::class, SendStoreBroadcastJob::class]);
        $broadcast = $this->broadcast();
        app(StoreBroadcastService::class)->deliver($broadcast, ['audience' => 'all']);
        (new SendStoreBroadcastJob($broadcast->id, ['audience' => 'all']))->failed(new \RuntimeException('worker died'));

        $this->assertSame(0, Artisan::call('admin:store-broadcast-resume', ['uuid' => $broadcast->uuid]));
        $log = \App\Models\AuditLog::query()->where('action', 'admin.cli.store_broadcast_resumed')->sole();
        $this->assertSame($broadcast->id, (int) $log->model_id);
        $this->assertNull($log->user_id);
        $this->assertNull($log->ip);
        $this->assertSame(['status' => 'failed'], $log->before);
        $this->assertSame(['status' => 'sending'], $log->after);
        $this->assertSame(3, $log->payload_json['queued'] ?? null);

        // Nothing to resume: nothing audited
        Artisan::call('admin:store-broadcast-resume', ['uuid' => $broadcast->uuid]);
        $this->assertSame(1, \App\Models\AuditLog::query()->where('action', 'admin.cli.store_broadcast_resumed')->count());
    }

    /**
     * A queue connection (the default one) that takes `acceptOnly` pushes and then fails like a Redis that went away;
     * `chats` are the chats whose Telegram message reached it.
     */
    private function flakyQueue(): object
    {
        $queue = new class extends \Illuminate\Queue\NullQueue
        {
            public ?int $acceptOnly = null;

            /** @var list<string> */
            public array $chats = [];

            public function push($job, $data = '', $queue = null)
            {
                return $this->enqueueUsing($job, $this->createPayload($job, (string) $queue, $data), $queue, null, fn () => $this->take($job));
            }

            public function later($delay, $job, $data = '', $queue = null)
            {
                return $this->enqueueUsing($job, $this->createPayload($job, (string) $queue, $data, $delay), $queue, $delay, fn () => $this->take($job));
            }

            private function take(mixed $job): string
            {
                if ($this->acceptOnly !== null && $this->acceptOnly-- <= 0) {
                    throw new \RuntimeException('queue down');
                }
                if ($job instanceof SendTelegramRequest) {
                    $this->chats[] = (string) $job->params['chat_id'];
                }

                return 'id';
            }
        };
        $queue->setContainer($this->app);
        app('queue')->extend('flaky', fn () => new class($queue) implements \Illuminate\Queue\Connectors\ConnectorInterface
        {
            public function __construct(private readonly \Illuminate\Contracts\Queue\Queue $queue) {}

            public function connect(array $config)
            {
                return $this->queue;
            }
        });
        config(['queue.connections.flaky' => ['driver' => 'flaky'], 'queue.default' => 'flaky']);

        return $queue;
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
