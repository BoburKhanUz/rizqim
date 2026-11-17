<?php

namespace Tests\Feature\Partitions;

use App\Domain\Integrations\IntegrationEvent;
use App\Domain\Integrations\IntegrationEventStatus;
use App\Enums\OrderStatus;
use App\Models\PosSyncOperation;
use App\Models\TelegramAction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave M3 (design section 2): the mutable logs that are not partitioned are pruned (MassPrunable + model:prune), and
 * failed queue jobs older than 30 days go too.
 */
class JournalPruningTest extends TestCase
{
    use OrderLifecycleFixtures;
    use RefreshDatabase;

    public function test_finished_integration_events_older_than_90_days_are_pruned_and_the_rest_kept(): void
    {
        $old = now()->subDays(91);
        $make = fn (IntegrationEventStatus $status, $at) => IntegrationEvent::factory()->create(['status' => $status, 'created_at' => $at]);
        $acknowledged = $make(IntegrationEventStatus::ACKNOWLEDGED, $old);
        $dead = $make(IntegrationEventStatus::DEAD, $old);
        $failed = $make(IntegrationEventStatus::FAILED, $old);
        $pending = $make(IntegrationEventStatus::PENDING, $old);
        $recent = $make(IntegrationEventStatus::ACKNOWLEDGED, now()->subDays(10));

        // The model lives outside app/Models: the plain model:prune would not find it, the schedule names it
        $this->artisan('model:prune', ['--model' => [IntegrationEvent::class]])->assertSuccessful();

        $this->assertSame([$failed->id, $pending->id, $recent->id], IntegrationEvent::orderBy('id')->pluck('id')->all());
        $this->assertNull(IntegrationEvent::find($acknowledged->id));
        $this->assertNull(IntegrationEvent::find($dead->id));
    }

    public function test_expired_telegram_actions_and_old_applied_sync_operations_are_pruned_by_the_daily_model_prune(): void
    {
        $this->setUpLifecycleStore();
        $order = $this->makeOrder(OrderStatus::NEW);
        $action = fn ($expires) => TelegramAction::create(['order_id' => $order->id, 'action' => 'accept', 'token' => Str::random(32), 'expires_at' => $expires]);
        $expiredLongAgo = $action(now()->subDays(31));
        $expiredRecently = $action(now()->subDays(2));

        $device = DB::table('devices')->insertGetId(['uuid' => (string) Str::uuid(), 'type' => 'pos', 'name' => 'Kassa 1', 'store_id' => $this->store->id, 'created_at' => now(), 'updated_at' => now()]);
        $seq = 0;
        $operation = function (string $status, $received) use ($device, &$seq) {
            return PosSyncOperation::create([
            'store_id' => $this->store->id, 'device_id' => $device, 'op_id' => (string) Str::uuid(), 'seq' => ++$seq, 'type' => 'order.open',
            'payload' => [], 'status' => $status, 'received_at' => $received,
            ]);
        };
        $oldApplied = $operation('applied', now()->subMonths(13));
        $oldRejected = $operation('rejected', now()->subMonths(13));
        $recentApplied = $operation('applied', now()->subMonths(11));

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertSame([$expiredRecently->id], TelegramAction::pluck('id')->all());
        $this->assertNull(TelegramAction::find($expiredLongAgo->id));
        $this->assertSame([$oldRejected->id, $recentApplied->id], PosSyncOperation::orderBy('id')->pluck('id')->all());
        $this->assertNull(PosSyncOperation::find($oldApplied->id));
    }

    public function test_the_schedule_runs_the_maintenance_commands(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command)->implode("\n");

        $this->assertStringContainsString('db:partitions:ensure', $events);
        $this->assertStringContainsString('db:partitions:archive', $events);
        $this->assertStringContainsString("queue:prune-failed --hours=720", $events);
        $this->assertMatchesRegularExpression('/model:prune --model=.*IntegrationEvent/', $events);
    }
}
