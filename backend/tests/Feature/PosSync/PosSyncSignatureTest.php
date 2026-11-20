<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Sync\SyncSignature;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave M5 S3-a (docs/scale/M5_DESIGN.md §4, test plan 7.1-7): the signed offline queue in observe mode. Every operation
 * gets a `sig_status` (ok, missing, bad, chain_gap) and is applied exactly as before; a batch is never refused because
 * of a signature; the old unsigned shape stays valid.
 */
class PosSyncSignatureTest extends TestCase
{
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
     * @return list<string|null>
     */
    private function statuses(): array
    {
        return PosSyncOperation::where('device_id', $this->till['device']->id)->orderBy('seq')->pluck('sig_status')->all();
    }

    public function test_bootstrap_says_this_server_takes_signed_operations(): void
    {
        $this->assertTrue($this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.offline.signing'));
    }

    public function test_a_signed_queue_is_verified_and_applied_as_before(): void
    {
        $sale = $this->walkInSale();
        $envelopes = $this->signed($sale['ops'], $this->key, $this->uuid);

        $response = $this->sync($envelopes)->assertOk();

        foreach ($sale['ops'] as $k => $op) {
            $response->assertJsonPath("data.results.{$k}.op_id", $op['op_id'])
                ->assertJsonPath("data.results.{$k}.seq", $op['seq'])
                ->assertJsonPath("data.results.{$k}.status", 'applied');
        }
        $this->assertSame(['ok', 'ok', 'ok', 'ok'], $this->statuses());
        $this->assertSame(array_column($envelopes, 'mac'), PosSyncOperation::where('device_id', $this->till['device']->id)->orderBy('seq')->pluck('mac')->all());
        $this->assertSame($this->key['kid'] . ':' . $envelopes[3]['mac'], Device::findOrFail($this->till['device']->id)->sync_chain);
        // The operation is what the signed text says, stored as before
        $this->assertSame($sale['ops'][0]['payload']['local_number'], PosSyncOperation::where('op_id', $sale['ops'][0]['op_id'])->firstOrFail()->payload['local_number']);
        $this->assertSame('41000.00', (string) Payment::where('order_id', Order::where('external_id', $sale['order'])->firstOrFail()->id)->firstOrFail()->amount);

        // The next batch follows the chain; the same batch again is answered from what the server holds
        $next = $this->signed($this->walkInSale('K1-002')['ops'], $this->key, $this->uuid);
        $this->sync($envelopes)->assertOk()->assertJsonPath('data.results.0.duplicate', true);
        $this->sync($next)->assertOk();
        $this->assertSame(array_fill(0, 8, 'ok'), $this->statuses());
    }

    public function test_the_old_unsigned_shape_is_still_taken_and_recorded_as_missing(): void
    {
        $this->sync($this->walkInSale()['ops'])->assertOk()->assertJsonPath('data.results.3.status', 'applied');
        $this->assertSame(['missing', 'missing', 'missing', 'missing'], $this->statuses());

        // A till paired before signing existed: no key on the server
        Device::whereKey($this->till['device']->id)->update(['sync_key' => null, 'sync_key_id' => 0]);
        $this->sync($this->walkInSale('K1-002')['ops'])->assertOk()->assertJsonPath('data.results.3.status', 'applied');
        $this->assertSame(array_fill(0, 8, 'missing'), $this->statuses());
    }

    public function test_a_line_edited_by_hand_is_bad_and_in_observe_mode_still_applied(): void
    {
        $sale = $this->walkInSale();
        $envelopes = $this->signed($sale['ops'], $this->key, $this->uuid);
        // The cash amount changed in the journal; the attacker has no key, so the mac stays
        $envelopes[2]['signed'] = str_replace('"amount":"41000"', '"amount":"40000"', $envelopes[2]['signed'], $count);
        $this->assertSame(1, $count);

        $response = $this->sync($envelopes)->assertOk();

        $this->assertSame(['ok', 'ok', 'bad', 'ok'], $this->statuses());
        // Money is never dropped: the payment is on the server with the amount that arrived
        $this->assertNotSame('rejected', $response->json('data.results.2.status'));
        $this->assertSame('40000.00', (string) Payment::where('order_id', Order::where('external_id', $sale['order'])->firstOrFail()->id)->firstOrFail()->amount);
    }

    public function test_a_removed_line_is_a_chain_gap_and_what_arrived_is_applied(): void
    {
        $first = $this->signed($this->walkInSale()['ops'], $this->key, $this->uuid);
        $this->sync($first)->assertOk();

        $sale = $this->walkInSale('K1-002');
        $void = $this->op('order.served', ['order' => $sale['order']]);
        $envelopes = $this->signed([...array_slice($sale['ops'], 0, 1), $void, ...array_slice($sale['ops'], 1)], $this->key, $this->uuid);
        // Fix the numbers as the journal had them: the removed line's number is simply missing on the server
        unset($envelopes[1]);

        $this->sync(array_values($envelopes))->assertOk()->assertJsonCount(4, 'data.results');

        $this->assertSame(['ok', 'ok', 'ok', 'ok', 'ok', 'chain_gap', 'ok', 'ok'], $this->statuses());
        $this->assertSame(8, PosSyncOperation::where('device_id', $this->till['device']->id)->whereIn('status', ['applied', 'conflict'])->count());
    }

    public function test_a_skipped_number_is_a_chain_gap_even_when_the_macs_follow(): void
    {
        $this->sync($this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $this->key, $this->uuid))->assertOk();
        $this->seq += 3;
        $this->sync($this->signed([$this->op('order.served', ['order' => (string) Str::uuid()]), $this->op('order.served', ['order' => (string) Str::uuid()])], $this->key, $this->uuid))->assertOk();

        $this->assertSame(['ok', 'chain_gap', 'ok'], $this->statuses());
    }

    public function test_a_batch_is_never_refused_over_a_signature_whatever_its_fields_hold(): void
    {
        $ops = [$this->op('order.served', ['order' => (string) Str::uuid()]), $this->op('order.served', ['order' => (string) Str::uuid()]), $this->op('order.served', ['order' => (string) Str::uuid()]), $this->op('order.served', ['order' => (string) Str::uuid()])];
        $envelopes = $this->signed($ops, $this->key, $this->uuid);
        $envelopes[0]['mac'] = ['not' => 'a mac'];
        $envelopes[1]['prev'] = 12;
        $envelopes[2]['kid'] = 'x';
        unset($envelopes[3]['mac'], $envelopes[3]['prev'], $envelopes[3]['kid']);

        $this->sync($envelopes)->assertOk()->assertJsonCount(4, 'data.results');

        $this->assertSame(['bad', 'bad', 'bad', 'bad'], $this->statuses());
        // The mac an operation came with is kept when it looks like one
        $this->assertSame([null, $envelopes[1]['mac'], $envelopes[2]['mac'], null], PosSyncOperation::where('device_id', $this->till['device']->id)->orderBy('seq')->pluck('mac')->all());
    }

    public function test_the_signed_text_is_the_operation_whatever_is_written_beside_it(): void
    {
        $sale = $this->walkInSale();
        $envelopes = $this->signed($sale['ops'], $this->key, $this->uuid);
        // Plain fields next to a signed operation are not the operation: nobody signed them
        $envelopes[2] += ['type' => 'bill.pay', 'op_id' => (string) Str::uuid(), 'seq' => 3, 'staff_id' => $this->manager->id, 'payload' => ['order' => $sale['order'], 'bill' => $sale['bill'], 'tenders' => [['tender' => 'cash', 'amount' => '1']]]];

        $this->sync($envelopes)->assertOk()->assertJsonPath('data.results.2.op_id', $sale['ops'][2]['op_id']);

        $this->assertSame(['ok', 'ok', 'ok', 'ok'], $this->statuses());
        $this->assertSame($this->cashier->id, PosSyncOperation::where('op_id', $sale['ops'][2]['op_id'])->firstOrFail()->staff_id);
        $this->assertSame('41000.00', (string) Payment::where('order_id', Order::where('external_id', $sale['order'])->firstOrFail()->id)->firstOrFail()->amount);
    }

    public function test_a_signed_operation_is_read_as_an_unsigned_one_would_be(): void
    {
        // Empty and padded strings: the request middleware makes them null / trimmed for the old shape; the signed
        // text is decoded here and must come out the same
        $plain = $this->op('order.served', ['order' => (string) Str::uuid(), 'note' => '', 'who' => '  Ali  ']);
        $this->sync([$plain])->assertOk();
        $signed = $this->op('order.served', ['order' => $plain['payload']['order'], 'note' => '', 'who' => '  Ali  ']);
        $this->sync($this->signed([$signed], $this->key, $this->uuid))->assertOk();

        $rows = PosSyncOperation::where('device_id', $this->till['device']->id)->orderBy('seq')->get();
        $this->assertSame($rows[0]->payload, $rows[1]->payload);
        $this->assertSame(['missing', 'ok'], $this->statuses());
    }

    public function test_signed_text_that_is_not_an_operation_never_refuses_the_batch(): void
    {
        // (the review after S3: a 422 for one unusable line would stop the till's queue behind it for good)
        $envelopes = $this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $this->key, $this->uuid);
        $envelopes[0]['signed'] = 'not json';

        $this->sync($envelopes)->assertOk()->assertJsonPath('data.results', [])->assertJsonPath('data.unreadable.0.index', 0)->assertJsonPath('data.unreadable.0.reason', 'invalid_payload');
        $this->assertSame(0, PosSyncOperation::count());

        // A type this server does not know names itself: recorded, rejected, its signature checked like any other
        $unknown = $this->op('order.served', ['order' => (string) Str::uuid()]);
        $unknown['type'] = 'order.teleport';
        $this->chainPrev = null;
        $this->sync($this->signed([$unknown], $this->key, $this->uuid))->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'type_unknown')
            ->assertJsonPath('data.unreadable', []);
        $this->assertSame(['ok'], $this->statuses());
    }

    public function test_after_a_rotation_the_old_keys_operations_still_verify_and_the_new_key_starts_its_own_chain(): void
    {
        $old = $this->key;
        $this->sync($this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $old, $this->uuid))->assertOk();
        // Queued under the old key, not sent yet (the till should not rotate now, but the server copes)
        $queued = $this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $old, $this->uuid);

        $new = $this->withBearer($this->till['token'])->postJson('/api/v1/terminal/sync-key')->assertOk()->json('data');
        $this->assertSame(2, $new['kid']);
        $this->chainPrev = null;
        $fresh = $this->signed([$this->op('order.served', ['order' => (string) Str::uuid()]), $this->op('order.served', ['order' => (string) Str::uuid()])], $new, $this->uuid);

        $this->sync([...$queued, ...$fresh])->assertOk();
        $this->assertSame(['ok', 'ok', 'ok', 'ok'], $this->statuses());
        $this->assertSame('2:' . $fresh[1]['mac'], Device::findOrFail($this->till['device']->id)->sync_chain);

        // An old key's operation after the new key's: out of order
        $late = $this->op('order.served', ['order' => (string) Str::uuid()]);
        $this->chainPrev = $queued[0]['mac'];
        $this->sync($this->signed([$late], $old, $this->uuid))->assertOk();
        $this->assertSame('chain_gap', PosSyncOperation::where('op_id', $late['op_id'])->value('sig_status'));

        // Two rotations later the first key is gone
        $this->withBearer($this->till['token'])->postJson('/api/v1/terminal/sync-key')->assertOk()->assertJsonPath('data.kid', 3);
        $gone = $this->op('order.served', ['order' => (string) Str::uuid()]);
        $this->sync($this->signed([$gone], $old, $this->uuid))->assertOk();
        $this->assertSame('bad', PosSyncOperation::where('op_id', $gone['op_id'])->value('sig_status'));
    }

    public function test_renumbered_operations_signed_again_are_taken_after_a_seq_reused_refusal(): void
    {
        // The server holds numbers 1-2 of this till; the till's journal was lost and counts from 1 again
        $this->sync($this->signed([$this->op('order.served', ['order' => (string) Str::uuid()]), $this->op('order.served', ['order' => (string) Str::uuid()])], $this->key, $this->uuid))->assertOk();
        $this->seq = 0;
        $this->chainPrev = null;
        $lost = [$this->op('order.served', ['order' => (string) Str::uuid()]), $this->op('order.served', ['order' => (string) Str::uuid()])];

        $this->sync($this->signed($lost, $this->key, $this->uuid))->assertStatus(409)->assertJsonPath('data.reason', 'seq_reused');
        $this->assertSame(2, PosSyncOperation::count());
        $head = Device::findOrFail($this->till['device']->id)->sync_chain;

        // SyncStore.adoptServerSeq: the server's last_seq, the waiting operations renumbered after it and signed again
        $last = $this->withBearer($this->till['token'])->getJson('/api/v1/pos/sync/status')->assertOk()->json('data.device.last_seq');
        $this->assertSame(2, $last);
        $lost[0]['seq'] = $last + 1;
        $lost[1]['seq'] = $last + 2;
        $this->chainPrev = null;
        $this->sync($this->signed($lost, $this->key, $this->uuid))->assertOk()->assertJsonPath('data.results.1.seq', 4);

        // The numbers follow; the chain does not (the till no longer knows the server's head): said once, then ok
        $this->assertSame(['ok', 'ok', 'chain_gap', 'ok'], $this->statuses());
        $this->assertNotSame($head, Device::findOrFail($this->till['device']->id)->sync_chain);
    }

    public function test_a_chain_the_device_row_missed_is_picked_up_from_the_last_operation(): void
    {
        $this->sync($this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $this->key, $this->uuid))->assertOk();
        // The process died between the operation's row and the device row
        Device::whereKey($this->till['device']->id)->update(['sync_chain' => null, 'sync_last_seq' => 0]);

        $this->sync($this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $this->key, $this->uuid))->assertOk();

        $this->assertSame(['ok', 'ok'], $this->statuses());
    }

    public function test_a_batch_that_waited_for_the_tills_lock_sees_what_the_one_before_it_left(): void
    {
        // The device row as the waiting request read it when it signed in
        $stale = Device::findOrFail($this->till['device']->id);
        $first = $this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $this->key, $this->uuid);
        $this->sync([...$first, $this->op('order.served', ['order' => (string) Str::uuid()])])->assertOk();

        $op = $this->op('order.served', ['order' => (string) Str::uuid()]);
        $envelope = $this->signed([$op], $this->key, $this->uuid)[0];
        app(\App\Domain\Pos\Sync\PosSyncService::class)->sync($stale, [$op + ['signature' => ['raw' => $envelope['signed'], 'mac' => $envelope['mac'], 'prev' => $envelope['prev'], 'kid' => $envelope['kid']]]], now(), null);

        $this->assertSame(['ok', 'missing', 'ok'], $this->statuses());
    }

    public function test_a_key_the_server_cannot_read_never_fails_the_batch(): void
    {
        DB::table('devices')->where('id', $this->till['device']->id)->update(['sync_key' => 'not-encrypted']);

        $this->sync($this->signed($this->walkInSale()['ops'], $this->key, $this->uuid))->assertOk()->assertJsonPath('data.results.3.status', 'applied');

        $this->assertSame(['bad', 'bad', 'bad', 'bad'], $this->statuses());
    }

    public function test_the_status_answer_never_carries_the_key_or_the_chain(): void
    {
        $this->sync($this->signed([$this->op('order.served', ['order' => (string) Str::uuid()])], $this->key, $this->uuid))->assertOk();

        foreach (['/api/v1/pos/sync/status', '/api/v1/terminal'] as $uri) {
            $body = (string) $this->withBearer($this->till['token'])->getJson($uri)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/"sync_key"|sync_key_prev|sync_chain/', $body);
            $this->assertStringNotContainsString($this->key['key'], $body);
            $this->assertStringNotContainsString(str_replace('/', '\/', $this->key['key']), $body);
        }
        $this->assertSame(SyncSignature::KEY_BYTES, strlen((string) base64_decode($this->key['key'], true)));
    }
}
