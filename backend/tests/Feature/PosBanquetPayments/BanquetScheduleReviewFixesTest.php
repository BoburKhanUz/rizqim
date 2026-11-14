<?php

namespace Tests\Feature\PosBanquetPayments;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssueResult;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Banquet\BanquetEventChanged;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Banquet\Payments\BanquetEntryType;
use App\Models\AuditLog;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\FiscalReceipt;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBanquetOps\PosBanquetOpsFixtures;
use Tests\TestCase;

/**
 * Review fixes of P6 wave 5 (H2): the schedule is closed once the final bill exists (C2, 409 `event_billed`), freeze
 * re-plans the schedule when it changes the total (C3), failed banquet receipts are sent again by
 * pos:banquet-retry-receipts (C5), duplicate row uuids in the schedule PUT (C6), one lock order shift → event (C9),
 * bank transfers panel-only with an owner alert, a duplicate reference refused and a mistaken transfer voided (C11/S4).
 */
class BanquetScheduleReviewFixesTest extends TestCase
{
    use PosBanquetOpsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBanquetOps();
        $this->openShift('100000');
    }

    private function paidAndStarted(BanquetEvent $event): BanquetEvent
    {
        [$deposit, $interim] = $this->rows($event)->all();
        $this->payRow($event, $deposit, ['tender' => 'cash'])->assertCreated();
        $this->payRow($event, $interim, ['tender' => 'card', 'card_type' => 'humo', 'transaction_id' => 'RRN-1'])->assertCreated();
        $this->pos('POST', "/banquets/{$event->uuid}/start")->assertOk();

        return $event->refresh();
    }

    private function transfer(BanquetEvent $event, BanquetPayment $row, array $data, ?string $key = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($this->owner, 'sanctum')->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())
            ->json('POST', "/api/v1/pos/banquets/{$event->uuid}/payments/{$row->uuid}/bank-transfer", $data);
    }

    private function voidTransfer(BanquetEvent $event, BanquetPaymentEntry $entry, ?string $reason = "Noto'g'ri tadbirga yozilgan"): TestResponse
    {
        $query = $reason !== null ? '?' . http_build_query(['reason' => $reason]) : '';

        return $this->panel($this->owner, 'DELETE', "/pos/banquets/{$event->uuid}/payments/transfers/{$entry->uuid}{$query}");
    }

    private function failingIssuer(): FiscalIssuer
    {
        $real = app(FiscalIssuer::class);
        $this->app->instance(FiscalIssuer::class, new class($real) implements FiscalIssuer
        {
            public function __construct(private FiscalIssuer $inner) {}

            public function issue(FiscalDocument $document): FiscalIssueResult
            {
                throw new \RuntimeException('provider down');
            }

            public function reissue(FiscalDocument $document): FiscalIssueResult
            {
                throw new \RuntimeException('provider down');
            }

            public function recordExternal(FiscalDocument $document, string $provider, array $providerData): FiscalIssueResult
            {
                return $this->inner->recordExternal($document, $provider, $providerData);
            }

            public function hasPending(string $subjectType, int $subjectId): bool
            {
                return $this->inner->hasPending($subjectType, $subjectId);
            }
        });

        return $real;
    }

    // --- C2 ---

    /** C2: after settle the schedule is closed: re-adding rows, a row payment and a transfer are refused (409 event_billed). */
    public function test_after_settle_schedule_edits_row_payments_and_transfers_are_refused(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $bill = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->assertSame('11040000.00', $bill['due_amount']);

        $this->pos('PUT', "/banquets/{$event->uuid}/payments", ['rows' => [['kind' => 'final', 'due_date' => '2027-01-20', 'amount' => '11040000']]], $this->managerToken)
            ->assertStatus(409)->assertJsonPath('data.reason', 'event_billed');
        $this->assertSame(0, BanquetPayment::where('banquet_event_id', $event->id)->where('status', 'due')->count());

        // A total change after the settle does not re-plan rows either (the remainder is on the final bill)
        event(new BanquetEventChanged($event->refresh(), 'updated', true));
        $this->assertSame(0, BanquetPayment::where('banquet_event_id', $event->id)->where('status', 'due')->count());

        // Even a stray unpaid row (data from before the fix) cannot take money twice
        $stray = BanquetPayment::create([
            'store_id' => $event->store_id, 'branch_id' => $event->branch_id, 'banquet_event_id' => $event->id,
            'kind' => BanquetPaymentKind::FINAL, 'due_date' => '2027-01-20', 'amount' => '11040000', 'status' => BanquetPaymentStatus::DUE, 'sort_order' => 99,
        ]);
        $this->payRow($event, $stray, ['tender' => 'cash'])->assertStatus(409)->assertJsonPath('data.reason', 'event_billed');
        $this->transfer($event, $stray, ['reference' => 'PP-77'])->assertStatus(409)->assertJsonPath('data.reason', 'event_billed');
        $this->assertSame(0, BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', 'transfer')->count());

        // The remainder is paid on the final bill; the schedule then says the event is billed
        $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => $bill['due_amount']]])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame(BanquetEventStatus::SETTLED, $event->refresh()->status);
        $this->pos('GET', "/banquets/{$event->uuid}/payments", [], $this->managerToken)->assertOk()->assertJsonPath('data.event.billed', true);
        $this->pos('PUT', "/banquets/{$event->uuid}/payments", ['rows' => []], $this->managerToken)
            ->assertStatus(409)->assertJsonPath('data.reason', 'event_billed');
    }

    public function test_before_settle_the_schedule_is_not_billed(): void
    {
        $event = $this->pricedEvent(200, 150);
        $this->pos('GET', "/banquets/{$event->uuid}/payments", [], $this->managerToken)->assertOk()->assertJsonPath('data.event.billed', false);
    }

    // --- C3 ---

    /** C3: freeze re-prices from the catalogue and the unpaid rows follow the new total. */
    public function test_freeze_that_changes_the_total_re_plans_the_schedule(): void
    {
        $created = $this->pos('POST', '/banquets', [
            'event_date' => '2027-02-10', 'slot' => 'evening', 'hall_ids' => [$this->hallA], 'customer_name' => 'Test',
            'guests_guaranteed' => 200, 'banquet_package_id' => $this->package->id,
        ], $this->managerToken)->assertCreated()->json('data');
        $event = BanquetEvent::where('uuid', $created['uuid'] ?? $created['event']['uuid'])->sole();
        $before = Money::of($event->total);
        // The owner raises the package price in the catalogue before the contract day
        $this->package->forceFill(['per_person_price' => '160000'])->save();

        $this->pos('POST', "/banquets/{$event->uuid}/freeze", [], $this->managerToken)->assertOk();
        $event->refresh();
        $this->assertTrue(Money::of($event->total)->isGreaterThan($before));
        $due = Money::sum(...$this->rows($event)->where('status', BanquetPaymentStatus::DUE)->pluck('amount')->all());
        $this->assertSame(Money::of($event->total)->toString(), $due->toString(), 'unpaid rows add up to the frozen total');
    }

    // --- C5 ---

    /** C5: an ADVANCE receipt that could not be issued is sent again by the hourly retry, once. */
    public function test_failed_advance_receipt_is_retried_once(): void
    {
        $event = $this->pricedEvent(200, 150);
        $real = $this->failingIssuer();
        $this->payRow($event, $this->rows($event)->first(), ['tender' => 'cash'])->assertCreated();
        $entry = BanquetPaymentEntry::where('banquet_event_id', $event->id)->sole();
        $this->assertSame('error', $entry->fiscal_status);

        // Still down: the retry leaves it visible as `error`
        $this->artisan('pos:banquet-retry-receipts')->assertSuccessful();
        $this->assertSame('error', $entry->refresh()->fiscal_status);

        $this->app->instance(FiscalIssuer::class, $real);
        $this->artisan('pos:banquet-retry-receipts')->assertSuccessful();
        $entry->refresh();
        $this->assertNotSame('error', $entry->fiscal_status);
        $this->assertNotNull($entry->fiscal_receipt_uuid);
        $this->assertSame($entry->fiscal_receipt_uuid, BanquetPayment::findOrFail($entry->banquet_payment_id)->fiscal_receipt_uuid);
        $this->assertSame(1, FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('subject_id', $entry->id)->count());

        // Run again: nothing more is issued
        $this->artisan('pos:banquet-retry-receipts')->assertSuccessful();
        $this->assertSame(1, FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('subject_id', $entry->id)->count());
    }

    /** C5: the REFUND and the retained SALE receipts of a cancellation are retried too (each once); a too-old one is left. */
    public function test_failed_refund_and_retained_receipts_are_retried_once(): void
    {
        $this->openShift('0', $this->managerTill['device'], $this->manager);
        $event = $this->event('10000000', '2026-12-04'); // 20 days ahead: 30 % retained
        $deposit = $this->rows($event)->first();
        $this->payRow($event, $deposit, ['tender' => 'cash'])->assertCreated();
        $advance = BanquetPaymentEntry::where('banquet_event_id', $event->id)->sole();
        $this->assertNotNull($advance->fiscal_receipt_uuid);

        $real = $this->failingIssuer();
        $this->pos('POST', "/banquets/{$event->uuid}/cancellation", ['reason' => "Mijoz to'yni ko'chirdi"], $this->managerToken)->assertOk();
        $refund = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', BanquetEntryType::REFUND->value)->sole();
        $retain = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', BanquetEntryType::RETAIN->value)->sole();
        $this->assertSame(['error', 'error'], [$refund->fiscal_status, $retain->fiscal_status]);

        $this->app->instance(FiscalIssuer::class, $real);
        $this->artisan('pos:banquet-retry-receipts')->assertSuccessful();
        $refund->refresh();
        $retain->refresh();
        $this->assertNotSame('error', $refund->fiscal_status);
        $this->assertNotSame('error', $retain->fiscal_status);
        $refundReceipt = FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('subject_id', $refund->id)->sole();
        $this->assertSame('refund', $refundReceipt->type->value);
        $this->assertSame($advance->fiscal_receipt_uuid, $refundReceipt->originalReceipt?->uuid);
        $sale = FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('subject_id', $retain->id)->sole();
        $this->assertSame('sale', $sale->type->value);
        $this->assertTrue((bool) collect($sale->payments)->first()['prepayment']);

        $this->artisan('pos:banquet-retry-receipts')->assertSuccessful();
        $this->assertSame(1, FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('subject_id', $refund->id)->count());
        $this->assertSame(1, FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('subject_id', $retain->id)->count());

        // Older than the retry window (3 days): left for a person to look at
        $real = $this->failingIssuer();
        $other = $this->event('5000000', '2027-03-01');
        $this->payRow($other, $this->rows($other)->first(), ['tender' => 'cash'])->assertCreated();
        $old = BanquetPaymentEntry::where('banquet_event_id', $other->id)->sole();
        $old->forceFill(['created_at' => now()->subDays(4)])->save();
        $this->app->instance(FiscalIssuer::class, $real);
        $this->artisan('pos:banquet-retry-receipts')->assertSuccessful();
        $this->assertSame('error', $old->refresh()->fiscal_status);
    }

    public function test_the_retry_is_scheduled_hourly(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'pos:banquet-retry-receipts'));
        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }

    // --- C6 ---

    public function test_duplicate_row_uuids_in_the_schedule_are_refused(): void
    {
        $event = $this->pricedEvent(200, 150);
        $rows = $this->rows($event);
        $first = $rows->first();
        $total = Money::sum(...$rows->pluck('amount')->all());
        $half = $total->percent('50', 0);
        $this->pos('PUT', "/banquets/{$event->uuid}/payments", ['rows' => [
            ['uuid' => $first->uuid, 'kind' => 'deposit', 'due_date' => '2026-11-20', 'amount' => $half->toString()],
            ['uuid' => $first->uuid, 'kind' => 'final', 'due_date' => '2027-01-20', 'amount' => $total->minus($half)->toString()],
        ]], $this->managerToken)->assertUnprocessable()->assertJsonValidationErrors(['rows.1.uuid'], 'data.errors');
        $this->assertSame($rows->count(), $this->rows($event)->count());
    }

    // --- C9 ---

    /** C9: a cancellation locks the shift before the event, as a till payment does (one lock order: no deadlock). */
    public function test_cancellation_locks_the_shift_before_the_event_like_a_payment(): void
    {
        $this->openShift('0', $this->managerTill['device'], $this->manager);
        $event = $this->event('10000000', '2027-01-20');
        $this->payRow($event, $this->rows($event)->first(), ['tender' => 'cash'])->assertCreated();

        $locks = [];
        DB::listen(function ($query) use (&$locks) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, ' for update') || str_contains($sql, ' for no key update')) {
                $locks[] = str_contains($sql, '"pos_shifts"') ? 'shift' : (str_contains($sql, '"banquet_events"') ? 'event' : 'other');
            }
        });
        $this->pos('POST', "/banquets/{$event->uuid}/cancellation", ['reason' => "Mijoz to'yni ko'chirdi"], $this->managerToken)->assertOk();
        $shift = array_search('shift', $locks, true);
        $eventLock = array_search('event', $locks, true);
        $this->assertNotFalse($shift, 'the shift is locked');
        $this->assertNotFalse($eventLock, 'the event is locked');
        $this->assertLessThan($eventLock, $shift, 'shift → event: ' . implode(',', $locks));

        // The till payment: shift → event as well
        $locks = [];
        $second = $this->event('5000000', '2027-02-20');
        $this->payRow($second, $this->rows($second)->first(), ['tender' => 'cash'])->assertCreated();
        $this->assertLessThan(array_search('event', $locks, true), array_search('shift', $locks, true));
    }

    /** C9: the excess refund after a settle locks the shift before the event too. */
    public function test_the_excess_refund_locks_the_shift_before_the_event(): void
    {
        $event = $this->pricedEvent(100, 100, '2027-01-20', [], null, false);
        foreach ($this->rows($event) as $row) {
            $this->payRow($event, $row, ['tender' => 'cash'])->assertCreated();
        }
        $event->refresh()->forceFill(['guests_guaranteed' => 80, 'guests_minimum' => 80])->save();
        $this->pos('POST', "/banquets/{$event->uuid}/start")->assertOk();
        $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->assertJsonPath('data.settlement.settlement.excess_status', 'pending');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 05:00:00', 'UTC'));
        $token = $this->approval('pos.refund');

        $locks = [];
        DB::listen(function ($query) use (&$locks) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, ' for update') || str_contains($sql, ' for no key update')) {
                $locks[] = str_contains($sql, '"pos_shifts"') ? 'shift' : (str_contains($sql, '"banquet_events"') ? 'event' : 'other');
            }
        });
        $this->pos('POST', "/banquets/{$event->uuid}/settlement/refund-excess", ['approval_token' => $token])->assertOk()
            ->assertJsonPath('data.settlement.excess_status', 'refunded');
        $this->assertNotFalse(array_search('shift', $locks, true));
        $this->assertLessThan(array_search('event', $locks, true), array_search('shift', $locks, true), implode(',', $locks));
    }

    // --- C11 / S4 ---

    public function test_a_bank_transfer_is_panel_only_alerts_the_owner_and_a_reference_counts_once(): void
    {
        $event = $this->pricedEvent(200, 150);
        [$deposit, $interim] = $this->rows($event)->all();

        // A terminal PIN session (even the manager's, with banquets.manage) may not record one
        $this->pos('POST', "/banquets/{$event->uuid}/payments/{$deposit->uuid}/bank-transfer", ['reference' => 'PP-77'], $this->managerToken)
            ->assertForbidden()->assertJsonPath('data.reason', 'panel_only');

        $key = (string) Str::uuid();
        $this->transfer($event, $deposit, ['reference' => 'PP-77'], $key)->assertCreated();
        // The same request again (same key): the same entry, one alert
        $this->transfer($event, $deposit, ['reference' => 'PP-77'], $key)->assertCreated();
        $this->assertSame(1, BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', 'transfer')->count());
        $alerts = array_values(array_filter($this->alerts->sent, fn (string $text) => str_contains($text, "Bank o'tkazmasi")));
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString(PosOwnerAlerts::sum($deposit->amount), $alerts[0]);
        $this->assertStringContainsString('PP-77', $alerts[0]);

        // Another request with the same payment order on the same event: refused (ignoring case and spaces)
        $this->transfer($event, $interim, ['reference' => ' pp-77 ', 'amount' => '1000'])->assertStatus(409)->assertJsonPath('data.reason', 'transfer_duplicate');
        $this->assertSame(1, BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', 'transfer')->count());
        // Another reference is fine; the same reference on another event too
        $this->transfer($event, $interim, ['reference' => 'PP-78', 'amount' => '1000'])->assertCreated();
        $other = $this->pricedEvent(200, 150, '2027-03-20');
        $this->transfer($other, $this->rows($other)->first(), ['reference' => 'PP-77', 'amount' => '1000'])->assertCreated();
    }

    public function test_a_mistaken_transfer_is_voided_before_billing_and_the_row_returns_to_due(): void
    {
        $event = $this->pricedEvent(200, 150);
        $deposit = $this->rows($event)->first();
        $this->transfer($event, $deposit, ['reference' => 'PP-90'])->assertCreated();
        $event->refresh();
        $this->assertSame(BanquetEventStatus::CONFIRMED, $event->status);
        $entry = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', 'transfer')->sole();

        $this->voidTransfer($event, $entry, null)->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->pos('DELETE', "/banquets/{$event->uuid}/payments/transfers/{$entry->uuid}?reason=xato", [], $this->managerToken)
            ->assertForbidden()->assertJsonPath('data.reason', 'panel_only');

        $this->voidTransfer($event, $entry)->assertOk()
            ->assertJsonPath('data.event.status', 'hold')
            ->assertJsonPath('data.event.paid_total', '0.00')
            ->assertJsonPath('data.rows.0.status', 'due')
            ->assertJsonPath('data.rows.0.tender', null)
            ->assertJsonPath('data.entries.0.type', 'transfer_void')
            ->assertJsonPath('data.entries.0.reason', "Noto'g'ri tadbirga yozilgan");
        $event->refresh();
        // Nothing else was paid: the event is a hold again, with a fresh hold time
        $this->assertSame(BanquetEventStatus::HOLD, $event->status);
        $this->assertNull($event->confirmed_at);
        $this->assertNotNull($event->hold_expires_at);
        $this->assertTrue($event->hold_expires_at->greaterThan(now()));
        $this->assertSame(BanquetPaymentStatus::DUE, $deposit->refresh()->status);
        $this->assertSame(Money::of($event->total)->toString(), Money::sum(...$this->rows($event)->where('status', BanquetPaymentStatus::DUE)->pluck('amount')->all())->toString());
        $audit = AuditLog::where('action', 'banquet.transfer_voided')->sole();
        $this->assertSame('PP-90', $audit->payload_json['reference'] ?? null);
        $this->assertStringContainsString('bekor qilindi', end($this->alerts->sent));
        $this->assertStringContainsString('PP-90', end($this->alerts->sent));

        // Voiding again: nothing changes (no second alert); the reference may be recorded again
        $alerts = count($this->alerts->sent);
        $this->voidTransfer($event, $entry)->assertOk();
        $this->assertCount($alerts, $this->alerts->sent);
        $this->transfer($event, $deposit->refresh(), ['reference' => 'PP-90'])->assertCreated();
    }

    public function test_voiding_keeps_a_confirmed_event_confirmed_when_something_else_was_paid_and_is_refused_once_billed(): void
    {
        $event = $this->pricedEvent(200, 150);
        [$deposit, $interim] = $this->rows($event)->all();
        $this->payRow($event, $deposit, ['tender' => 'cash'])->assertCreated();
        $this->transfer($event, $interim, ['reference' => 'PP-91', 'amount' => '1000000'])->assertCreated();
        $entry = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', 'transfer')->sole();
        $this->voidTransfer($event, $entry)->assertOk()->assertJsonPath('data.event.status', 'confirmed');
        $this->assertSame(Money::of($deposit->amount)->toString(), Money::of($event->refresh()->paid_total)->toString());

        // A transfer on an event whose final bill exists cannot be voided
        $interim = $this->rows($event)->firstWhere('status', BanquetPaymentStatus::DUE);
        $this->transfer($event, $interim, ['reference' => 'PP-92'])->assertCreated();
        $entry = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', 'transfer')->sole();
        $this->pos('POST', "/banquets/{$event->uuid}/start")->assertOk();
        $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk();
        $this->voidTransfer($event, $entry)->assertStatus(409)->assertJsonPath('data.reason', 'event_billed');
        $this->assertSame('transfer', $entry->refresh()->type->value);

        // Not a transfer: 404
        $payment = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', 'payment')->first();
        $this->voidTransfer($event, $payment)->assertNotFound();
    }
}
