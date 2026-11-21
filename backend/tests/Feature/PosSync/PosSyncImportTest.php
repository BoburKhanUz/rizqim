<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Sync\PosSyncImport;
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
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Wave M5 S4-b (docs/scale/M5_DESIGN.md §5, "As built: the import"): the rescue file a till wrote
 * (POST /owner/pos/sync/import, the partner panel). The file's MAC is checked first; after that every operation is
 * applied as its till through the live sync's own path — the same idempotency, signatures and store mode — and
 * nothing refuses the whole file. A payment is never dropped.
 */
class PosSyncImportTest extends TestCase
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

    protected function tearDown(): void
    {
        PosSyncImport::$timeBudget = 20.0;

        parent::tearDown();
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
     * The till's file with those lines, under its current key.
     *
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

    public function test_a_file_is_applied_as_its_till_and_audited(): void
    {
        $sale = $this->walkInSale();

        $response = $this->import($this->file($this->sign($sale['ops'])))->assertOk()
            ->assertJsonPath('data.complete', true)
            ->assertJsonPath('data.stopped_reason', null)
            ->assertJsonPath('data.file.device', $this->uuid)
            ->assertJsonPath('data.file.count', 4)
            ->assertJsonPath('data.file.first_seq', 1)
            ->assertJsonPath('data.file.last_seq', 4)
            ->assertJsonPath('data.device.uuid', $this->uuid)
            ->assertJsonPath('data.device.last_seq', 4)
            ->assertJsonCount(4, 'data.results')
            ->assertJsonPath('data.skipped', []);
        $this->assertSame(
            ['operations' => 4, 'applied' => 4, 'conflict' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'pending' => 0, 'renumbered' => 0],
            $response->json('data.totals'),
        );
        foreach ($sale['ops'] as $k => $op) {
            $response->assertJsonPath("data.results.{$k}.op_id", $op['op_id'])
                ->assertJsonPath("data.results.{$k}.status", 'applied')
                ->assertJsonPath("data.results.{$k}.sig_status", 'ok')
                ->assertJsonPath("data.results.{$k}.duplicate", false)
                ->assertJsonPath("data.results.{$k}.device", $this->uuid)
                // (line 1 of the file is the header)
                ->assertJsonPath("data.results.{$k}.line", $k + 2);
        }

        // The sale is on the server, as that till's
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertNotNull($order->closed_at);
        $this->assertSame(1, $this->payments($sale['order']));
        $this->assertSame(4, PosSyncOperation::where('device_id', $this->till['device']->id)->count());
        // ...and the chain went on as after a live sync: the till's next operation follows it
        $this->sync($this->sign([$this->op('order.served', ['order' => $sale['order']])]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'ok');

        $audit = AuditLog::where('action', 'pos.sync_imported')->sole();
        $this->assertSame([$this->owner->id, $this->store->id, $this->till['device']->id], [$audit->user_id, $audit->store_id, (int) $audit->model_id]);
        $this->assertSame(4, $audit->payload_json['totals']['applied']);
        $this->assertSame([1, 4, true], [$audit->payload_json['first_seq'], $audit->payload_json['last_seq'], $audit->payload_json['complete']]);
        // Never a key, a mac or what the operations carried
        $stored = (string) json_encode($audit->payload_json);
        $this->assertStringNotContainsString($this->key['key'], $stored);
        $this->assertStringNotContainsString('tenders', $stored);
        $this->assertStringNotContainsString($this->chainPrev, $stored);
        $this->assertStringNotContainsString($this->key['key'], $response->getContent());
    }

    public function test_the_same_file_twice_changes_nothing(): void
    {
        $sale = $this->walkInSale();
        $file = $this->file($this->sign($sale['ops']));
        $this->import($file)->assertOk()->assertJsonPath('data.totals.applied', 4);
        $rows = PosSyncOperation::count();

        $again = $this->import($file)->assertOk()->assertJsonPath('data.complete', true)->assertJsonCount(4, 'data.results');

        $this->assertSame(
            ['operations' => 4, 'applied' => 0, 'conflict' => 0, 'rejected' => 0, 'duplicate' => 4, 'skipped' => 0, 'pending' => 0, 'renumbered' => 0],
            $again->json('data.totals'),
        );
        foreach (range(0, 3) as $k) {
            $again->assertJsonPath("data.results.{$k}.duplicate", true)->assertJsonPath("data.results.{$k}.status", 'applied');
        }
        $this->assertSame($rows, PosSyncOperation::count());
        $this->assertSame(1, $this->payments($sale['order']));
        $this->assertSame(1, Order::where('external_id', $sale['order'])->count());
        // Both uploads are in the journal
        $this->assertSame(2, AuditLog::where('action', 'pos.sync_imported')->count());
    }

    public function test_a_file_imported_after_the_till_sent_half_of_it_applies_the_rest(): void
    {
        $sale = $this->walkInSale();
        $envelopes = $this->sign($sale['ops']);
        // The connection came back for a moment: the order and its bill arrived by themselves
        $this->sync(array_slice($envelopes, 0, 2))->assertOk()->assertJsonPath('data.results.1.status', 'applied');

        $response = $this->import($this->file($envelopes))->assertOk();

        $this->assertSame([true, true, false, false], array_column($response->json('data.results'), 'duplicate'));
        $this->assertSame(['applied', 'applied', 'applied', 'applied'], array_column($response->json('data.results'), 'status'));
        // The chain is whole across the two ways in
        $this->assertSame(['ok', 'ok', 'ok', 'ok'], array_column($response->json('data.results'), 'sig_status'));
        $response->assertJsonPath('data.totals.applied', 2)->assertJsonPath('data.totals.duplicate', 2);
        $this->assertSame(1, $this->payments($sale['order']));
        $this->assertNotNull(Order::where('external_id', $sale['order'])->firstOrFail()->closed_at);
    }

    public function test_a_line_changed_on_the_tills_disk_is_that_lines_answer_not_the_files(): void
    {
        $sale = $this->walkInSale();
        $envelopes = $this->sign($sale['ops']);
        // Edited in the journal before the export: the file's own mac is right, the line's is not
        $envelopes[0]['signed'] = str_replace('"local_number":"K1-001"', '"local_number":"K1-009"', $envelopes[0]['signed'], $count);
        $this->assertSame(1, $count);

        $response = $this->import($this->file($envelopes))->assertOk()->assertJsonCount(4, 'data.results');

        $this->assertSame(['bad', 'ok', 'ok', 'ok'], array_column($response->json('data.results'), 'sig_status'));
        // Observe mode: applied as it arrived and recorded, like a live sync
        $this->assertSame('K1-009', Order::where('external_id', $sale['order'])->firstOrFail()->local_number);
        $this->assertSame(1, $this->payments($sale['order']));
    }

    public function test_a_line_changed_after_the_export_refuses_the_whole_file(): void
    {
        $sale = $this->walkInSale();
        $file = $this->file($this->sign($sale['ops']));
        $tampered = str_replace('41000', '14000', $file, $count);
        $this->assertGreaterThan(0, $count);

        $this->import($tampered)->assertStatus(422)->assertJsonPath('data.reason', 'file_mac_invalid');
        // ...and so does a trailer that is not this file's
        $this->import(preg_replace('/"file_mac":"[0-9a-f]{64}"/', '"file_mac":"' . str_repeat('ab', 32) . '"', $file))
            ->assertStatus(422)->assertJsonPath('data.reason', 'file_mac_invalid');
        // ...a body line removed, a line added
        $lines = explode("\n", $file);
        $this->import(implode("\n", [...array_slice($lines, 0, 2), ...array_slice($lines, 3)]))->assertStatus(422)->assertJsonPath('data.reason', 'file_mac_invalid');
        $this->import(implode("\n", [...array_slice($lines, 0, 2), $lines[1], ...array_slice($lines, 2)]))->assertStatus(422)->assertJsonPath('data.reason', 'file_mac_invalid');

        $this->assertSame(0, PosSyncOperation::count());
        $this->assertSame(0, Order::where('external_id', $sale['order'])->count());
        $this->assertSame(0, AuditLog::where('action', 'pos.sync_imported')->count());
    }

    public function test_a_file_signed_with_another_tills_key_is_refused(): void
    {
        $other = $this->pairTerminal($this->owner, $this->branch);
        $sale = $this->walkInSale();
        // The header names this till, the mac is made with the other till's key
        $file = self::exportFile($this->uuid, $this->key['kid'], (string) base64_decode($other['sync_key']['key'], true), $this->sign($sale['ops']));

        $this->import($file)->assertStatus(422)->assertJsonPath('data.reason', 'file_mac_invalid');
        $this->assertSame(0, PosSyncOperation::count());
    }

    public function test_an_unknown_version_and_a_file_of_another_kind_are_refused_in_plain_words(): void
    {
        $key = (string) base64_decode($this->key['key'], true);
        $newer = self::exportFile($this->uuid, $this->key['kid'], $key, $this->sign($this->walkInSale()['ops']), null, ['version' => 2]);

        $this->import($newer)->assertStatus(422)->assertJsonPath('data.reason', 'version_unsupported')
            ->assertJsonPath('message', "Fayl versiyasi (2) bu serverga noma'lum: fayl yangiroq kassa ilovasida yozilgan. RIZQIM qo'llab-quvvatlashiga yozing.");
        $this->import("%PDF-1.7\nnot a queue\n")->assertStatus(422)->assertJsonPath('data.reason', 'not_export_file');
        $this->import('')->assertStatus(422);
        $this->assertSame(0, PosSyncOperation::count());
    }

    public function test_another_stores_till_is_not_found(): void
    {
        $foreign = $this->makeStore('Boshqa oshxona');
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreign);
        $file = $this->file($this->sign($this->walkInSale()['ops']));

        // A perfectly good file of this store's till, uploaded by another store's owner
        $this->import($file, $foreignOwner)->assertStatus(404)->assertJsonPath('data.reason', 'device_not_found');
        $this->assertSame(0, PosSyncOperation::count());
        $this->assertSame(0, AuditLog::where('action', 'pos.sync_imported')->count());

        // ...and a manager bound to another branch of this store does not reach this branch's till either
        $elsewhere = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->import($file, $elsewhere)->assertStatus(404);
        $this->assertSame(0, PosSyncOperation::count());
        // The branch's own manager does
        $this->import($file, $this->manager)->assertOk()->assertJsonPath('data.totals.applied', 4);
    }

    public function test_only_who_manages_devices_imports_and_never_from_a_pin_session(): void
    {
        $file = $this->file($this->sign($this->walkInSale()['ops']));

        $this->import($file, $this->cashier)->assertStatus(403);
        // The manager's PIN session on the till: a terminal is for order work only
        $pin = $this->pinSession($this->till, $this->manager, self::MANAGER_PIN);
        $this->withBearer($pin)->post('/api/v1/owner/pos/sync/import', ['file' => UploadedFile::fake()->createWithContent('q.rzq', $file)], ['Accept' => 'application/json'])->assertStatus(403);
        // The till's own token neither
        $this->withBearer($this->till['token'])->post('/api/v1/owner/pos/sync/import', ['file' => UploadedFile::fake()->createWithContent('q.rzq', $file)], ['Accept' => 'application/json'])->assertStatus(401);
        $this->withBearer(null)->post('/api/v1/owner/pos/sync/import', [], ['Accept' => 'application/json'])->assertStatus(401);
        $this->import('x', $this->owner)->assertStatus(422);

        $this->assertSame(0, PosSyncOperation::count());
    }

    public function test_a_disconnected_tills_file_is_still_taken(): void
    {
        $sale = $this->walkInSale();
        $file = $this->file($this->sign($sale['ops']));
        // The till died with its queue; the owner disconnected it afterwards (its token is gone with it)
        app(DeviceService::class)->revoke($this->till['device'], $this->owner, false, true);
        $this->sync([], [])->assertStatus(401);

        Carbon::setTestNow(now()->addDays(3));
        $response = $this->import($file)->assertOk()->assertJsonPath('data.complete', true);

        $this->assertSame(['applied', 'applied', 'applied', 'applied'], array_column($response->json('data.results'), 'status'));
        $this->assertSame(['ok', 'ok', 'ok', 'ok'], array_column($response->json('data.results'), 'sig_status'));
        $this->assertNotNull($response->json('data.device.revoked_at'));
        $this->assertSame(1, $this->payments($sale['order']));
        $this->assertNotNull(Order::where('external_id', $sale['order'])->firstOrFail()->closed_at);
    }

    public function test_after_the_key_was_erased_the_file_is_refused_in_plain_words(): void
    {
        $file = $this->file($this->sign($this->walkInSale()['ops']));
        app(DeviceService::class)->revoke($this->till['device'], $this->owner, false, true);
        Carbon::setTestNow(now()->addDays(SyncSignature::KEEP_REVOKED_DAYS + 1));
        $this->assertSame(1, SyncSignature::eraseExpired());

        $this->import($file)->assertStatus(422)->assertJsonPath('data.reason', 'key_unavailable');
        $this->assertSame(0, PosSyncOperation::count());
    }

    public function test_operations_dated_after_the_till_was_disconnected_are_refused_one_by_one_and_money_is_still_recorded(): void
    {
        $before = $this->walkInSale('K1-001');
        // Disconnected 15 minutes ago; the till, offline, went on selling
        Carbon::setTestNow(now()->subMinutes(15));
        app(DeviceService::class)->revoke($this->till['device'], $this->owner, false, true);
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));

        $order = (string) Str::uuid();
        $after = [
            $this->op('order.open', ['order' => $order, 'local_number' => 'K1-002', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->tea, '1')], 'send' => true, 'menu_taken_at' => now()->subHour()->toJSON()], null, 5),
            // A payment taken on the first order after the disconnection (its bill is paid: recorded beside it)
            $this->op('bill.pay', ['order' => $before['order'], 'bill' => $before['bill'], 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '7000', 'tendered_amount' => '7000']]], null, 4),
        ];

        $response = $this->import($this->file($this->sign([...$before['ops'], ...$after])))->assertOk()->assertJsonCount(6, 'data.results');

        // What was done before the disconnection is applied
        $this->assertSame(['applied', 'applied', 'applied', 'applied'], array_slice(array_column($response->json('data.results'), 'status'), 0, 4));
        // The order opened after it is not: kept for the manager, nothing created
        $response->assertJsonPath('data.results.4.status', 'rejected')->assertJsonPath('data.results.4.reason', 'device_revoked')->assertJsonPath('data.results.4.sig_status', 'ok');
        $this->assertSame(0, Order::where('external_id', $order)->count());
        // The payment after it is money in the drawer all the same: recorded, flagged, never dropped
        $response->assertJsonPath('data.results.5.status', 'conflict');
        $this->assertNotSame('rejected', $response->json('data.results.5.status'));
        $this->assertSame(2, $this->payments($before['order']));
        $this->assertSame('7000.00', (string) Payment::where('order_id', Order::where('external_id', $before['order'])->value('id'))->orderByDesc('id')->value('amount'));
        $this->assertStringContainsString('device_revoked', (string) json_encode($response->json('data.results.5.refs')));
    }

    public function test_in_enforce_mode_an_unverified_payment_of_the_file_is_recorded_not_dropped(): void
    {
        $this->store->forceFill(['pos_sync_enforce_signatures' => true])->save();
        $sale = $this->walkInSale();
        $envelopes = $this->sign($sale['ops']);
        // The payment's line was edited in the journal (40 000 instead of 41 000)
        $envelopes[2]['signed'] = str_replace('"amount":"41000"', '"amount":"40000"', $envelopes[2]['signed'], $count);
        $this->assertSame(1, $count);

        $response = $this->import($this->file($envelopes))->assertOk();

        $response->assertJsonPath('data.results.2.status', 'conflict')
            ->assertJsonPath('data.results.2.reason', 'payment_unverified')
            ->assertJsonPath('data.results.2.sig_status', 'bad');
        $this->assertSame(1, $this->payments($sale['order']));
        $this->assertSame('40000.00', (string) Payment::where('order_id', Order::where('external_id', $sale['order'])->value('id'))->value('amount'));
        $response->assertJsonPath('data.totals.conflict', 1)->assertJsonPath('data.totals.skipped', 0);

        // ...and an unsigned payment line of the file the same way
        $second = $this->walkInSale('K1-002');
        $lines = [...$this->sign(array_slice($second['ops'], 0, 2)), $second['ops'][2], $second['ops'][3]];
        $again = $this->import($this->file($lines))->assertOk();
        $again->assertJsonPath('data.results.2.status', 'conflict')->assertJsonPath('data.results.2.reason', 'payment_unverified')->assertJsonPath('data.results.2.sig_status', 'missing');
        $this->assertSame(1, $this->payments($second['order']));
    }

    public function test_a_line_that_holds_no_operation_is_skipped_and_the_rest_is_applied(): void
    {
        $sale = $this->walkInSale();
        $envelopes = $this->sign($sale['ops']);
        $prev = $this->chainPrev;
        // Signed text that is no operation, and one that names itself but lost its payload
        $junk = ['signed' => '{"hello":1}', 'mac' => str_repeat('a', 64), 'prev' => $prev, 'kid' => $this->key['kid']];
        $broken = $this->sign([['op_id' => (string) Str::uuid(), 'seq' => ++$this->seq, 'type' => 'order.served', 'occurred_at' => now()->toJSON(), 'staff_id' => $this->cashier->id]])[0];

        $response = $this->import($this->file([...$envelopes, $junk, $broken]))->assertOk()->assertJsonPath('data.complete', true);

        $this->assertSame(1, $this->payments($sale['order']));
        $response->assertJsonCount(5, 'data.results')->assertJsonCount(1, 'data.skipped')
            ->assertJsonPath('data.skipped.0.reason', 'invalid_payload')
            ->assertJsonPath('data.skipped.0.op_id', null)
            ->assertJsonPath('data.results.4.status', 'rejected')
            ->assertJsonPath('data.results.4.reason', 'invalid_payload')
            ->assertJsonPath('data.totals.skipped', 1);
        $this->assertIsInt($response->json('data.skipped.0.line'));
    }

    public function test_numbers_already_taken_by_other_operations_get_the_next_free_ones_and_no_payment_is_lost(): void
    {
        // The till lost its journal with a sale in it; an older file of that sale survived on a USB stick
        $sale = $this->walkInSale();
        $file = $this->file($this->sign($sale['ops']));
        // Meanwhile the reinstalled till went on from the server's numbers (1, 2) with other operations
        $this->seq = 0;
        $this->chainPrev = null;
        $this->sync($this->sign([
            $this->op('order.served', ['order' => (string) Str::uuid()]),
            $this->op('order.served', ['order' => (string) Str::uuid()]),
        ]))->assertOk()->assertJsonCount(2, 'data.results');

        $response = $this->import($file)->assertOk()->assertJsonPath('data.complete', true)->assertJsonCount(4, 'data.results');

        $this->assertSame([3, 4, 5, 6], array_column($response->json('data.results'), 'seq'));
        $this->assertSame([1, 2, 3, 4], array_column($response->json('data.results'), 'renumbered_from'));
        $response->assertJsonPath('data.totals.renumbered', 4)->assertJsonPath('data.totals.skipped', 0);
        $this->assertSame(1, $this->payments($sale['order']));
        $this->assertNotNull(Order::where('external_id', $sale['order'])->firstOrFail()->closed_at);
        // Their text is the till's own (the mac holds); their place in the chain is not
        $this->assertNotContains('bad', array_column($response->json('data.results'), 'sig_status'));

        // The same file again: nothing new, whatever the numbers
        $again = $this->import($file)->assertOk();
        $this->assertSame([true, true, true, true], array_column($again->json('data.results'), 'duplicate'));
        $this->assertSame(1, $this->payments($sale['order']));
        $this->assertSame(6, PosSyncOperation::where('device_id', $this->till['device']->id)->count());
    }

    public function test_lines_of_an_earlier_pairing_are_applied_as_that_device(): void
    {
        // The sale was queued under the first pairing; the till was disconnected and paired again (a new device row)
        $sale = $this->walkInSale();
        $old = $this->sign($sale['ops']);
        app(DeviceService::class)->revoke($this->till['device'], $this->owner, false, true);
        $again = $this->pairTerminal($this->owner, $this->branch);
        $newUuid = (string) $again['device']->uuid;

        $this->seq = 0;
        $this->chainPrev = null;
        $own = $this->signed([$this->op('order.served', ['order' => $sale['order']])], $again['sync_key'], $newUuid);
        $file = self::exportFile($newUuid, $again['sync_key']['kid'], (string) base64_decode($again['sync_key']['key'], true), [
            ...array_map(fn (array $line) => $line + ['dev' => $this->uuid], $old),
            ...$own,
        ]);

        $response = $this->import($file)->assertOk()->assertJsonCount(5, 'data.results')->assertJsonPath('data.file.device', $newUuid);

        // The four of the earlier pairing first, as that device and under its key; then the new pairing's own
        $this->assertSame([$this->uuid, $this->uuid, $this->uuid, $this->uuid, $newUuid], array_column($response->json('data.results'), 'device'));
        $this->assertSame(['ok', 'ok', 'ok', 'ok', 'ok'], array_column($response->json('data.results'), 'sig_status'));
        $this->assertSame(4, PosSyncOperation::where('device_id', $this->till['device']->id)->count());
        $this->assertSame(1, PosSyncOperation::where('device_id', $again['device']->id)->count());
        $this->assertSame(1, $this->payments($sale['order']));

        // A line that names a device the store does not have is skipped, nothing more
        $this->seq = 1;
        $stray = $this->signed([$this->op('order.served', ['order' => $sale['order']])], $again['sync_key'], $newUuid);
        $unknown = self::exportFile($newUuid, $again['sync_key']['kid'], (string) base64_decode($again['sync_key']['key'], true), [$stray[0] + ['dev' => (string) Str::uuid()]]);
        $this->import($unknown)->assertOk()->assertJsonCount(0, 'data.results')->assertJsonPath('data.skipped.0.reason', 'device_unknown');
    }

    public function test_a_long_file_is_taken_in_parts_by_uploading_it_again(): void
    {
        // 60 operations: more than one batch
        $operations = [];
        foreach (range(1, 60) as $k) {
            $operations[] = $this->op('order.served', ['order' => (string) Str::uuid()]);
        }
        $file = $this->file($this->sign($operations));
        // No time left after the first batch
        PosSyncImport::$timeBudget = -1.0;

        $first = $this->import($file)->assertOk()
            ->assertJsonPath('data.complete', false)
            ->assertJsonPath('data.stopped_reason', 'time_budget')
            ->assertJsonPath('data.totals.pending', 10)
            ->assertJsonCount(50, 'data.results');
        $this->assertSame(50, PosSyncOperation::count());
        $this->assertFalse(AuditLog::where('action', 'pos.sync_imported')->sole()->payload_json['complete']);

        $second = $this->import($file)->assertOk()->assertJsonPath('data.complete', false)->assertJsonPath('data.totals.duplicate', 50)->assertJsonPath('data.totals.pending', 10);
        $this->assertSame(50, PosSyncOperation::count());

        PosSyncImport::$timeBudget = 20.0;
        $this->import($file)->assertOk()->assertJsonPath('data.complete', true)->assertJsonPath('data.totals.duplicate', 50)->assertJsonPath('data.totals.pending', 0);
        $this->assertSame(60, PosSyncOperation::count());
        $this->assertSame(60, (int) $this->till['device']->fresh()->sync_last_seq);
        unset($first, $second);
    }

    public function test_the_queue_the_till_last_reported_is_lowered_by_what_the_import_recorded(): void
    {
        $sale = $this->walkInSale();
        $this->till['device']->forceFill(['sync_pending_ops' => 4, 'sync_oldest_op_at' => now()->subHour()])->save();

        $this->import($this->file($this->sign($sale['ops'])))->assertOk()->assertJsonPath('data.device.pending_ops', 0);

        $device = Device::findOrFail($this->till['device']->id);
        $this->assertSame([0, null], [(int) $device->sync_pending_ops, $device->sync_oldest_op_at]);
    }

    public function test_bootstrap_tells_the_till_whether_the_server_takes_export_files(): void
    {
        $this->tillAs('GET', '/pos/bootstrap')->assertOk()->assertJsonPath('data.offline.export', true);

        config(['pos.sync_signing' => false]);
        $this->tillAs('GET', '/pos/bootstrap')->assertOk()->assertJsonPath('data.offline.export', false)->assertJsonPath('data.offline.signing', false);
    }
}
