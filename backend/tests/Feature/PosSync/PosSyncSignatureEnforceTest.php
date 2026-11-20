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
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4, the modes table; test plan 7.1-7): the store's "enforce" switch
 * (`stores.pos_sync_enforce_signatures`, SignaturePolicy). With it on, an operation that comes unsigned or badly
 * signed is still applied and becomes a conflict `signature_invalid` for the manager (a sale is never dropped; only a
 * concession — a discount, a void — is refused); a payment is recorded and flagged `payment_unverified`; a `chain_gap`
 * is applied and becomes a conflict. The batch is still never refused. The owner hears about a `chain_gap` in both
 * modes. (The review after S3: PosSyncSignatureReviewTest.)
 */
class PosSyncSignatureEnforceTest extends TestCase
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

    public function test_enforce_flags_an_unsigned_operation_that_is_not_money_and_takes_the_signed_ones(): void
    {
        $this->enforce();
        $first = $this->sync($this->sign($this->walkInSale()['ops']))->assertOk();
        foreach (range(0, 3) as $k) {
            $first->assertJsonPath("data.results.{$k}.status", 'applied')->assertJsonPath("data.results.{$k}.sig_status", 'ok');
        }

        $order = (string) Str::uuid();
        $open = $this->op('order.open', ['order' => $order, 'local_number' => 'K1-002', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->tea, '1')], 'send' => true, 'menu_taken_at' => now()->subHour()->toJSON()]);

        $this->sync([$open])->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'signature_invalid')
            ->assertJsonPath('data.results.0.sig_status', 'missing');

        // Applied: the sale is never dropped
        $this->assertTrue(Order::where('external_id', $order)->exists());
        // The manager sees it in "Sinxronlash xatolari", with what the signature was worth
        $error = collect($this->tillAs('GET', '/pos/sync/errors')->assertOk()->json('data.errors'))->firstWhere('op_id', $open['op_id']);
        $this->assertSame(['conflict', 'signature_invalid', 'missing'], [$error['status'], $error['reason'], $error['sig_status']]);
        $this->assertNotNull($error['applied_at']);
    }

    public function test_enforce_applies_and_flags_a_line_edited_by_hand_that_is_not_money(): void
    {
        $this->enforce();
        $sale = $this->walkInSale();
        $envelopes = $this->sign($sale['ops']);
        // A second tea added to the opened order in the journal, without the key
        $envelopes[0]['signed'] = str_replace('"local_number":"K1-001"', '"local_number":"K1-009"', $envelopes[0]['signed'], $count);
        $this->assertSame(1, $count);

        $response = $this->sync($envelopes)->assertOk()->assertJsonCount(4, 'data.results');

        $response->assertJsonPath('data.results.0.status', 'conflict')->assertJsonPath('data.results.0.reason', 'signature_invalid')->assertJsonPath('data.results.0.sig_status', 'bad');
        // Applied as it arrived (the edited number with it), for the manager to compare; the rest of the sale follows
        $this->assertSame('K1-009', Order::where('external_id', $sale['order'])->firstOrFail()->local_number);
        $response->assertJsonPath('data.results.2.status', 'applied')->assertJsonPath('data.results.3.status', 'applied');
    }

    public function test_in_enforce_an_unsigned_discount_is_refused_like_any_other_discount(): void
    {
        $this->enforce();
        $sale = $this->walkInSale();
        $this->sync($this->sign([$sale['ops'][0]]))->assertOk()->assertJsonPath('data.results.0.status', 'applied');

        $discount = $this->op('order.discount', ['order' => $sale['order'], 'kind' => 'percent', 'value' => '10', 'reason' => 'Doimiy mijoz', 'expected_discount' => '4100']);
        $this->sync([$discount])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'discount_not_allowed')
            ->assertJsonPath('data.results.0.refs.cause', 'signature_invalid')
            ->assertJsonPath('data.results.0.sig_status', 'missing');

        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertNull($order->getAttribute('discount_kind'));
        $this->assertSame($order->id, PosSyncOperation::where('op_id', $discount['op_id'])->firstOrFail()->order_id);
    }

    public function test_in_enforce_a_badly_signed_payment_is_recorded_on_its_bill_and_flagged_payment_unverified(): void
    {
        $this->enforce();
        $sale = $this->walkInSale();
        $envelopes = $this->sign($sale['ops']);
        $envelopes[2]['signed'] = str_replace('"amount":"41000"', '"amount":"40000"', $envelopes[2]['signed'], $count);
        $this->assertSame(1, $count);

        $response = $this->sync($envelopes)->assertOk();

        $response->assertJsonPath('data.results.2.status', 'conflict')
            ->assertJsonPath('data.results.2.reason', 'payment_unverified')
            ->assertJsonPath('data.results.2.sig_status', 'bad');
        $this->assertStringContainsString('imzo', (string) $response->json('data.results.2.message'));
        // Money is never dropped: the payment is on its bill with the amount that arrived
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('40000.00', (string) $payment->amount);
        $this->assertNotNull($payment->bill_id);
        $this->assertSame($this->shift->id, $payment->shift_id);
        $flags = array_column((array) $order->fresh()->sync_flags, 'flag');
        $this->assertContains('payment_unverified', $flags);
        $this->assertContains('underpaid', $flags);

        // Audited with the device as the actor, what the signature was worth, and nothing key-shaped
        $audit = AuditLog::where('action', 'pos.offline_payment_unverified')->sole();
        $this->assertNull($audit->user_id);
        $this->assertSame($this->till['device']->id, $audit->device_id);
        $this->assertSame('bad', $audit->payload_json['sig_status']);
        $this->assertSame('40000.00', $audit->payload_json['amount']);
        $this->assertStringNotContainsString($this->key['key'], (string) json_encode($audit->getAttributes()));

        // The same batch again changes nothing
        $this->sync($envelopes)->assertOk()->assertJsonPath('data.results.2.duplicate', true)->assertJsonPath('data.results.2.reason', 'payment_unverified');
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_in_enforce_an_unsigned_payment_still_pays_the_bill_and_the_order_closes(): void
    {
        $this->enforce();
        $sale = $this->walkInSale();
        [$open, $bill, $pay, $close] = $sale['ops'];
        [$signedOpen, $signedBill, $signedClose] = $this->sign([$open, $bill, $close]);

        $response = $this->sync([$signedOpen, $signedBill, $pay, $signedClose])->assertOk();

        $response->assertJsonPath('data.results.2.status', 'conflict')
            ->assertJsonPath('data.results.2.reason', 'payment_unverified')
            ->assertJsonPath('data.results.2.sig_status', 'missing')
            ->assertJsonPath('data.results.3.status', 'applied')
            ->assertJsonPath('data.results.3.sig_status', 'ok');
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertSame('41000.00', (string) Payment::where('order_id', $order->id)->sole()->amount);
        // Closing is never blocked: the bill is paid, the order closed, nothing rejected keeps the shift open
        $this->assertNotNull($order->closed_at);
        $this->assertSame(0, PosSyncOperation::where('device_id', $this->till['device']->id)->where('status', 'rejected')->count());
    }

    public function test_in_enforce_a_badly_signed_payment_with_no_open_bill_is_recorded_unallocated(): void
    {
        $this->enforce();
        $sale = $this->walkInSale();
        $this->sync($this->sign($sale['ops']))->assertOk()->assertJsonPath('data.results.2.status', 'applied');

        // The bill is paid already: a second payment for it, its line changed by hand
        $again = $this->sign([$this->op('bill.pay', ['order' => $sale['order'], 'bill' => $sale['bill'], 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '41000']]])]);
        $again[0]['signed'] = str_replace('"amount":"41000"', '"amount":"7000"', $again[0]['signed'], $count);
        $this->assertSame(1, $count);

        $this->sync($again)->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'payment_unverified');

        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $loose = Payment::where('order_id', $order->id)->whereNull('bill_id')->sole();
        $this->assertSame('7000.00', (string) $loose->amount);
        $this->assertSame(1, AuditLog::where('action', 'pos.offline_payment_unverified')->count());
    }

    public function test_in_enforce_a_till_that_does_not_sign_at_all_never_gets_a_refused_batch(): void
    {
        $this->enforce();
        $sale = $this->walkInSale();

        $response = $this->sync($sale['ops'])->assertOk()->assertJsonCount(4, 'data.results');

        $response->assertJsonPath('data.results.0.reason', 'signature_invalid')
            ->assertJsonPath('data.results.1.reason', 'signature_invalid')
            ->assertJsonPath('data.results.3.reason', 'signature_invalid');
        // Every one of them applied: the order is open, so the money lands on its bill (never `order_not_found`)
        $response->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.2.status', 'conflict')->assertJsonPath('data.results.2.reason', 'payment_unverified');
        $this->assertNotNull(Payment::where('order_id', Order::where('external_id', $sale['order'])->firstOrFail()->id)->sole()->bill_id);
        $this->assertSame(0, PosSyncOperation::where('status', 'rejected')->count());
        $this->assertSame(4, PosSyncOperation::where('device_id', $this->till['device']->id)->where('sig_status', 'missing')->count());
        $this->assertSame(4, (int) Device::findOrFail($this->till['device']->id)->sync_last_seq);
    }

    public function test_in_enforce_a_chain_gap_is_applied_and_becomes_a_conflict_for_the_manager(): void
    {
        $this->enforce();
        $this->sync($this->sign($this->walkInSale()['ops']))->assertOk();

        $sale = $this->walkInSale('K1-002');
        $served = $this->op('order.served', ['order' => $sale['order']]);
        $envelopes = $this->sign([$sale['ops'][0], $served, ...array_slice($sale['ops'], 1)]);
        unset($envelopes[1]);

        $response = $this->sync(array_values($envelopes))->assertOk();

        // seq 5 the order, 6 removed, 7 the bill (the gap), 8 the payment, 9 the close
        $response->assertJsonPath('data.results.1.sig_status', 'chain_gap')
            ->assertJsonPath('data.results.1.status', 'conflict')
            ->assertJsonPath('data.results.1.reason', 'chain_gap')
            ->assertJsonPath('data.results.2.status', 'applied');
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertSame('41000.00', (string) Payment::where('order_id', $order->id)->sole()->amount);
        $this->assertNotNull($order->closed_at);
        $this->assertNotNull(PosSyncOperation::where('reason', 'chain_gap')->sole()->applied_at);

        $alerts = $this->ownerAlerts();
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString("kassa navbatidan amal yo'qolgan", $alerts[0]);
        $this->assertStringContainsString((string) $this->till['device']->name, $alerts[0]);
    }

    public function test_in_observe_mode_a_chain_gap_stays_applied_and_the_owner_hears_once(): void
    {
        $this->sync($this->sign([$this->op('order.served', ['order' => (string) Str::uuid()])]))->assertOk();
        $this->assertSame([], $this->ownerAlerts());

        $this->seq += 2;
        $this->sync($this->sign([$this->op('order.served', ['order' => (string) Str::uuid()])]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'chain_gap');
        $this->assertNull(PosSyncOperation::where('reason', 'chain_gap')->first());
        $this->assertCount(1, $this->ownerAlerts());

        // The same batch sent again, and another gap within the hour: no second message
        $this->seq += 2;
        $this->sync($this->sign([$this->op('order.served', ['order' => (string) Str::uuid()])]))->assertOk()->assertJsonPath('data.results.0.sig_status', 'chain_gap');
        $this->assertCount(1, $this->ownerAlerts());
    }

    public function test_observe_mode_is_the_default_and_a_bad_or_missing_signature_changes_nothing_there(): void
    {
        $this->assertFalse((bool) $this->store->fresh()->pos_sync_enforce_signatures);

        $this->sync($this->walkInSale()['ops'])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.2.sig_status', 'missing');
        // A bad or missing signature is RIZQIM's to look at (the monitoring rule), not the owner's
        $this->assertSame([], $this->ownerAlerts());
        $this->assertSame(0, AuditLog::where('action', 'pos.offline_payment_unverified')->count());
    }

    public function test_bootstrap_tells_the_till_the_number_of_the_key_the_server_holds(): void
    {
        $this->assertSame($this->key['kid'], $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.offline.signing_kid'));

        Device::whereKey($this->till['device']->id)->update(['sync_key' => null, 'sync_key_id' => 0]);
        $offline = $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.offline');
        $this->assertArrayHasKey('signing_kid', $offline);
        $this->assertNull($offline['signing_kid']);
    }
}
