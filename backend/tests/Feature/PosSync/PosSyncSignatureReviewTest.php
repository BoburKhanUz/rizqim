<?php

namespace Tests\Feature\PosSync;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * Wave M5 S3, the fixes after the two independent reviews (docs/scale/M5_DESIGN.md §4):
 *  - enforce never drops a sale: an unsigned or badly signed operation that builds a sale is applied and shown to the
 *    manager (`signature_invalid`); only concessions (a discount, a void) are refused;
 *  - one unreadable signed line never refuses the batch;
 *  - the chain: the key order comes first, a `bad` line never moves the head, a till that came back from a build that
 *    does not sign starts its chain again without a false alarm;
 *  - the owner's `chain_gap` message is throttled by when the owner was told;
 *  - a till counts as signing only once the server has a verified operation from it under its current key.
 */
class PosSyncSignatureReviewTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;
    use SignsOperations;

    /** @var array{key: string, kid: int} */
    private array $key;

    private string $uuid;

    private FakeTelegramMessenger $telegram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
        $this->key = $this->till['sync_key'];
        $this->uuid = (string) $this->till['device']->uuid;
        $this->telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $this->telegram);
    }

    private function enforce(bool $on = true): void
    {
        $this->store->forceFill(['pos_sync_enforce_signatures' => $on])->save();
    }

    /**
     * @return list<string>
     */
    private function ownerAlerts(): array
    {
        return array_values(array_map(fn (array $sent) => $sent['text'], array_filter($this->telegram->sent, fn (array $sent) => $sent['chat'] === (string) $this->owner->telegram_id)));
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
     * @return array<string, mixed>
     */
    private function served(): array
    {
        return $this->op('order.served', ['order' => (string) Str::uuid()]);
    }

    /**
     * @return list<string|null>
     */
    private function statuses(): array
    {
        return PosSyncOperation::where('device_id', $this->till['device']->id)->orderBy('seq')->pluck('sig_status')->all();
    }

    // --- 1. Enforce never drops a sale (supervisor decision D1) ---------------------------------------------------

    public function test_in_enforce_an_unsigned_sale_is_in_the_books_and_the_cashier_closes_the_shift(): void
    {
        $this->enforce();
        $sale = $this->walkInSale();

        // Unsigned, as an app from before signing sends it
        $response = $this->sync($sale['ops'])->assertOk()->assertJsonCount(4, 'data.results');

        foreach ([0, 1, 3] as $k) {
            $response->assertJsonPath("data.results.{$k}.status", 'conflict')
                ->assertJsonPath("data.results.{$k}.reason", 'signature_invalid')
                ->assertJsonPath("data.results.{$k}.sig_status", 'missing');
        }
        $response->assertJsonPath('data.results.2.status', 'conflict')->assertJsonPath('data.results.2.reason', 'payment_unverified');
        $this->assertNotContains('order_not_found', array_column($response->json('data.results'), 'reason'));

        // The sale is in the books: the order, its bill, the money on that bill, the order closed
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('41000.00', (string) $payment->amount);
        $this->assertNotNull($payment->bill_id);
        $this->assertSame($this->shift->id, $payment->shift_id);
        $this->assertNotNull($order->closed_at);
        // Applied, and for the manager to look at: "Sinxronlash xatolari" shows each with what its signature was worth
        $row = PosSyncOperation::where('op_id', $sale['ops'][0]['op_id'])->firstOrFail();
        $this->assertNotNull($row->applied_at);
        $this->assertStringContainsString('imzosiz', (string) $row->error);
        $this->assertCount(4, $this->tillAs('GET', '/pos/sync/errors')->assertOk()->json('data.errors'));

        // Nothing rejected: the cashier closes the shift, nobody has to force it
        $this->assertSame(0, PosSyncOperation::where('status', 'rejected')->count());
        $close = $this->tillAs('POST', '/pos/shifts/' . $this->shift->uuid . '/close', ['counted_breakdown' => ['100000' => 1, '10000' => 4, '1000' => 1]]);
        $this->assertNotSame('shift_has_sync_errors', $close->json('data.reason'));
        $close->assertOk();
    }

    public function test_in_enforce_a_well_signed_payment_is_not_lost_because_the_orders_line_was_edited(): void
    {
        $this->enforce();
        $sale = $this->walkInSale();
        $signed = $this->sign($sale['ops']);
        // One line edited by hand, its mac left as it was
        $signed[0]['signed'] = str_replace('"local_number":"K1-001"', '"local_number":"K1-999"', $signed[0]['signed'], $count);
        $this->assertSame(1, $count);

        $response = $this->sync($signed)->assertOk();

        $response->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'signature_invalid')
            ->assertJsonPath('data.results.0.sig_status', 'bad')
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.2.sig_status', 'ok');
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertSame('41000.00', (string) Payment::where('order_id', $order->id)->sole()->amount);
        $this->assertNotNull($order->closed_at);
    }

    public function test_in_enforce_an_unsigned_void_is_a_concession_and_is_refused(): void
    {
        $this->enforce();
        $order = (string) Str::uuid();
        $line = (string) Str::uuid();
        $open = $this->op('order.open', ['order' => $order, 'local_number' => 'K1-001', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1', null, $line)], 'send' => false, 'menu_taken_at' => now()->subHour()->toJSON()]);
        $this->sync($this->sign([$open]))->assertOk()->assertJsonPath('data.results.0.status', 'applied');

        $void = $this->op('order.void', ['order' => $order, 'line' => $line, 'reason' => 'Xato kiritildi']);
        $this->sync([$void])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'signature_invalid')
            ->assertJsonPath('data.results.0.sig_status', 'missing');

        // The line is still on the order: the sale is not made smaller by a line nobody signed
        $server = Order::where('external_id', $order)->firstOrFail();
        $this->assertSame(2, $server->items()->get()->filter(fn ($item) => !$item->isVoid())->count());
    }

    public function test_in_observe_mode_nothing_is_flagged_signature_invalid(): void
    {
        $this->sync($this->walkInSale()['ops'])->assertOk()->assertJsonPath('data.results.0.status', 'applied')->assertJsonPath('data.results.3.status', 'applied');
        $this->assertSame(0, PosSyncOperation::where('reason', 'signature_invalid')->count());
    }

    // --- 3. One unreadable signed line never refuses the batch ----------------------------------------------------

    public function test_one_unreadable_signed_line_never_refuses_the_batch(): void
    {
        $sale = $this->walkInSale();
        $signed = $this->sign($sale['ops']);
        $bill = json_decode($signed[1]['signed'], true);

        // The payload key removed: the line still says which operation it was
        $broken = $signed;
        $broken[1] = ['signed' => json_encode(array_diff_key($bill, ['payload' => null]))] + $signed[1];
        $response = $this->sync($broken)->assertOk()->assertJsonCount(4, 'data.results');

        $response->assertJsonPath('data.results.1.op_id', $bill['op_id'])
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'invalid_payload')
            ->assertJsonPath('data.results.1.sig_status', 'bad')
            ->assertJsonPath('data.unreadable', []);
        $this->assertSame(4, PosSyncOperation::count());
        // What the rest of the queue carried is not held up: the order is open and the money recorded
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertSame('41000.00', (string) Payment::where('order_id', $order->id)->sole()->amount);
        // The same batch again is answered from what the server holds
        $this->sync($broken)->assertOk()->assertJsonPath('data.results.1.duplicate', true)->assertJsonPath('data.results.1.reason', 'invalid_payload');
        $this->assertSame(4, PosSyncOperation::count());
    }

    public function test_a_signed_line_with_a_blanked_field_or_an_unknown_type_is_recorded_rejected(): void
    {
        $ops = [$this->served(), $this->served(), $this->served()];
        $signed = $this->sign($ops);
        $signed[0]['signed'] = str_replace('"staff_id":' . $this->cashier->id, '"staff_id":0', $signed[0]['signed'], $count);
        $this->assertSame(1, $count);
        // A type this server does not know, signed by the till itself (a newer app): its signature is good
        $unknown = $this->served();
        $unknown['type'] = 'order.teleport';

        $response = $this->sync([...$signed, ...$this->sign([$unknown])])->assertOk()->assertJsonCount(4, 'data.results');

        $response->assertJsonPath('data.results.0.status', 'rejected')->assertJsonPath('data.results.0.reason', 'invalid_payload')->assertJsonPath('data.results.0.sig_status', 'bad')
            ->assertJsonPath('data.results.1.sig_status', 'ok')
            ->assertJsonPath('data.results.3.status', 'rejected')->assertJsonPath('data.results.3.reason', 'type_unknown')->assertJsonPath('data.results.3.sig_status', 'ok');
        // ...and the chain goes on after it
        $this->sync($this->sign([$this->served()]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'ok');
    }

    public function test_signed_text_that_names_no_operation_is_answered_without_a_row(): void
    {
        $ops = [$this->served(), $this->served(), $this->served()];
        $signed = $this->sign($ops);

        foreach (['"x"', 'not json', '[1,2]', '{"op_id":"nope","seq":2}', '{"op_id":"' . Str::uuid() . '","seq":1,"type":"order.served"}'] as $k => $text) {
            $broken = $signed;
            $broken[1] = ['signed' => $text] + $signed[1];
            $response = $this->sync($k === 0 ? $broken : [$broken[1]])->assertOk();

            $response->assertJsonCount(1, 'data.unreadable')
                ->assertJsonPath('data.unreadable.0.index', $k === 0 ? 1 : 0)
                ->assertJsonPath('data.unreadable.0.reason', 'invalid_payload');
            $this->assertNotSame('', (string) $response->json('data.unreadable.0.message'));
        }

        // The first batch recorded the two good lines around the unreadable one; nothing else was ever written
        $this->assertSame(2, PosSyncOperation::count());
        $this->assertSame([1, 3], PosSyncOperation::orderBy('seq')->pluck('seq')->all());
    }

    public function test_a_signed_line_whose_number_was_edited_to_one_of_the_batch_is_not_a_422(): void
    {
        $ops = [$this->served(), $this->served(), $this->served()];
        $signed = $this->sign($ops);
        $signed[1]['signed'] = str_replace('"seq":2,', '"seq":3,', $signed[1]['signed'], $count);
        $this->assertSame(1, $count);

        // As if the two had come one batch after the other: the number is taken, the till is told and renumbers
        // (pos_sync adoptServerSeq); a 422 would stop its queue for good
        $this->sync($signed)->assertStatus(409)->assertJsonPath('data.reason', 'seq_reused')->assertJsonPath('data.seq', 3);
        $this->assertSame([1, 3], PosSyncOperation::orderBy('seq')->pluck('seq')->all());

        // The same op_id twice: the second is the first one again
        $twice = $this->sign([$this->served()]);
        $this->sync([$twice[0], $twice[0]])->assertOk()->assertJsonCount(2, 'data.results')->assertJsonPath('data.results.1.duplicate', true);
    }

    public function test_a_malformed_unsigned_operation_is_still_a_422(): void
    {
        $op = $this->served();
        unset($op['staff_id']);
        $this->sync([$op])->assertStatus(422);
        $this->assertSame(0, PosSyncOperation::count());
    }

    // --- 4. The key order comes before everything else ------------------------------------------------------------

    public function test_the_previous_key_cannot_sign_ok_operations_after_the_new_keys_first_one(): void
    {
        $old = $this->key;
        $this->sync($this->sign([$this->served()]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'ok');

        $new = $this->withBearer($this->till['token'])->postJson('/api/v1/terminal/sync-key')->assertOk()->json('data');
        $this->chainPrev = null;
        $underNew = $this->signed([$this->served()], $new, $this->uuid);
        $this->sync($underNew)->assertOk()->assertJsonPath('data.results.0.sig_status', 'ok');

        // The old key, chained to the newest operation's mac
        $this->chainPrev = $underNew[0]['mac'];
        $this->sync($this->signed([$this->served()], $old, $this->uuid))->assertOk()->assertJsonPath('data.results.0.sig_status', 'chain_gap');
        // ...and the head stays what a verified operation left
        $this->chainPrev = null;
    }

    // --- 5. A badly signed line never hides a removal -------------------------------------------------------------

    public function test_a_bad_line_out_of_its_place_does_not_vouch_for_what_was_removed_before_it(): void
    {
        $ops = [$this->served(), $this->served(), $this->served(), $this->served()];
        $signed = $this->sign($ops);
        // Lines 2 and 3 removed; a line of the attacker's own under number 3 carrying line 3's mac
        $dummy = ['op_id' => (string) Str::uuid(), 'seq' => 3] + $ops[2];
        $forged = [
            $signed[0],
            ['signed' => json_encode($dummy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)] + $signed[2],
            $signed[3],
        ];

        $this->sync($forged)->assertOk();

        // The dummy is `bad`; what was removed before it shows on the next signed operation, and the owner hears
        $this->assertSame(['ok', 'bad', 'chain_gap'], $this->statuses());
        $this->assertCount(1, $this->ownerAlerts());
    }

    public function test_one_line_changed_in_its_place_is_one_bad_and_the_next_follows_either_way(): void
    {
        $ops = [$this->served(), $this->served(), $this->served()];
        $signed = $this->sign($ops);
        $signed[1]['signed'] = str_replace($ops[1]['payload']['order'], (string) Str::uuid(), $signed[1]['signed']);
        $this->sync($signed)->assertOk();
        $this->assertSame(['ok', 'bad', 'ok'], $this->statuses());
        // The head is the last verified operation, never the mac a bad line claims
        $this->assertSame($this->key['kid'] . ':' . $signed[2]['mac'], Device::findOrFail($this->till['device']->id)->sync_chain);

        // A bad line last in its batch: the next batch may follow the mac it claimed...
        $more = $this->sign([$this->served(), $this->served()]);
        $bad = $more[0];
        $bad['signed'] = str_replace('order.served', 'order.send', $bad['signed']);
        $this->sync([$bad])->assertOk()->assertJsonPath('data.results.0.sig_status', 'bad');
        $this->assertSame($this->key['kid'] . ':' . $signed[2]['mac'], Device::findOrFail($this->till['device']->id)->sync_chain);
        $this->sync([$more[1]])->assertOk()->assertJsonPath('data.results.0.sig_status', 'ok');

        // ...or (a till that skipped its own damaged line) the verified operation before it
        $damaged = $this->sign([$this->served()]);
        $damaged[0]['mac'] = str_repeat('0', 64);
        $this->sync($damaged)->assertOk()->assertJsonPath('data.results.0.sig_status', 'bad');
        $this->chainPrev = $more[1]['mac'];
        $this->sync($this->sign([$this->served()]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'ok');
        $this->assertSame([], $this->ownerAlerts());
    }

    // --- 7. Back from a build that does not sign -----------------------------------------------------------------

    public function test_a_till_rolled_back_and_updated_again_starts_its_chain_without_a_false_alarm(): void
    {
        $this->sync($this->sign([$this->served()]))->assertOk();   // 1: signed
        $this->sync([$this->served()])->assertOk();                 // 2: the old build, unsigned
        $this->chainPrev = null;                                     // the updated till no longer holds its chain
        $this->sync($this->sign([$this->served(), $this->served()]))->assertOk();

        $this->assertSame(['ok', 'missing', 'ok', 'ok'], $this->statuses());
        $this->assertSame([], $this->ownerAlerts());

        // Not straight after an unsigned operation: a chain that starts again is a gap, as before
        $this->chainPrev = null;
        $this->sync($this->sign([$this->served()]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'chain_gap');
    }

    public function test_an_unsigned_line_put_in_place_of_a_removed_one_does_not_hide_it(): void
    {
        $ops = [$this->served(), $this->served(), $this->served()];
        $signed = $this->sign($ops);
        $plain = ['op_id' => (string) Str::uuid()] + $ops[1];

        $this->sync([$signed[0], $plain, $signed[2]])->assertOk();

        $this->assertSame(['ok', 'missing', 'chain_gap'], $this->statuses());
    }

    // --- 8. The owner's message is throttled by when the owner was told -------------------------------------------

    public function test_a_till_that_keeps_losing_lines_is_reported_to_the_owner_once_an_hour_not_once_for_ever(): void
    {
        $this->sync($this->sign([$this->served()]))->assertOk();
        for ($i = 0; $i < 6; $i++) {
            $this->seq += 2;
            $this->sync($this->sign([$this->served()]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'chain_gap');
            $this->travel(50)->minutes();
        }

        // Gaps at 0, 50, 100, 150, 200 and 250 minutes: told at 0, 100 and 200
        $this->assertCount(3, $this->ownerAlerts());
        // ...kept where a deploy or a cache restart does not lose it: the audit log, numbers only
        $told = AuditLog::where('action', 'pos.sync_chain_gap_alerted')->orderBy('id')->get();
        $this->assertCount(3, $told);
        $this->assertSame([$this->till['device']->id, null, 1], [$told[0]->device_id, $told[0]->user_id, $told[0]->payload_json['operations']]);
    }

    // --- 9. The chain picked up from the rows -------------------------------------------------------------------

    public function test_a_batch_killed_before_the_device_row_was_saved_is_picked_up_past_an_unsigned_last_row(): void
    {
        $this->sync($this->sign([$this->served()]))->assertOk();
        $head = Device::findOrFail($this->till['device']->id)->sync_chain;
        // Batch 2: number 2 signed, number 3 unsigned, then the process dies before the device row is saved
        $this->sync([...$this->sign([$this->served()]), $this->served()])->assertOk();
        Device::whereKey($this->till['device']->id)->update(['sync_chain' => $head]);

        $this->sync($this->sign([$this->served()]))->assertOk();

        $this->assertSame(['ok', 'ok', 'missing', 'ok'], $this->statuses());
    }

    // --- 10. Loose clients -----------------------------------------------------------------------------------------

    public function test_a_key_number_sent_as_text_is_the_same_number(): void
    {
        $signed = $this->sign([$this->served()]);
        $signed[0]['kid'] = (string) $signed[0]['kid'];

        $this->sync($signed)->assertOk()->assertJsonPath('data.results.0.sig_status', 'ok');
    }

    public function test_a_signed_operation_in_a_body_that_is_not_json_is_recorded_bad_never_refused(): void
    {
        $signed = $this->sign([$this->served()]);

        // Form-encoded: the server no longer has the bytes the till signed (the request middleware rewrites strings)
        $this->withBearer($this->till['token'])->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/pos/sync', ['sent_at' => now()->toJSON(), 'operations' => $signed])
            ->assertOk()
            ->assertJsonPath('data.results.0.sig_status', 'bad');
    }

    public function test_a_till_that_signed_and_then_sends_unsigned_operations_is_audited_once_a_day(): void
    {
        $this->sync($this->sign([$this->served()]))->assertOk();
        $this->assertSame(0, AuditLog::where('action', 'device.sync_signature_missing')->count());

        $this->sync([$this->served(), $this->served()])->assertOk();
        $this->sync([$this->served()])->assertOk();

        $audit = AuditLog::where('action', 'device.sync_signature_missing')->sole();
        $this->assertSame([$this->till['device']->id, null], [$audit->device_id, $audit->user_id]);
        $this->assertSame($this->key['kid'], $audit->payload_json['kid']);
        $this->assertStringNotContainsString($this->key['key'], (string) json_encode($audit->getAttributes()));

        $this->travel(25)->hours();
        $this->sync([$this->served()])->assertOk();
        $this->assertSame(2, AuditLog::where('action', 'device.sync_signature_missing')->count());
    }

    public function test_a_till_that_never_signed_under_its_key_is_not_audited_for_unsigned_operations(): void
    {
        $this->sync([$this->served()])->assertOk();
        $this->sync([$this->served()])->assertOk();
        $this->assertSame(0, AuditLog::where('action', 'device.sync_signature_missing')->count());
    }

    // --- 11. The kill switch --------------------------------------------------------------------------------------

    public function test_the_kill_switch_turns_signing_off_in_bootstrap_and_enforce_with_it(): void
    {
        $this->assertTrue(config('pos.sync_signing'));
        config(['pos.sync_signing' => false]);

        $offline = $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.offline');
        $this->assertFalse($offline['signing']);
        $this->assertNull($offline['signing_kid']);

        // Tills stop signing: a store in enforce must not have every sale flagged for it
        $this->enforce();
        $this->sync($this->walkInSale()['ops'])->assertOk()->assertJsonPath('data.results.0.status', 'applied')->assertJsonPath('data.results.2.status', 'applied');
        // ...and what does arrive signed is still checked and recorded
        $this->sync($this->sign([$this->served()]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'ok');
        $this->sync([$this->served()])->assertOk();
        $this->assertSame(0, AuditLog::where('action', 'device.sync_signature_missing')->count());
    }
}
