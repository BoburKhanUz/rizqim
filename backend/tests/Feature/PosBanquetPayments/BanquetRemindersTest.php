<?php

namespace Tests\Feature\PosBanquetPayments;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P6-53: the daily reminders (pos:banquet-payment-reminders): 3 and 1 days before a due date, overdue on the first day
 * after and then weekly; once per row and day (running twice sends nothing new); paid rows and cancelled events are
 * never reminded; the store's own reminder days.
 */
class BanquetRemindersTest extends TestCase
{
    use PosBanquetPaymentsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquetPayments();
    }

    private function on(string $date): int
    {
        $this->travelTo(Carbon::parse("{$date} 09:05:00", 'Asia/Tashkent'));
        $before = count($this->alerts->sent);
        $this->artisan('pos:banquet-payment-reminders')->assertSuccessful();

        return count($this->alerts->sent) - $before;
    }

    public function test_before_and_overdue_reminders_once_per_row_and_day(): void
    {
        // Rows: deposit due 14.11, interim due 06.01.2027, final 20.01.2027
        $event = $this->event('10000000', '2027-01-20');

        $this->assertSame(0, $this->on('2026-11-14')); // the deposit is due today: no "0 days" reminder by default
        $this->assertSame(1, $this->on('2026-11-15')); // the deposit: one day overdue
        $this->assertStringContainsString("Muddati 1 kun o'tdi: Zaklad 3 000 000 so'm", end($this->alerts->sent));
        $this->assertStringContainsString('Karimovlar oilasi', end($this->alerts->sent));
        $this->assertSame(0, $this->on('2026-11-15')); // again the same day: nothing new
        $this->assertSame(0, $this->on('2026-11-16'));
        $this->assertSame(1, $this->on('2026-11-21')); // a week overdue

        // Paid (the till's PIN session of the fixtures has expired by now: marked as the pay endpoint leaves it)
        $this->rows($event)->first()->forceFill(['status' => 'paid', 'tender' => 'cash', 'paid_at' => now()])->save();
        $this->assertSame(0, $this->on('2026-11-28'));

        $this->assertSame(1, $this->on('2027-01-03')); // the interim in 3 days
        $this->assertStringContainsString("3 kundan so'ng to'lanadi: Oraliq to'lov 4 000 000 so'm", end($this->alerts->sent));
        $this->assertSame(1, $this->on('2027-01-05')); // in 1 day
        $this->assertSame(2, DB::table('banquet_payment_reminders')->where('kind', 'before')->count());
    }

    public function test_the_store_days_and_a_cancelled_event(): void
    {
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/payment-settings', ['reminder_days' => [0], 'overdue_reminders' => false])
            ->assertOk()->assertJsonPath('data.reminder_days', [0])->assertJsonPath('data.overdue_reminders', false);
        $event = $this->event('10000000', '2027-01-20');
        $cancelled = $this->event('10000000', '2027-01-21');
        $cancelled->forceFill(['status' => BanquetEventStatus::CANCELLED])->save();

        $this->assertSame(1, $this->on('2026-11-14')); // due today (of the live event only)
        $this->assertStringContainsString("Bugun to'lanishi kerak", end($this->alerts->sent));
        $this->assertSame(0, $this->on('2026-11-15')); // overdue reminders off
        $this->assertSame(1, $this->on('2027-01-06'));
        $this->assertSame($event->id, (int) DB::table('banquet_payment_reminders')->join('banquet_payments', 'banquet_payments.id', '=', 'banquet_payment_reminders.banquet_payment_id')->value('banquet_event_id'));
    }
}
