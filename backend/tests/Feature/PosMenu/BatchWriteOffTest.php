<?php

namespace Tests\Feature\PosMenu;

use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\AuditLog;
use App\Models\MenuBatch;
use App\Models\MenuBatchWriteOff;
use App\Models\Order;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\TestCase;

/**
 * P6-15 hisobdan chiqarish and the yield report: unsold portions taken off a batch with a reason (module gate, reasons,
 * limits, close, idempotency, audit, availability), the terminal's shift and the Z report, and the yield numbers of a
 * day (produced, sold, written off, left, variance, cancellations, kompleks portions).
 */
class BatchWriteOffTest extends TestCase
{
    use PosBillingFixtures;
    use PosMenuModules {
        PosMenuModules::enableMenuModules as enableModules;
    }
    use RefreshDatabase;

    protected Product $osh;

    protected ProductVariant $portion;

    protected ProductVariant $small;

    protected string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-14 06:00:00', 'UTC'));
        $this->setUpPosBilling();
        $this->enableModules();
        $this->osh = $this->makeProduct('Osh', ['batch_tracked' => true]);
        $this->portion = $this->makeVariant($this->osh, '55000', ['name' => '1 porsiya']);
        $this->small = $this->makeVariant($this->osh, '45000', ['name' => '0.7 porsiya', 'batch_consumption' => '0.7']);
        $this->managerToken = $this->pinSession($this->till, $this->manager, self::MANAGER_PIN);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function openBatch(string $quantity = '10'): MenuBatch
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/batches', [
            'branch_id' => $this->branch->id, 'product_id' => $this->osh->id, 'quantity' => $quantity,
        ])->assertCreated();

        return MenuBatch::latest('id')->firstOrFail();
    }

    protected function writeOff(MenuBatch $batch, array $data, ?string $token = null, ?string $key = null)
    {
        $this->app['auth']->forgetGuards();
        $request = $token !== null ? $this->withBearer($token) : $this->actingAs($this->owner, 'sanctum');

        return $request->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson("/api/v1/owner/batches/{$batch->id}/write-off", $data);
    }

    public function test_unsold_portions_are_written_off_with_a_reason(): void
    {
        $batch = $this->openBatch('10');
        Event::fake([MenuAvailabilityChangedBroadcast::class]);

        $response = $this->writeOff($batch, ['quantity' => '3.5', 'reason' => 'unsold'])->assertCreated();
        $response->assertJsonPath('data.write_off.quantity', 3.5)
            ->assertJsonPath('data.write_off.reason', 'unsold')
            ->assertJsonPath('data.write_off.unit', 'piece')
            ->assertJsonPath('data.write_off.business_date', '2026-11-14')
            ->assertJsonPath('data.write_off.shift_id', null)
            ->assertJsonPath('data.batch.quantity_left', 6.5)
            ->assertJsonPath('data.batch.written_off_quantity', 3.5);
        $this->assertSame('6.500', $batch->fresh()->getRawOriginal('quantity_left'));
        $this->assertSame('10.000', $batch->fresh()->getRawOriginal('quantity_produced'));

        $row = MenuBatchWriteOff::firstOrFail();
        $this->assertSame($this->owner->id, (int) $row->written_off_by);
        $this->assertSame($this->branch->id, (int) $row->branch_id);

        $log = AuditLog::where('action', 'menu.batch_written_off')->firstOrFail();
        $this->assertSame($this->owner->id, (int) $log->user_id);
        $this->assertSame('unsold', $log->payload_json['reason']);
        $this->assertSame(10, $log->before['quantity_left']);
        $this->assertSame(6.5, $log->after['quantity_left']);

        // The batch has portions left: still on sale; the menus were told of the count change
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn ($event) => $event->reason === 'batch' && $event->items[0]['available'] === true);
    }

    public function test_the_rest_can_be_written_off_and_the_batch_closed_in_one_step(): void
    {
        $batch = $this->openBatch('4');
        $this->writeOff($batch, ['quantity' => '4', 'reason' => 'spoiled', 'close' => true])->assertCreated()
            ->assertJsonPath('data.batch.is_open', false)->assertJsonPath('data.batch.quantity_left', 0);
        $this->assertNotNull($batch->fresh()->closed_at);
        $this->assertNotNull($batch->fresh()->sold_out_at);

        // A closed batch cannot be written off any more
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'unsold'])->assertStatus(422)->assertJsonValidationErrors('batch');
        $this->assertSame(1, MenuBatchWriteOff::count());
    }

    public function test_writing_everything_off_sells_the_dish_out(): void
    {
        $batch = $this->openBatch('2');
        $this->writeOff($batch, ['quantity' => '2', 'reason' => 'staff_meal'])->assertCreated();

        $this->app['auth']->forgetGuards();
        $availability = collect($this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}")->json('data.branches'))->firstWhere('branch_id', $this->branch->id);
        $this->assertEqualsCanonicalizing([$this->portion->id, $this->small->id], $availability['sold_out']['variants']);
        $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 1]])->assertStatus(422);
    }

    public function test_the_amount_and_the_reason_are_checked(): void
    {
        $batch = $this->openBatch('5');

        $this->writeOff($batch, ['quantity' => '5.001', 'reason' => 'unsold'])->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->writeOff($batch, ['quantity' => '0', 'reason' => 'unsold'])->assertStatus(422);
        $this->writeOff($batch, ['quantity' => '-1', 'reason' => 'unsold'])->assertStatus(422);
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'theft'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->writeOff($batch, ['quantity' => '1'])->assertStatus(422)->assertJsonValidationErrors('reason');
        // "Boshqa" needs a note
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'other'])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'other', 'note' => 'Mehmonlarga tarqatildi'])->assertCreated();
        $this->assertSame(1, MenuBatchWriteOff::count());
        $this->assertSame('4.000', $batch->fresh()->getRawOriginal('quantity_left'));
    }

    public function test_a_retry_with_the_same_key_writes_off_once(): void
    {
        $batch = $this->openBatch('5');
        $key = (string) Str::uuid();

        $this->writeOff($batch, ['quantity' => '2', 'reason' => 'unsold'], null, $key)->assertCreated();
        $this->writeOff($batch, ['quantity' => '2', 'reason' => 'unsold'], null, $key)->assertCreated();

        $this->assertSame(1, MenuBatchWriteOff::count());
        $this->assertSame('3.000', $batch->fresh()->getRawOriginal('quantity_left'));
    }

    public function test_the_module_gates_the_write_off_and_another_branch_is_out_of_reach(): void
    {
        $batch = $this->openBatch('5');

        $this->enableModules(modules: ['batch_writeoff' => false, 'batches' => true]);
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'unsold'])->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('data.module', 'batch_writeoff');
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-writeoffs?branch_id={$this->branch->id}")->assertForbidden();
        $this->assertSame(0, MenuBatchWriteOff::count());
        // The existing batch screens are not touched by the module
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batches?branch_id={$this->branch->id}")->assertOk();

        // Store-wide the module is on in another branch only: the record's own branch still decides
        $this->enableModules($this->otherBranch);
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'unsold'])->assertForbidden()->assertJsonPath('code', 'module_disabled');

        // A cashier has no batches.manage
        $this->enableModules();
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'unsold'], $this->pinSession($this->till, $this->cashier))->assertForbidden();
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'unsold'])->assertCreated();
    }

    public function test_a_terminal_write_off_belongs_to_its_shift_and_is_on_the_z_report(): void
    {
        $batch = $this->openBatch('8');
        $shift = $this->openShift();
        $this->managerToken = $this->pinSession($this->till, $this->manager, self::MANAGER_PIN);

        // From the panel: no shift
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'spoiled'])->assertCreated()->assertJsonPath('data.write_off.shift_id', null);

        $this->writeOff($batch, ['quantity' => '2', 'reason' => 'unsold'], $this->managerToken)->assertCreated()
            ->assertJsonPath('data.write_off.shift_id', $shift->id);
        $this->writeOff($batch, ['quantity' => '0.5', 'reason' => 'unsold'], $this->managerToken)->assertCreated();
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'dropped'], $this->managerToken)->assertCreated();

        $x = $this->pos('GET', "/shifts/{$shift->uuid}/x-report", [], $this->managerToken)->assertOk()->json('data.report.batch_writeoffs');
        $this->assertSame(3, $x['count']);
        $items = collect($x['items'])->keyBy('reason');
        $this->assertSame(2.5, $items['unsold']['quantity']);
        $this->assertSame('Sotilmay qoldi', $items['unsold']['reason_label']);
        $this->assertSame('Osh', $items['unsold']['product_name']);
        $this->assertSame(1, $items['dropped']['quantity']);
        $this->assertArrayNotHasKey('spoiled', $items->all());

        // The Z report keeps the block
        $closed = $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 1]], $this->managerToken)->assertOk();
        $this->assertSame(3, $closed->json('data.summary.batch_writeoffs.count'));
        $this->assertSame(2.5, $closed->json('data.summary.batch_writeoffs.items.0.quantity'));
        $this->assertSame(3, PosShift::find($shift->id)->summary['batch_writeoffs']['count']);
    }

    public function test_the_branch_lists_its_write_offs_for_a_period(): void
    {
        $batch = $this->openBatch('8');
        $this->writeOff($batch, ['quantity' => '1', 'reason' => 'spoiled', 'note' => 'Idish sinib'])->assertCreated();

        $list = $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-writeoffs?branch_id={$this->branch->id}")->assertOk()->json('data');
        $this->assertSame('2026-11-14', $list['from']);
        $this->assertCount(1, $list['write_offs']);
        $this->assertSame('Buzildi', $list['write_offs'][0]['reason_label']);
        $this->assertSame('Idish sinib', $list['write_offs'][0]['note']);
        $this->assertSame($this->owner->name, $list['write_offs'][0]['written_off_by']['name']);
        $this->assertContains('staff_meal', array_column($list['reasons'], 'key'));

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-writeoffs?branch_id={$this->branch->id}&from=2026-11-01&to=2026-11-13")
            ->assertOk()->assertJsonCount(0, 'data.write_offs');
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-writeoffs?branch_id={$this->branch->id}&from=2026-11-05&to=2026-11-01")->assertStatus(422);
    }

    public function test_the_yield_report_explains_what_became_of_a_batch(): void
    {
        $batch = $this->openBatch('10');

        // 4 sold (3 whole + one 0.7 = 3.7), one order canceled (gives its portions back), 2 written off
        $first = $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 3], ['variant_id' => $this->small->uuid, 'quantity' => 1]])->assertCreated();
        $second = $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 2]])->assertCreated();
        $this->setStatus(Order::where('uuid', $second->json('data.order_uuid'))->firstOrFail(), $this->owner, 'canceled')->assertOk();
        $this->writeOff($batch, ['quantity' => '2', 'reason' => 'unsold'])->assertCreated();
        // A recount nobody explained: 4.3 left became 4
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/batches/{$batch->id}/adjust", ['quantity_left' => '2'])->assertOk();

        $report = $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-yield?branch_id={$this->branch->id}")->assertOk()->json('data');

        $this->assertSame('2026-11-14', $report['from']);
        $this->assertCount(1, $report['batches']);
        $row = $report['batches'][0];
        $this->assertSame(10, $row['produced']);
        $this->assertSame(3.7, $row['sold']);
        $this->assertSame(2, $row['written_off']);
        $this->assertSame(2, $row['left']);
        // 10 - 3.7 - 2 - 2 = 2.3: the recount (4.3 -> 2) nobody explained
        $this->assertSame(2.3, $row['variance']);
        $this->assertSame('37.0', $row['sold_percent']);
        $this->assertSame('20.0', $row['written_off_percent']);
        $this->assertSame('piece', $row['unit']);
        $this->assertSame('2026-11-14', $row['business_date']);
        $this->assertTrue($row['is_open']);

        $day = $report['days'][0];
        $this->assertSame('Osh', $day['product_name']);
        $this->assertSame(10, $day['produced']);
        $this->assertSame(3.7, $day['sold']);
        $this->assertSame(1, $day['batches']);

        $this->assertSame([['reason' => 'unsold', 'label' => 'Sotilmay qoldi', 'count' => 1, 'quantity' => 2]], $report['write_offs']);
        $this->assertSame([], $report['in_sets']);
        $this->assertTrue($first->json('success'));
    }

    public function test_the_yield_report_covers_business_days_and_checks_the_period(): void
    {
        // A batch cooked on the 13th (late evening local) and one on the 14th
        Carbon::setTestNow(Carbon::parse('2026-11-13 14:00:00', 'UTC'));
        $this->openBatch('6');
        MenuBatch::query()->update(['closed_at' => now()]);
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:00:00', 'UTC'));
        $this->openBatch('9');

        $url = "/api/v1/owner/batch-yield?branch_id={$this->branch->id}";
        $this->actingAs($this->owner, 'sanctum')->getJson("{$url}&from=2026-11-14&to=2026-11-14")->assertOk()->assertJsonCount(1, 'data.batches')->assertJsonPath('data.batches.0.produced', 9);
        $both = $this->actingAs($this->owner, 'sanctum')->getJson("{$url}&from=2026-11-13&to=2026-11-14")->assertOk()->json('data');
        $this->assertSame([9, 6], array_column($both['days'], 'produced'));
        $this->assertSame(['2026-11-14', '2026-11-13'], array_column($both['days'], 'business_date'));
        $this->actingAs($this->owner, 'sanctum')->getJson("{$url}&from=2026-01-01&to=2026-11-14")->assertStatus(422)->assertJsonValidationErrors('to');

        // A cashier has neither reports.view nor batches.manage
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->cashier, 'sanctum')->getJson($url)->assertForbidden();
    }
}
