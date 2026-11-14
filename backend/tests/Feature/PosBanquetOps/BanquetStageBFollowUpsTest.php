<?php

namespace Tests\Feature\PosBanquetOps;

use App\Models\BanquetEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6 wave 5 stage B follow-ups: (a) the owner's "Tadbirlar" report counts the remainder paid on a settled event's
 * final bill as paid (not only the advances), and an event billed but not yet paid in full shows that remainder as
 * outstanding; (b) the calendar and list summaries carry the schedule's `overdue` flag (BanquetSchedule::summary).
 */
class BanquetStageBFollowUpsTest extends TestCase
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

    public function test_the_report_counts_the_remainder_paid_on_the_final_bill(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $this->pos('PATCH', "/banquets/{$event->uuid}/guests", ['guests_actual' => 180], $this->managerToken)->assertOk();
        $bill = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->assertSame('36800000.00', $bill['total']);
        $this->assertSame('11040000.00', $bill['due_amount']);

        // Billed, the rest not paid yet: the unpaid schedule rows went, the bill's rest is still outstanding
        $report = $this->panel($this->owner, 'GET', '/owner/pos/banquets/report?from=2027-01-01&to=2027-01-31')->assertOk()->json('data');
        $this->assertSame('25760000.00', $report['paid']);
        $this->assertSame('11040000.00', $report['outstanding']);
        $this->assertSame(['25760000.00', '11040000.00'], [$report['rows'][0]['paid'], $report['rows'][0]['outstanding']]);

        // Half of the rest, then the rest: paid = the event total, nothing outstanding
        $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => '5000000']])->assertOk();
        $report = $this->panel($this->owner, 'GET', '/owner/pos/banquets/report?from=2027-01-01&to=2027-01-31')->assertOk()->json('data');
        $this->assertSame('30760000.00', $report['paid']);
        $this->assertSame('6040000.00', $report['outstanding']);

        $this->pay($bill['uuid'], [['tender' => 'card', 'amount' => '6040000', 'card_type' => 'uzcard', 'transaction_id' => 'RRN-9']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame('settled', $event->refresh()->status->value);
        $report = $this->panel($this->owner, 'GET', '/owner/pos/banquets/report?from=2027-01-01&to=2027-01-31')->assertOk()->json('data');
        $this->assertSame('36800000.00', $report['total']);
        $this->assertSame('36800000.00', $report['paid']);
        $this->assertSame('0.00', $report['outstanding']);
        $this->assertSame(['settled', '36800000.00', '36800000.00', '0.00'], [$report['rows'][0]['status'], $report['rows'][0]['total'], $report['rows'][0]['paid'], $report['rows'][0]['outstanding']]);
    }

    public function test_calendar_and_list_summaries_carry_the_overdue_flag(): void
    {
        // Booked today: the deposit row is due today (not overdue yet)
        $late = $this->pricedEvent(200, 150, '2027-01-20');
        $fine = $this->pricedEvent(200, 150, '2027-01-21');
        $this->payRow($fine, $this->rows($fine)->first(), ['tender' => 'cash'])->assertCreated();

        $flags = fn (string $uri) => collect($this->panel($this->manager, 'GET', $uri)->assertOk()->json('data.events'))->mapWithKeys(fn (array $e) => [$e['uuid'] => $e['overdue']])->all();
        $this->assertSame([$late->uuid => false, $fine->uuid => false], $flags('/pos/banquets?from=2027-01-01&to=2027-01-31'));

        // Two days later the unpaid deposit is overdue; the paid one's next row is weeks away
        $this->travel(2)->days();
        $this->assertSame([$late->uuid => true, $fine->uuid => false], $flags('/pos/banquets?from=2027-01-01&to=2027-01-31'));
        $calendar = $this->panel($this->manager, 'GET', '/pos/banquets/calendar?from=2027-01-01&to=2027-01-31')->assertOk()->json('data.halls');
        $events = collect($calendar)->flatMap(fn (array $hall) => $hall['events'])->mapWithKeys(fn (array $e) => [$e['uuid'] => $e['overdue']])->all();
        $this->assertSame([$late->uuid => true, $fine->uuid => false], $events);
    }
}
