<?php

namespace Tests\Feature\PosQr;

use App\Domain\Pos\QrPayments\UzQr\UzQrOperatorProtocol as Api;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosQrSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Review 11: a UzQR the operator reports PAID must end either as a payment of the bill or as paid_unmatched
 * (reconciliation), and a UzQR the customer may still pay is asked until the operator settles it. The first five
 * tests are the review's proofs (each ended with the money taken at the operator and neither recorded on a96d70b8).
 */
class UzQrLatePaymentsTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;
    use UzQrOperatorFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->openShift();
        $this->setUpOperator();
    }

    private function created(): PosQrPayment
    {
        $this->setTillKey()->assertOk();
        $this->fakeOperator([Api::PATH_CREATE => $this->createdAnswer(), Api::PATH_STATUS => $this->statusAnswer('pending'), Api::PATH_CANCEL => ['success' => true]]);
        [, $bill] = $this->billed();

        return $this->qrModel($this->qr($bill, 'uzqr')->assertCreated()->json('data.qr.uuid'));
    }

    private function assertPaidMoneyIsSeen(PosQrPayment $qr): void
    {
        $qr->refresh();
        $recorded = Payment::where('provider', 'qr')->where('status', 'paid')->count() === 1;
        $flagged = $qr->cancel_reason === 'paid_unmatched';
        $this->assertTrue($recorded || $flagged, "operator says PAID, but the QR is {$qr->status->value}/{$qr->cancel_reason}: no payment, no paid_unmatched");
    }

    /**
     * Review finding (HIGH): the till's poll picks the QR as "late" (lateUzQr) BEFORE asking the operator; the status call (up to 23 s)
     * ends after the hold window closed; confirm() re-checks lateUzQr, gets false and returns 'refused' with nothing
     * recorded. The minute job no longer selects it (cancelled_at outside the same window): lost.
     */
    public function test_a_paid_answer_that_arrives_after_the_old_hold_window_is_still_taken(): void
    {
        $qr = $this->created();
        $this->travel(6)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful(); // pending at the operator: expired here
        $this->assertSame('expired', $qr->refresh()->status->value);

        // 9 min 50 s after the expiry the till polls; the operator answers "paid" 20 s later (its timeout is 20 s)
        $this->travel(9 * 60 + 50)->seconds();
        $this->fakeOperator([Api::PATH_STATUS => function (Request $request) {
            $this->travel(20)->seconds();

            return Http::response($this->paidAnswer());
        }]);
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk();

        // The minute job runs afterwards and the operator still says paid
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();

        $this->assertPaidMoneyIsSeen($qr);
    }

    /**
     * Review finding (HIGH): an operator outage (or slow answers) lasting longer than hold_minutes after the expiry: every ask in the
     * window gets no answer, then the QR is never asked again. The customer was charged (the status shows paid once
     * the operator is back); Rizqim has an `expired` QR and nobody is told.
     */
    public function test_a_paid_qr_is_seen_after_an_operator_outage_longer_than_the_hold_window(): void
    {
        $qr = $this->created();
        $this->fakeOperator([Api::PATH_STATUS => Http::response(['message' => 'down'], 503)]);
        $this->travel(6)->minutes();
        for ($minute = 0; $minute < 11; $minute++) {
            $this->artisan('pos:expire-qr-payments')->assertSuccessful();
            $this->travel(1)->minutes();
        }
        $this->assertSame('expired', $qr->refresh()->status->value);

        // The operator is back: the customer's payment went through during the outage
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk();

        $this->assertPaidMoneyIsSeen($qr);
    }

    /**
     * Review finding (HIGH): the cashier cancels a 30-minute QR after one minute; the operator's cancel fails (only logged: announce()
     * is best effort). The QR stays payable at the operator (lifetime sent at create = 30 min), but the recheck
     * window is 10 minutes from OUR cancel. The customer pays at minute 15: never seen.
     */
    public function test_a_payment_after_a_cancel_the_operator_never_got_is_seen(): void
    {
        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'providers' => ['click', 'payme', 'uzqr'], 'expiry_minutes' => 30])->assertOk();
        $qr = $this->created();
        $this->assertSame(30 * 60, $this->sentTo(Api::PATH_CREATE)['lifetime']);

        $this->travel(1)->minutes();
        $this->fakeOperator([Api::PATH_CANCEL => Http::response(['message' => 'down'], 503)]);
        $this->pos('DELETE', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'cancelled');

        // Minutes 1..14: the customer has not paid yet
        for ($minute = 0; $minute < 13; $minute++) {
            $this->travel(1)->minutes();
            $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        }
        // Minute 15: the customer pays the (still live) operator invoice
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->travel(1)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();

        $this->assertPaidMoneyIsSeen($qr);
    }

    /**
     * Review finding (MEDIUM): recheckLateUzQr takes the first 50 ended UzQRs by id every minute with no progress marker; one that
     * answers pending/cancelled stays in the set for the whole window. With more than 50 UzQRs ended within
     * hold_minutes (platform-wide: about 5 a minute), the newer ones are never asked.
     */
    public function test_the_recheck_never_starves_past_fifty_ended_qrs(): void
    {
        $qr = $this->created();
        $this->travel(6)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $qr->refresh();
        for ($i = 0; $i < 50; $i++) {
            $copy = $qr->replicate();
            $copy->forceFill(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'gateway_reference' => "inv-old-{$i}"])->save();
        }
        // The last one (the target) ended at the same time but has the highest id
        $target = $qr->replicate();
        $target->forceFill(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'gateway_reference' => 'inv-target'])->save();

        $this->fakeOperator([Api::PATH_STATUS => fn (Request $request) => Http::response($request['invoice_id'] === 'inv-target'
            ? $this->paidAnswer(14300000, ['invoice_id' => 'inv-target'])
            : $this->statusAnswer('pending'))]);
        for ($minute = 0; $minute < 11; $minute++) {
            $this->artisan('pos:expire-qr-payments')->assertSuccessful();
            $this->travel(1)->minutes();
        }

        $asked = Http::recorded(fn (Request $request) => $request->url() === $this->base() . Api::PATH_STATUS && $request['invoice_id'] === 'inv-target')->count();
        $this->assertGreaterThan(0, $asked, 'the paid target QR was never asked: lost');
    }

    /**
     * Review finding (MEDIUM): money the operator took but the bill did not (cashier cancelled while the customer paid, amount
     * mismatch, shift closed) is only written to the audit log and a server log line; the owner is never alerted
     * (PosOwnerAlerts, as the bank-terminal difference is) and no report lists it.
     */
    public function test_paid_unmatched_money_reaches_the_owner(): void
    {
        $alerts = [];
        $this->mock(\App\Domain\Pos\Accounts\PosOwnerAlerts::class, function ($mock) use (&$alerts) {
            $mock->shouldReceive('send')->andReturnUsing(function (int $store, string $text) use (&$alerts) {
                $alerts[] = $text;
            });
        });
        $qr = $this->created();
        $this->pos('DELETE', "/qr/{$qr->uuid}")->assertOk();
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame('paid_unmatched', $qr->refresh()->cancel_reason);

        $this->assertNotEmpty($alerts, 'the owner was never told about 143 000 so\'m taken at UzQR and not on any bill');
    }

    /**
     * @return \ArrayObject<int, string> the owner alerts sent from now on
     */
    private function alerts(): \ArrayObject
    {
        /** @var \ArrayObject<int, string> $alerts */
        $alerts = new \ArrayObject([]);
        $this->mock(\App\Domain\Pos\Accounts\PosOwnerAlerts::class, function ($mock) use ($alerts) {
            $mock->shouldReceive('send')->andReturnUsing(function (int $store, string $text) use ($alerts) {
                $alerts[] = $text;
            });
        });

        return $alerts;
    }

    public function test_the_owner_lists_the_unmatched_qr_money_of_their_store_only(): void
    {
        $qr = $this->created();
        $this->pos('DELETE', "/qr/{$qr->uuid}")->assertOk();
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();

        $this->owner('GET', '/owner/pos/qr-payments/unmatched')->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.uuid', $qr->uuid)
            ->assertJsonPath('data.items.0.provider', 'uzqr')
            ->assertJsonPath('data.items.0.amount', '143000.00')
            ->assertJsonPath('data.items.0.transaction_id', 'pay-777001')
            ->assertJsonPath('data.items.0.reason', 'paid_after_cashier')
            ->assertJsonPath('data.total', '143000.00');

        $store2 = $this->makeStore('Other');
        $owner2 = $this->makeStaff(\App\Enums\UserRole::OWNER, null, $store2);
        $this->owner('GET', '/owner/pos/qr-payments/unmatched', [], $owner2)->assertOk()->assertJsonCount(0, 'data.items');
    }

    /**
     * LOW: the operator made an invoice, then the bill changed before the request was saved (409 bill_changed): the
     * invoice stayed payable at the operator with no record here. It is cancelled there and watched like any other.
     */
    public function test_an_invoice_made_for_a_bill_that_changed_meanwhile_is_cancelled_and_watched(): void
    {
        $this->setTillKey()->assertOk();
        [, $bill] = $this->billed();
        $this->fakeOperator([
            Api::PATH_CREATE => function () use ($bill) {
                \App\Models\OrderBill::where('uuid', $bill)->update(['total' => '150000']);

                return Http::response($this->createdAnswer('inv-orphan'));
            },
            Api::PATH_CANCEL => ['success' => true],
            Api::PATH_STATUS => $this->paidAnswer(14300000, ['invoice_id' => 'inv-orphan']),
        ]);

        $this->qr($bill, 'uzqr')->assertStatus(409)->assertJsonPath('data.reason', 'bill_changed');

        $this->assertSame('inv-orphan', $this->sentTo(Api::PATH_CANCEL)['invoice_id']);
        $kept = PosQrPayment::where('gateway_reference', 'inv-orphan')->sole();
        $this->assertSame(['cancelled', 'bill_changed'], [$kept->status->value, $kept->cancel_reason]);

        // The customer had paid it anyway: kept for reconciliation
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame('paid_unmatched', $kept->refresh()->cancel_reason);
    }

    /**
     * H2/M1: one run asks at most `per_run` ended QRs, the most overdue first; the next run goes on with the rest.
     */
    public function test_one_run_asks_a_bounded_number_and_the_next_run_goes_on_with_the_rest(): void
    {
        config(['qr_payments.uzqr.recheck.per_run' => 10]);
        $qr = $this->created();
        $this->travel(6)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $qr->refresh();
        for ($i = 0; $i < 24; $i++) {
            $qr->replicate()->forceFill(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'gateway_reference' => "inv-many-{$i}"])->save();
        }
        $before = $this->countSent(Api::PATH_STATUS);

        $this->travel(1)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame(10, $this->countSent(Api::PATH_STATUS) - $before);

        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $asked = Http::recorded(fn (Request $request) => $request->url() === $this->base() . Api::PATH_STATUS)
            ->map(fn ($pair) => $pair[0]['invoice_id'])->unique();
        $this->assertCount(25, $asked, 'every ended QR was asked within three runs');
    }

    /**
     * H2: an operator that does not answer for long: asked with a backoff (never given up), the owner told once, and
     * the payment taken as soon as it answers "paid".
     */
    public function test_a_long_silence_backs_off_alerts_the_owner_once_and_the_payment_is_still_taken(): void
    {
        $alerts = $this->alerts();
        $qr = $this->created();
        $this->fakeOperator([Api::PATH_STATUS => Http::response(['message' => 'down'], 503)]);
        $this->travel(6)->minutes();
        $runs = 0;
        for ($minute = 0; $minute < 120; $minute++) {
            $this->artisan('pos:expire-qr-payments')->assertSuccessful();
            $runs++;
            $this->travel(1)->minutes();
        }
        $asked = $this->countSent(Api::PATH_STATUS);
        $this->assertGreaterThan(5, $asked);
        $this->assertLessThan($runs / 2, $asked, 'no backoff');
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('UzQR', $alerts[0]);
        $this->assertNotNull($qr->refresh()->next_check_at);

        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        for ($minute = 0; $minute < 31; $minute++) {
            $this->artisan('pos:expire-qr-payments')->assertSuccessful();
            $this->travel(1)->minutes();
        }
        $this->assertSame('paid', $qr->refresh()->status->value);
        $this->assertSame(1, Payment::where('provider', 'qr')->where('status', 'paid')->count());
    }

    /**
     * H3: a cancel the operator did not acknowledge is sent again; once acknowledged and past the QR's lifetime the QR
     * is settled: no more asks, and the key copy it kept is wiped.
     */
    public function test_an_unacknowledged_cancel_is_sent_again_and_a_settled_qr_is_no_longer_asked(): void
    {
        $qr = $this->created();
        $this->fakeOperator([Api::PATH_CANCEL => Http::response(['message' => 'down'], 503)]);
        $this->pos('DELETE', "/qr/{$qr->uuid}")->assertOk();
        $this->assertNull($qr->refresh()->operator_cancelled_at);

        $this->travel(1)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame(2, $this->countSent(Api::PATH_CANCEL));
        $this->fakeOperator([Api::PATH_CANCEL => ['success' => true]]);
        $this->travel(1)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame(3, $this->countSent(Api::PATH_CANCEL));
        $this->assertNotNull($qr->refresh()->operator_cancelled_at);

        for ($minute = 0; $minute < 20; $minute++) {
            $this->travel(1)->minutes();
            $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        }
        $qr->refresh();
        $this->assertNull($qr->next_check_at);
        $this->assertNull($qr->uzqr_api_key);
        $this->assertSame(3, $this->countSent(Api::PATH_CANCEL));
        $asked = $this->countSent(Api::PATH_STATUS);
        $this->travel(10)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame($asked, $this->countSent(Api::PATH_STATUS));
    }
}
