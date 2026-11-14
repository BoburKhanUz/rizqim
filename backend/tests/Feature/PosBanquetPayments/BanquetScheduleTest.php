<?php

namespace Tests\Feature\PosBanquetPayments;

use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Banquet\Payments\BanquetPaymentSettings;
use App\Domain\Pos\Banquet\Payments\BanquetSchedule;
use App\Models\AuditLog;
use App\Models\BanquetEvent;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-53: the schedule maths — planned from the template (30 % deposit at booking, 40 % 14 days before, the rest on the
 * day; whole so'm, the final takes the rest; late bookings clamp to today), re-planned when the total changes (unpaid
 * rows adjust from the last, paid rows never change), and the manager's own unpaid rows.
 */
class BanquetScheduleTest extends TestCase
{
    use PosBanquetPaymentsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquetPayments();
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function shape(BanquetEvent $event): array
    {
        return $this->rows($event)->map(fn ($row) => [$row->kind->value, $row->due_date->toDateString(), Money::of($row->amount)->toString(), $row->status->value])->all();
    }

    public function test_the_default_template_plans_deposit_interim_and_final(): void
    {
        $event = $this->event('10000001', '2027-01-20');

        $this->assertSame([
            ['deposit', '2026-11-14', '3000000.00', 'due'],
            ['interim', '2027-01-06', '4000000.00', 'due'],
            ['final', '2027-01-20', '3000001.00', 'due'],
        ], $this->shape($event));
    }

    public function test_a_late_booking_clamps_due_dates_to_today_and_the_rows_add_up(): void
    {
        $rows = BanquetSchedule::plan(Money::of('7777777.77'), BanquetPaymentSettings::DEFAULT_TEMPLATE, '2026-11-20', '2026-11-14');

        $this->assertSame(['2026-11-14', '2026-11-14', '2026-11-20'], array_column($rows, 'due_date'));
        $this->assertSame(['2333333.00', '3111111.00', '2333333.77'], array_map(fn ($row) => $row['amount']->toString(), $rows));
        $this->assertSame([], BanquetSchedule::plan(Money::zero(), BanquetPaymentSettings::DEFAULT_TEMPLATE, '2026-11-20', '2026-11-14'));
    }

    public function test_a_changed_total_moves_the_difference_onto_the_unpaid_rows_and_never_a_paid_one(): void
    {
        $this->openShift();
        $event = $this->event('10000000');
        $this->payRow($event, $this->rows($event)->first())->assertCreated();
        $schedule = app(BanquetSchedule::class);

        // Up: onto the final row
        $event->refresh()->forceFill(['total' => '12000000'])->save();
        $schedule->sync($event);
        $this->assertSame([
            ['deposit', '2026-11-14', '3000000.00', 'paid'],
            ['interim', '2027-01-06', '4000000.00', 'due'],
            ['final', '2027-01-20', '5000000.00', 'due'],
        ], $this->shape($event));

        // Down by more than the final: the final goes, the interim takes the rest of the cut
        $event->forceFill(['total' => '5000000'])->save();
        $schedule->sync($event);
        $this->assertSame([
            ['deposit', '2026-11-14', '3000000.00', 'paid'],
            ['interim', '2027-01-06', '2000000.00', 'due'],
        ], $this->shape($event));

        // Below what was paid: no unpaid rows (the final settlement refunds the difference)
        $event->forceFill(['total' => '2500000'])->save();
        $schedule->sync($event);
        $this->assertSame([['deposit', '2026-11-14', '3000000.00', 'paid']], $this->shape($event));

        // Up again: a new final row on the day
        $event->forceFill(['total' => '4000000'])->save();
        $schedule->sync($event);
        $this->assertSame([
            ['deposit', '2026-11-14', '3000000.00', 'paid'],
            ['final', '2027-01-20', '1000000.00', 'due'],
        ], $this->shape($event));
    }

    public function test_the_store_template_is_used(): void
    {
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/payment-settings', [
            'schedule_template' => [
                ['kind' => 'deposit', 'percent' => 50, 'days_before' => null],
                ['kind' => 'final', 'percent' => null, 'days_before' => 3],
            ],
        ])->assertOk()->assertJsonPath('data.schedule_template.0.percent', '50');

        $this->assertSame([
            ['deposit', '2026-11-14', '1000000.00', 'due'],
            ['final', '2027-01-17', '1000000.00', 'due'],
        ], $this->shape($this->event('2000000')));
    }

    public function test_the_manager_edits_the_unpaid_rows_which_must_add_up(): void
    {
        $this->openShift();
        $event = $this->event('10000000');
        $this->payRow($event, $this->rows($event)->first())->assertCreated();
        [, $interim] = $this->rows($event)->all();
        $paidBefore = $this->rows($event)->first()->only(['amount', 'status', 'due_date']);

        $this->panel($this->manager, 'PUT', "/pos/banquets/{$event->uuid}/payments", ['rows' => [
            ['uuid' => $interim->uuid, 'kind' => 'interim', 'due_date' => '2026-12-01', 'amount' => '2000000'],
            ['kind' => 'interim', 'due_date' => '2026-12-25', 'amount' => '2000000'],
        ]])->assertUnprocessable()->assertJsonPath('data.reason', 'schedule_sum_mismatch')->assertJsonPath('data.expected', '7000000.00');

        $this->panel($this->manager, 'PUT', "/pos/banquets/{$event->uuid}/payments", ['version' => $event->refresh()->version, 'rows' => [
            ['uuid' => $interim->uuid, 'kind' => 'interim', 'due_date' => '2026-12-01', 'amount' => '2000000'],
            ['kind' => 'interim', 'due_date' => '2026-12-25', 'amount' => '2000000'],
            ['kind' => 'final', 'due_date' => '2027-01-20', 'amount' => '3000000'],
        ]])->assertOk()->assertJsonCount(4, 'data.rows')->assertJsonPath('data.summary.outstanding', '7000000.00');

        $this->assertSame([
            ['deposit', '2026-11-14', '3000000.00', 'paid'],
            ['interim', '2026-12-01', '2000000.00', 'due'],
            ['interim', '2026-12-25', '2000000.00', 'due'],
            ['final', '2027-01-20', '3000000.00', 'due'],
        ], $this->shape($event));
        $this->assertSame($interim->id, $this->rows($event)->get(1)->id);
        $this->assertEquals($paidBefore, $this->rows($event)->first()->only(['amount', 'status', 'due_date']));
        $this->assertTrue(AuditLog::where('action', 'banquet.schedule_updated')->exists());

        // A paid row cannot be edited, a stale version is refused, a date in the past too
        $paid = $this->rows($event)->first();
        $this->panel($this->manager, 'PUT', "/pos/banquets/{$event->uuid}/payments", ['rows' => [
            ['uuid' => $paid->uuid, 'kind' => 'deposit', 'due_date' => '2027-01-20', 'amount' => '7000000'],
        ]])->assertUnprocessable()->assertJsonPath('data.reason', 'payment_not_editable');
        $this->panel($this->manager, 'PUT', "/pos/banquets/{$event->uuid}/payments", ['version' => 1, 'rows' => [
            ['kind' => 'final', 'due_date' => '2027-01-20', 'amount' => '7000000'],
        ]])->assertConflict()->assertJsonPath('data.reason', 'version_conflict');
        $this->panel($this->manager, 'PUT', "/pos/banquets/{$event->uuid}/payments", ['rows' => [
            ['kind' => 'final', 'due_date' => '2026-11-01', 'amount' => '7000000'],
        ]])->assertUnprocessable()->assertJsonPath('data.reason', 'due_date_past');
        $this->assertSame(BanquetPaymentStatus::PAID, $paid->refresh()->status);
    }

    public function test_editing_needs_banquets_manage_and_the_module_reading_does_not(): void
    {
        $event = $this->event('1000000');
        $this->pos('PUT', "/banquets/{$event->uuid}/payments", ['rows' => []])->assertForbidden();
        $this->pos('GET', "/banquets/{$event->uuid}/payments")->assertOk()
            ->assertJsonPath('data.summary.outstanding', '1000000.00')
            ->assertJsonPath('data.summary.next_due.kind', 'deposit')
            ->assertJsonPath('data.summary.overdue', false);

        $settings = $this->branch->fresh()->pos_settings;
        $settings['modules']['banquet'] = false;
        $this->branch->forceFill(['pos_settings' => $settings])->save();
        app()->forgetScopedInstances();
        $this->panel($this->manager, 'PUT', "/pos/banquets/{$event->uuid}/payments", ['rows' => [
            ['kind' => 'final', 'due_date' => '2027-01-20', 'amount' => '1000000'],
        ]])->assertForbidden()->assertJsonPath('code', 'module_disabled');
        $this->panel($this->manager, 'GET', "/pos/banquets/{$event->uuid}/payments")->assertOk();
    }

    public function test_overdue_rows_show_on_the_event_card(): void
    {
        $event = $this->event('1000000');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 10:00:00', 'Asia/Tashkent'));

        $this->panel($this->manager, 'GET', "/pos/banquets/{$event->uuid}/payments")->assertOk()
            ->assertJsonPath('data.summary.overdue', true)
            ->assertJsonPath('data.summary.overdue_amount', '300000.00')
            ->assertJsonPath('data.summary.overdue_count', 1)
            ->assertJsonPath('data.rows.0.overdue', true)
            ->assertJsonPath('data.rows.1.overdue', false);
    }
}
