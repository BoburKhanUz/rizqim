<?php

namespace Tests\Feature\PosBanquetPayments;

use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosBanquet\PosBanquetFixtures;
use Tests\TestCase;

/**
 * P6-53 with P6-51/P6-52: an event created through the events API gets its schedule planned (BanquetEventChanged),
 * and a change of its total (more guests) re-plans the unpaid rows.
 */
class BanquetScheduleSyncTest extends TestCase
{
    use PosBanquetFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquets();
    }

    public function test_a_new_event_is_planned_and_a_new_total_re_planned(): void
    {
        $uuid = $this->held();
        $event = BanquetEvent::where('uuid', $uuid)->sole();
        $total = Money::of($event->total);
        $this->assertTrue($total->isPositive());

        $rows = BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->get();
        $this->assertSame(['deposit', 'interim', 'final'], $rows->map(fn ($row) => $row->kind->value)->all());
        $this->assertTrue(Money::sum(...$rows->pluck('amount')->all())->equals($total));
        $this->assertSame($total->percent(30, 0)->toString(), $rows[0]->amount);

        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['guests_guaranteed' => 300, 'version' => $event->version])->assertOk();
        $event->refresh();
        $this->assertTrue(Money::of($event->total)->isGreaterThan($total));
        $after = BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->get();
        $this->assertSame([$rows[0]->amount, $rows[1]->amount], [$after[0]->amount, $after[1]->amount]);
        $this->assertTrue(Money::sum(...$after->pluck('amount')->all())->equals($event->total));
    }
}
