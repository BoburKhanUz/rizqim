<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Sync\PosSyncException;
use App\Domain\Pos\Sync\PosSyncImport;
use App\Domain\Pos\Sync\PosSyncService;
use App\Domain\Pos\Sync\SyncSignature;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use App\Models\User;
use App\Services\Auth\DeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Wave M5 S4 fix F1 (docs/scale/M5_DESIGN.md §5, the reviews after S4): what the import of a rescue file must do and
 * did not —
 *  - a sale made after the till was disconnected is applied as a conflict `device_revoked` and its payment recorded
 *    (only a concession is refused, as in enforce mode); the same for an operation older than the history the server
 *    keeps (`operation_too_old`);
 *  - a `dev` line is taken only for a disconnected earlier pairing in the same branch and only when its signature is
 *    the till's own; another device's reported queue is never touched;
 *  - the till's own sync holding the lock is `sync_in_progress` (never a 500), and an import that failed half-way is
 *    audited with what it did;
 *  - the device row's number never goes back, an import is not the till reporting in, a refused file is audited, a
 *    file whose earlier numbers the server lacks says so.
 */
class PosSyncImportReviewTest extends TestCase
{
    use BuildsExportFiles;
    use PosSyncFixtures;
    use RefreshDatabase;
    use SignsOperations;

    /** @var array{key: string, kid: int} */
    private array $key;

    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
        $this->key = $this->till['sync_key'];
        $this->uuid = (string) $this->till['device']->uuid;
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return list<array<string, mixed>>
     */
    private function sign(array $operations): array
    {
        return $this->signed($operations, $this->key, $this->uuid);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function file(array $lines): string
    {
        return self::exportFile($this->uuid, $this->key['kid'], (string) base64_decode($this->key['key'], true), $lines);
    }

    private function import(string $file, ?User $user = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user ?? $this->owner, 'sanctum')->post('/api/v1/owner/pos/sync/import', [
            'file' => UploadedFile::fake()->createWithContent('rizqim-navbat.rzq', $file),
        ], ['Accept' => 'application/json']);
    }

    private function payments(string $orderUuid): int
    {
        $order = Order::where('external_id', $orderUuid)->first();

        return $order === null ? 0 : Payment::where('order_id', $order->id)->count();
    }

    private function revokeMinutesAgo(int $minutes): void
    {
        Carbon::setTestNow(now()->subMinutes($minutes));
        app(DeviceService::class)->revoke($this->till['device'], $this->owner, false, true);
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));
    }

    /**
     * The till paired again: its earlier pairing disconnected, the new one's key and uuid.
     *
     * @return array{device: Device, sync_key: array{key: string, kid: int}, uuid: string}
     */
    private function pairedAgain(): array
    {
        app(DeviceService::class)->revoke($this->till['device'], $this->owner, false, true);
        $again = $this->pairTerminal($this->owner, $this->branch);

        return ['device' => $again['device'], 'sync_key' => $again['sync_key'], 'uuid' => (string) $again['device']->uuid];
    }

    // R1 HIGH-1 -------------------------------------------------------------------------------------------------------

    public function test_a_sale_made_after_the_till_was_disconnected_is_applied_as_a_conflict_and_its_payment_recorded(): void
    {
        // Disconnected 15 minutes ago; the till, offline, went on selling: a new walk-in sale after it
        $this->revokeMinutesAgo(15);
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();
        $ops = [
            $this->op('order.open', ['order' => $order, 'local_number' => 'K1-009', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1')], 'send' => true, 'menu_taken_at' => now()->subHour()->toJSON()], null, 10),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '41000'], null, 9),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '41000', 'tendered_amount' => '50000']]], null, 8),
            $this->op('order.close', ['order' => $order], null, 8),
        ];

        $response = $this->import($this->file($this->sign($ops)))->assertOk()->assertJsonPath('data.complete', true);

        // Nothing of the sale is dropped: every operation is a conflict for the manager
        $this->assertSame(['conflict', 'conflict', 'conflict', 'conflict'], array_column($response->json('data.results'), 'status'));
        $this->assertSame(['device_revoked', 'device_revoked', 'staff_unverified', 'device_revoked'], array_column($response->json('data.results'), 'reason'));
        $this->assertStringContainsString('device_revoked', (string) json_encode($response->json('data.results.2.refs')));
        // The 41 000 in the drawer is a payment on the server, the order is there and closed
        $this->assertSame(1, $this->payments($order));
        $this->assertSame('41000.00', (string) Payment::where('order_id', Order::where('external_id', $order)->value('id'))->value('amount'));
        $this->assertNotNull(Order::where('external_id', $order)->firstOrFail()->closed_at);
        $response->assertJsonPath('data.totals.conflict', 4)->assertJsonPath('data.totals.rejected', 0);
    }

    public function test_a_discount_and_a_void_made_after_the_disconnection_are_refused_and_the_sale_still_paid(): void
    {
        $this->revokeMinutesAgo(15);
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();
        $tea = (string) Str::uuid();
        $ops = [
            $this->op('order.open', ['order' => $order, 'local_number' => 'K1-010', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1', null, $tea)], 'send' => false, 'menu_taken_at' => now()->subHour()->toJSON()], null, 10),
            $this->op('order.void', ['order' => $order, 'line' => $tea, 'reason' => 'Xato kiritildi'], null, 9),
            $this->op('order.discount', ['order' => $order, 'kind' => 'percent', 'value' => '10', 'reason' => 'Doimiy mijoz', 'expected_discount' => '3600'], null, 9),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '32400'], null, 8),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '32400', 'tendered_amount' => '32400']]], null, 8),
        ];

        $response = $this->import($this->file($this->sign($ops)))->assertOk();

        $this->assertSame(['conflict', 'rejected', 'rejected', 'conflict', 'conflict'], array_column($response->json('data.results'), 'status'));
        $response->assertJsonPath('data.results.1.reason', 'device_revoked')
            ->assertJsonPath('data.results.2.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.2.refs.cause', 'device_revoked');
        // The bill was made at the full price (nothing voided, no discount); the money taken is recorded all the same
        $this->assertSame(1, $this->payments($order));
        $this->assertSame('32400.00', (string) Payment::where('order_id', Order::where('external_id', $order)->value('id'))->value('amount'));
    }

    public function test_an_operation_older_than_the_history_the_server_keeps_is_a_conflict_and_its_payment_recorded(): void
    {
        // A file found on a USB stick 13 months later: its rows may have been pruned, so its sale could be applied twice
        $minutes = (int) now()->diffInMinutes(now()->subMonths(PosSyncImport::HISTORY_MONTHS)->subMonth(), true);
        $sale = $this->walkInSale();
        foreach ($sale['ops'] as $k => $op) {
            $sale['ops'][$k]['occurred_at'] = now()->subMinutes($minutes - $k)->toJSON();
        }
        $discount = $this->op('order.discount', ['order' => $sale['order'], 'kind' => 'percent', 'value' => '10', 'reason' => 'Doimiy mijoz', 'expected_discount' => '4100'], null, $minutes);

        $response = $this->import($this->file($this->sign([...$sale['ops'], $discount])))->assertOk();

        $this->assertSame(['conflict', 'conflict', 'conflict', 'conflict', 'rejected'], array_column($response->json('data.results'), 'status'));
        $this->assertSame(['operation_too_old', 'operation_too_old', 'staff_unverified', 'operation_too_old', 'discount_not_allowed'], array_column($response->json('data.results'), 'reason'));
        $this->assertSame(1, $this->payments($sale['order']));
    }

    public function test_a_relayed_payment_after_the_disconnection_gets_the_answer_a_live_sync_gives_and_is_never_the_hubs(): void
    {
        $sale = $this->walkInSale();
        $waiterPhone = $this->pairTerminal($this->owner, $this->branch);
        $relayed = $sale['ops'];
        $relayed[2]['origin_device'] = (string) $waiterPhone['device']->uuid;
        // What a live sync answers to a payment relayed for another terminal
        $live = $this->sync([$this->op('bill.pay', $relayed[2]['payload']) + ['origin_device' => (string) $waiterPhone['device']->uuid]])->assertOk();
        $this->assertSame('rejected', $live->json('data.results.0.status'));
        PosSyncOperation::query()->delete();
        $this->seq = 0;
        foreach ($relayed as $k => $op) {
            $relayed[$k]['seq'] = $k + 1;
        }
        $this->revokeMinutesAgo(60);

        $response = $this->import($this->file($this->sign($relayed)))->assertOk();

        $response->assertJsonPath('data.results.2.status', 'rejected')->assertJsonPath('data.results.2.reason', $live->json('data.results.0.reason'));
        // No payment was written in the hub's name for another terminal's operation
        $this->assertSame(0, AuditLog::where('action', 'pos.offline_payment_staff_unverified')->count());
    }

    // R2 MEDIUM-S1 ----------------------------------------------------------------------------------------------------

    public function test_a_dev_line_for_a_till_that_is_still_connected_is_skipped(): void
    {
        $b = $this->pairTerminal($this->owner, $this->branch);
        $this->pinSession($b, $this->cashier);
        $bUuid = (string) $b['device']->uuid;
        $b['device']->forceFill(['sync_pending_ops' => 4])->save();
        $sale = $this->walkInSale('K9-001');
        $lines = array_map(fn (array $op) => $op + ['dev' => $bUuid], $sale['ops']);

        $response = $this->import($this->file($lines))->assertOk()->assertJsonCount(0, 'data.results')->assertJsonCount(4, 'data.skipped');

        $this->assertSame(['device_unknown'], array_values(array_unique(array_column($response->json('data.skipped'), 'reason'))));
        $this->assertSame(0, PosSyncOperation::where('device_id', $b['device']->id)->count());
        $this->assertSame(0, Order::where('external_id', $sale['order'])->count());
        $this->assertSame(4, (int) $b['device']->fresh()->sync_pending_ops);
    }

    public function test_a_dev_line_for_a_disconnected_till_of_another_branch_is_skipped(): void
    {
        $other = $this->pairTerminal($this->owner, $this->otherBranch);
        app(DeviceService::class)->revoke($other['device'], $this->owner, false, true);
        $otherUuid = (string) $other['device']->uuid;
        // Even signed with that till's own key
        $line = $this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $other['sync_key'], $otherUuid)[0];

        $this->import($this->file([$line + ['dev' => $otherUuid]]))->assertOk()->assertJsonCount(0, 'data.results')
            ->assertJsonPath('data.skipped.0.reason', 'device_unknown');
        $this->assertSame(0, PosSyncOperation::where('device_id', $other['device']->id)->count());
    }

    public function test_a_dev_line_of_an_earlier_pairing_is_taken_only_with_that_pairings_signature(): void
    {
        $sale = $this->walkInSale();
        $old = $this->sign($sale['ops']);
        $again = $this->pairedAgain();
        $this->till['device']->forceFill(['sync_pending_ops' => 9])->save();
        $this->seq = 0;
        $this->chainPrev = null;
        $own = $this->signed([$this->op('order.served', ['order' => $sale['order']])], $again['sync_key'], $again['uuid']);
        $newKey = (string) base64_decode($again['sync_key']['key'], true);

        // Unsigned, and signed with the new pairing's key instead of the earlier one's: nobody's word
        $unsigned = array_map(fn (array $op) => $op + ['dev' => $this->uuid], $sale['ops']);
        $this->chainPrev = null;
        $wrongKey = array_map(fn (array $line) => $line + ['dev' => $this->uuid], $this->signed($sale['ops'], $again['sync_key'], $this->uuid));
        foreach ([$unsigned, $wrongKey] as $lines) {
            $response = $this->import(self::exportFile($again['uuid'], $again['sync_key']['kid'], $newKey, [...$lines, ...$own]))->assertOk();
            $this->assertSame(['signature_invalid'], array_values(array_unique(array_column($response->json('data.skipped'), 'reason'))));
            $response->assertJsonCount(4, 'data.skipped');
            $this->assertSame(0, PosSyncOperation::where('device_id', $this->till['device']->id)->count());
        }

        // Its own signature: applied as that device; the earlier pairing's reported queue is not touched
        $response = $this->import(self::exportFile($again['uuid'], $again['sync_key']['kid'], $newKey, [...array_map(fn (array $line) => $line + ['dev' => $this->uuid], $old), ...$own]))->assertOk();
        $response->assertJsonCount(0, 'data.skipped');
        $this->assertSame(4, PosSyncOperation::where('device_id', $this->till['device']->id)->count());
        $this->assertSame(1, $this->payments($sale['order']));
        $this->assertSame(9, (int) $this->till['device']->fresh()->sync_pending_ops);
    }

    // R2 MEDIUM-S2 ----------------------------------------------------------------------------------------------------

    public function test_a_real_lock_held_by_the_till_is_sync_in_progress_with_what_was_done_audited(): void
    {
        $sale = $this->walkInSale();
        $old = $this->sign($sale['ops']);
        $again = $this->pairedAgain();
        $this->seq = 0;
        $this->chainPrev = null;
        $own = $this->signed([$this->op('order.served', ['order' => $sale['order']])], $again['sync_key'], $again['uuid']);
        $file = self::exportFile($again['uuid'], $again['sync_key']['kid'], (string) base64_decode($again['sync_key']['key'], true), [
            ...array_map(fn (array $line) => $line + ['dev' => $this->uuid], $old),
            ...$own,
        ]);

        // The new till is sending a batch right now: it holds its own lock (a real lock: Lock::block() throws; its
        // 10 s of waiting pass on the test clock)
        Sleep::fake(syncWithCarbon: true);
        $held = Cache::lock(PosSyncService::lockName($again['device']), 120);
        $this->assertTrue($held->get());

        $response = $this->import($file)->assertOk()
            ->assertJsonPath('data.complete', false)
            ->assertJsonPath('data.stopped_reason', 'sync_in_progress')
            ->assertJsonPath('data.totals.pending', 1);
        $held->release();

        $this->assertSame(4, PosSyncOperation::where('device_id', $this->till['device']->id)->count());
        $audit = AuditLog::where('action', 'pos.sync_imported')->sole();
        $this->assertSame([false, 'sync_in_progress', 4], [$audit->payload_json['complete'], $audit->payload_json['stopped_reason'], $audit->payload_json['totals']['applied']]);
        $this->assertSame(4, $response->json('data.totals.applied'));
    }

    public function test_a_live_sync_waiting_for_the_lock_is_refused_sync_in_progress(): void
    {
        Sleep::fake(syncWithCarbon: true);
        $held = Cache::lock(PosSyncService::lockName($this->till['device']), 120);
        $this->assertTrue($held->get());

        try {
            app(PosSyncService::class)->sync($this->till['device'], [], null, null);
            $this->fail('the batch was not refused');
        } catch (PosSyncException $e) {
            $this->assertSame('sync_in_progress', $e->reason);
        } finally {
            $held->release();
        }
    }

    public function test_an_import_that_fails_half_way_is_audited_with_what_it_did(): void
    {
        $sale = $this->walkInSale();
        $old = $this->sign($sale['ops']);
        $again = $this->pairedAgain();
        $this->seq = 0;
        $this->chainPrev = null;
        $own = $this->signed([$this->op('order.served', ['order' => $sale['order']])], $again['sync_key'], $again['uuid']);
        $file = self::exportFile($again['uuid'], $again['sync_key']['kid'], (string) base64_decode($again['sync_key']['key'], true), [
            ...array_map(fn (array $line) => $line + ['dev' => $this->uuid], $old),
            ...$own,
        ]);
        // Something nobody expected breaks at the second device's batch (not the database: nothing answers it)
        $failing = (int) $again['device']->id;
        PosSyncOperation::creating(function (PosSyncOperation $row) use ($failing) {
            if ((int) $row->device_id === $failing) {
                throw new RuntimeException('the disk went away');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->import($file);
            $this->fail('the failure was swallowed');
        } catch (RuntimeException $e) {
            $this->assertSame('the disk went away', $e->getMessage());
        }

        $audit = AuditLog::where('action', 'pos.sync_imported')->sole();
        $this->assertSame([false, 'error', 4], [$audit->payload_json['complete'], $audit->payload_json['stopped_reason'], $audit->payload_json['totals']['applied']]);
        $this->assertSame(4, PosSyncOperation::where('device_id', $this->till['device']->id)->count());
    }

    // R1 LOW ----------------------------------------------------------------------------------------------------------

    public function test_the_device_rows_last_number_never_goes_back_when_a_batch_had_a_stale_device(): void
    {
        $stale = Device::findOrFail($this->till['device']->id);
        $ops = $this->sign([
            $this->op('order.served', ['order' => (string) Str::uuid()]),
            $this->op('order.served', ['order' => (string) Str::uuid()]),
            $this->op('order.served', ['order' => (string) Str::uuid()]),
        ]);
        $this->sync($ops)->assertOk();
        $this->assertSame(3, (int) Device::findOrFail($stale->id)->sync_last_seq);

        // A batch that only repeats the first operation, with the device as it was read before the till's own sync
        $first = json_decode($ops[0]['signed'], true);
        app(PosSyncService::class)->sync($stale, [$first], null, null);

        $this->assertSame(3, (int) Device::findOrFail($stale->id)->sync_last_seq);
    }

    public function test_an_import_is_not_the_till_reporting_in(): void
    {
        $reported = now()->subDays(2)->startOfSecond();
        $this->till['device']->forceFill(['sync_reported_at' => $reported])->save();

        $this->import($this->file($this->sign($this->walkInSale()['ops'])))->assertOk()->assertJsonPath('data.totals.applied', 4);

        $device = Device::findOrFail($this->till['device']->id);
        $this->assertSame($reported->toJSON(), $device->sync_reported_at?->toJSON());
        $this->assertSame(4, (int) $device->sync_last_seq);
    }

    public function test_a_file_whose_earlier_numbers_the_server_lacks_says_so_and_is_still_applied(): void
    {
        // The second of two files (numbers 5..8), uploaded first
        $this->seq = 4;
        $sale = $this->walkInSale();
        $response = $this->import($this->file($this->sign($sale['ops'])))->assertOk()->assertJsonPath('data.totals.applied', 4);

        $response->assertJsonCount(1, 'data.warnings')
            ->assertJsonPath('data.warnings.0.reason', 'earlier_numbers_missing')
            ->assertJsonPath('data.warnings.0.device', $this->uuid)
            ->assertJsonPath('data.warnings.0.first_seq', 5)
            ->assertJsonPath('data.warnings.0.server_last_seq', 0);
        $this->assertNotEmpty($response->json('data.warnings.0.message'));
        $this->assertSame(1, $this->payments($sale['order']));
    }

    public function test_a_file_that_follows_what_the_server_holds_has_no_warning(): void
    {
        $this->sync($this->sign([$this->op('order.served', ['order' => (string) Str::uuid()])]))->assertOk();
        $this->import($this->file($this->sign($this->walkInSale()['ops'])))->assertOk()->assertJsonCount(0, 'data.warnings');

        // ...nor the first file of a till
        PosSyncOperation::query()->delete();
        $this->seq = 0;
        $this->chainPrev = null;
        $this->import($this->file($this->sign($this->walkInSale('K1-002')['ops'])))->assertOk()->assertJsonCount(0, 'data.warnings');
    }

    // R2 LOW ----------------------------------------------------------------------------------------------------------

    public function test_a_refused_file_is_audited_with_its_reason_and_device(): void
    {
        $file = $this->file($this->sign($this->walkInSale()['ops']));

        // A wrong file MAC
        $this->import(str_replace('41000', '14000', $file))->assertStatus(422)->assertJsonPath('data.reason', 'file_mac_invalid');
        // Another store's owner: not their till
        $foreign = $this->makeStore('Boshqa oshxona');
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreign);
        $this->import($file, $foreignOwner)->assertStatus(404)->assertJsonPath('data.reason', 'device_not_found');
        // The key was erased
        app(DeviceService::class)->revoke($this->till['device'], $this->owner, false, true);
        Carbon::setTestNow(now()->addDays(SyncSignature::KEEP_REVOKED_DAYS + 1));
        SyncSignature::eraseExpired();
        $this->import($file)->assertStatus(422)->assertJsonPath('data.reason', 'key_unavailable');

        $rows = AuditLog::where('action', PosSyncImport::AUDIT_REFUSED)->orderBy('id')->get();
        $this->assertSame(['file_mac_invalid', 'device_not_found', 'key_unavailable'], $rows->map(fn (AuditLog $row) => $row->payload_json['reason'])->all());
        $this->assertSame([$this->uuid, $this->uuid, $this->uuid], $rows->map(fn (AuditLog $row) => $row->payload_json['device'])->all());
        // Each in the journal of the store that tried: the other store never sees this till's row, nor this one theirs
        $this->assertSame([$this->store->id, $foreign->id, $this->store->id], $rows->map(fn (AuditLog $row) => (int) $row->store_id)->all());
        $this->assertSame([$this->owner->id, $foreignOwner->id, $this->owner->id], $rows->map(fn (AuditLog $row) => (int) $row->user_id)->all());
        $this->assertNull($rows[1]->model_id);
        $this->assertSame(0, AuditLog::where('action', PosSyncImport::AUDIT)->count());
        $this->assertStringNotContainsString($this->key['key'], (string) json_encode($rows->pluck('payload_json')->all()));
    }
}
