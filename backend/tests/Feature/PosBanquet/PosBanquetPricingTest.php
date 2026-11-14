<?php

namespace Tests\Feature\PosBanquet;

use App\Domain\Pos\Banquet\BanquetEventChanged;
use App\Models\AuditLog;
use App\Models\BanquetEvent;
use App\Models\BanquetHallRate;
use App\Models\BanquetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6-52: package × max(guests_guaranteed, guests_minimum) + hall rent + extras (per event, per person); prices frozen
 * on the contract day; unfreezing with a reason; the quote currency for display only.
 */
class PosBanquetPricingTest extends TestCase
{
    use RefreshDatabase;
    use PosBanquetFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBanquets();
    }

    public function test_the_formula_bills_the_minimum_guests_the_halls_and_the_extras(): void
    {
        $response = $this->hold([
            'hall_ids' => [$this->hallA, $this->hallB],
            'guests_guaranteed' => 180, // below the package minimum (200)
            'extras' => [
                ['extra_id' => $this->cake->id, 'quantity' => '2'],
                ['extra_id' => $this->napkins->id],
                ['name' => 'Karnay-surnay', 'unit_price' => '1500000'],
                ['name' => 'Gul', 'unit_price' => '2000', 'per_person' => true, 'quantity' => '3'],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.guests_billed', 200)
            ->assertJsonPath('data.package_total', '30000000.00') // 150 000 × 200
            ->assertJsonPath('data.hall_total', '10000000.00') // 2 halls × 5 000 000 (evening)
            // 800 000 × 2 + 5 000 × 200 + 1 500 000 + 2 000 × 3 × 200
            ->assertJsonPath('data.extras_total', '5300000.00')
            ->assertJsonPath('data.total', '45300000.00')
            ->assertJsonPath('data.outstanding', '45300000.00')
            ->assertJsonPath('data.package_snapshot.name', 'Standart')
            ->assertJsonPath('data.package_snapshot.included', ['Osh', 'Salatlar', 'Ichimliklar']);

        $lines = collect($response->json('data.lines'));
        $this->assertSame(['package', 'hall_rent', 'hall_rent', 'extra', 'extra', 'extra', 'extra'], $lines->pluck('kind')->all());
        $this->assertSame(200, $lines[4]['persons']);
        $this->assertTrue($lines[4]['per_person']);
        $this->assertNull($lines[3]['persons']);

        // More guaranteed guests than the minimum: they are billed
        $uuid = $response->json('data.uuid');
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['guests_guaranteed' => 250])->assertOk()
            ->assertJsonPath('data.package_total', '37500000.00')
            ->assertJsonPath('data.extras_total', '5850000.00') // napkins and flowers follow the guests
            ->assertJsonPath('data.total', '53350000.00');
    }

    public function test_the_slot_decides_the_rent_and_a_hall_without_rent_costs_nothing(): void
    {
        $this->hold(['slot' => 'day', 'banquet_package_id' => null, 'guests_guaranteed' => 50])->assertCreated()
            ->assertJsonPath('data.hall_total', '3000000.00')
            ->assertJsonPath('data.package_total', '0.00')
            ->assertJsonPath('data.guests_minimum', 0)
            ->assertJsonPath('data.total', '3000000.00');
        $this->hold(['slot' => 'morning', 'banquet_package_id' => null])->assertCreated()->assertJsonPath('data.total', '0.00');
    }

    public function test_frozen_prices_do_not_follow_the_catalogue(): void
    {
        Event::fake([BanquetEventChanged::class]);
        $uuid = $this->held(['extras' => [['extra_id' => $this->cake->id]]]);
        $this->api($this->manager, 'POST', "/api/v1/pos/banquets/{$uuid}/freeze", ['version' => 1])->assertOk()
            ->assertJsonPath('data.frozen', true)
            ->assertJsonPath('data.total', '35800000.00');
        $this->api($this->manager, 'POST', "/api/v1/pos/banquets/{$uuid}/freeze")->assertStatus(409)->assertJsonPath('data.reason', 'already_frozen');

        // The catalogue changes after the contract day
        $this->package->forceFill(['per_person_price' => '200000', 'name' => 'Standart 2027'])->save();
        $this->cake->forceFill(['price' => '1000000'])->save();
        BanquetHallRate::where('hall_id', $this->hallA)->where('slot', 'evening')->update(['price' => '9000000']);
        BanquetHallRate::where('hall_id', $this->hallB)->where('slot', 'evening')->update(['price' => '7000000']);

        $response = $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", [
            'guests_guaranteed' => 220,
            'event_date' => '2027-03-27',
            'extras' => [['extra_id' => $this->cake->id], ['extra_id' => $this->napkins->id]],
        ])->assertOk();
        $response->assertJsonPath('data.package_total', '33000000.00') // the frozen 150 000 × 220
            ->assertJsonPath('data.hall_total', '5000000.00') // the frozen rent: the slot did not change
            ->assertJsonPath('data.extras_total', '1900000.00') // the frozen cake 800 000 + new napkins 5 000 × 220
            ->assertJsonPath('data.package_snapshot.name', 'Standart');
        $this->assertTrue(collect($response->json('data.lines'))->every(fn (array $line) => $line['frozen']));

        // Another hall on a frozen event: today's rent for it
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['hall_ids' => [$this->hallA, $this->hallB]])->assertOk()
            ->assertJsonPath('data.hall_total', '12000000.00');

        // The package cannot change while frozen
        $other = BanquetPackage::create(['store_id' => $this->store->id, 'name' => 'VIP', 'per_person_price' => '300000']);
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['banquet_package_id' => $other->id])
            ->assertStatus(409)->assertJsonPath('data.reason', 'event_frozen');

        // Unfreezing needs a reason; then the catalogue prices apply
        $this->api($this->manager, 'POST', "/api/v1/pos/banquets/{$uuid}/unfreeze", [])->assertStatus(422);
        $this->api($this->manager, 'POST', "/api/v1/pos/banquets/{$uuid}/unfreeze", ['reason' => 'Shartnoma qayta tuzildi'])->assertOk()
            ->assertJsonPath('data.frozen', false)
            ->assertJsonPath('data.package_total', '44000000.00') // 200 000 × 220
            ->assertJsonPath('data.hall_total', '16000000.00');
        $log = AuditLog::where('action', 'banquet.unfrozen')->firstOrFail();
        $this->assertSame('Shartnoma qayta tuzildi', $log->payload_json['reason'] ?? null);
        $this->assertTrue(AuditLog::where('action', 'banquet.frozen')->exists());

        Event::assertDispatched(BanquetEventChanged::class, fn (BanquetEventChanged $event) => $event->action === 'unfrozen' && $event->totalChanged);
    }

    public function test_the_quote_currency_is_for_display_only(): void
    {
        $this->hold(['quote_currency' => 'USD', 'quote_rate' => '12750', 'banquet_package_id' => null, 'slot' => 'day'])->assertCreated()
            ->assertJsonPath('data.total', '3000000.00')
            ->assertJsonPath('data.quote.currency', 'USD')
            ->assertJsonPath('data.quote.total', '235.29');
        $this->hold(['quote_currency' => 'USD', 'event_date' => '2027-03-22'])->assertStatus(422);
    }

    public function test_warnings_for_many_guests_and_a_late_end_never_refuse(): void
    {
        $response = $this->hold(['guests_guaranteed' => 260, 'slot' => 'custom', 'start_time' => '19:00', 'end_time' => '00:30'])->assertCreated();

        $codes = array_column($response->json('data.warnings'), 'code');
        $this->assertSame(['guests_over_threshold', 'outside_time_window'], $codes);
        $this->assertStringContainsString('250', $response->json('data.warnings.0.message'));

        $this->hold(['event_date' => '2027-03-21'])->assertCreated()->assertJsonPath('data.warnings', []);
    }

    public function test_an_unknown_or_foreign_extra_and_a_nameless_free_extra_are_refused(): void
    {
        $foreign = \App\Models\BanquetExtra::create(['store_id' => $this->foreignStore->id, 'name' => 'Begona', 'price' => '1', 'pricing' => 'per_event']);
        $this->hold(['extras' => [['extra_id' => $foreign->id]]])->assertStatus(422)->assertJsonPath('data.reason', 'extra_not_found');
        $uuid = $this->held();
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['extras' => [['quantity' => '2']]])
            ->assertStatus(422)->assertJsonPath('data.reason', 'extra_name_required');
        $this->assertSame(0, \App\Models\BanquetEventLine::where('kind', 'extra')->count());
    }

    public function test_an_unfrozen_event_takes_catalogue_changes_on_its_next_change(): void
    {
        $uuid = $this->held();
        $this->package->forceFill(['per_person_price' => '160000'])->save();

        $this->assertSame('30000000.00', BanquetEvent::where('uuid', $uuid)->value('package_total'));
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['note' => 'Kelin tomoni'])->assertOk()
            ->assertJsonPath('data.package_total', '32000000.00');
    }
}
