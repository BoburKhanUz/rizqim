<?php

namespace Tests\Feature\PosBanquetOps;

use App\Domain\Pos\Banquet\Ops\BanquetContracts;
use App\Enums\UserRole;
use App\Models\BanquetOpsSetting;
use App\Models\BanquetSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P6-54 (E5): the contract from the store's template (placeholders, the schedule and cancellation rules, the quote
 * currency for reference), its per-store-and-year numbering, who may read it (the customer's data), and the guest and
 * time warnings — never a refusal.
 */
class BanquetContractTest extends TestCase
{
    use PosBanquetOpsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquetOps();
    }

    public function test_the_default_template_is_filled_from_the_event(): void
    {
        $event = $this->pricedEvent(200, 150);
        $event->forceFill(['quote_currency' => 'USD', 'quote_rate' => '12800'])->save();

        $document = $this->posAs($this->manager, 'GET', "/banquets/{$event->uuid}/contract")->assertOk()->json('data');

        $this->assertNull($document['contract_no']);
        $text = $document['text'];
        $this->assertStringContainsString("TADBIR O'TKAZISH SHARTNOMASI № ________", $text);
        $this->assertStringContainsString('Karimovlar oilasi (tel.: +998901234567)', $text);
        $this->assertStringContainsString("Tadbir: To'y. Sana: 20.01.2027, vaqti: 18:00–23:00 (Kechqurun).", $text);
        $this->assertStringContainsString('Zal: Oq zal.', $text);
        $this->assertStringContainsString('kafolatlangan 200 kishi, minimal 150 kishi', $text);
        $this->assertStringContainsString("Paket: Standart, kishi boshiga 150 000 so'm. Paket summasi: 30 000 000 so'm.", $text);
        $this->assertStringContainsString('Paketga kiradi: Osh; Salatlar.', $text);
        $this->assertStringContainsString("Zal ijarasi: 5 000 000 so'm.", $text);
        $this->assertStringContainsString("   - Tort: 800 000 so'm", $text);
        $this->assertStringContainsString("   - Salfetka (200 kishi): 1 000 000 so'm", $text);
        $this->assertStringContainsString("Jami: 36 800 000 so'm. To'langan: 0 so'm. Qoldiq: 36 800 000 so'm.", $text);
        $this->assertStringContainsString("2 875.00 USD (kurs: 1 USD = 12 800.00 so'm", $text);
        $this->assertStringContainsString("1. Zaklad — 14.11.2026 — 11 040 000 so'm (To'lanmagan)", $text);
        $this->assertStringContainsString("3. Yakuniy to'lov — 20.01.2027 — 11 040 000 so'm (To'lanmagan)", $text);
        $this->assertStringContainsString('   - tadbirdan 30 kun va undan oldin: 0%', $text);
        $this->assertStringContainsString('   - tadbirdan 14 kun va undan oldin: 30%', $text);
        $this->assertStringNotContainsString('{{', $text);
        $this->assertSame(explode("\n", $text), $document['lines']);
    }

    public function test_the_store_template_and_unknown_placeholders(): void
    {
        $event = $this->pricedEvent();
        BanquetOpsSetting::where('store_id', $this->store->id)->update(['contract_template' => "№{{ contract_no }} {{customer_name}} — {{total}}\n\n\n\n{{nomalum}}"]);

        $this->assertSame("№________ Karimovlar oilasi — 36 800 000 so'm\n\n{{nomalum}}", $this->posAs($this->manager, 'GET', "/banquets/{$event->uuid}/contract")->json('data.text'));
        $this->assertSame('a b', BanquetContracts::render('{{x}} {{ y }}', ['x' => 'a', 'y' => 'b']));
    }

    public function test_contract_numbers_go_per_store_and_year_once(): void
    {
        $first = $this->pricedEvent();
        $second = $this->pricedEvent(100, 100, '2027-02-10');

        $this->posAs($this->manager, 'POST', "/banquets/{$first->uuid}/contract/number")->assertOk()->assertJsonPath('data.contract_no', '2026-0001')
            ->assertJsonPath('data.fields.contract_no', '2026-0001');
        // Again: the same number
        $this->posAs($this->manager, 'POST', "/banquets/{$first->uuid}/contract/number")->assertOk()->assertJsonPath('data.contract_no', '2026-0001');
        $this->posAs($this->manager, 'POST', "/banquets/{$second->uuid}/contract/number")->assertOk()->assertJsonPath('data.contract_no', '2026-0002');
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'banquet.contract_numbered')->where('model_id', $first->id)->count());
        $this->assertGreaterThan($first->version, $first->refresh()->version);

        // A new year starts again at 1; a number typed by hand is skipped
        $this->travelTo(Carbon::parse('2027-01-02 10:00:00', 'Asia/Tashkent'));
        $third = $this->pricedEvent(100, 100, '2027-03-01');
        $fourth = $this->pricedEvent(100, 100, '2027-03-02', ['contract_no' => '2027-0001']);
        $this->posAs($this->manager, 'POST', "/banquets/{$third->uuid}/contract/number")->assertOk()->assertJsonPath('data.contract_no', '2027-0002');
        $this->assertSame('2027-0001', $fourth->refresh()->contract_no);
    }

    public function test_the_contract_carries_the_customer_and_needs_banquets_manage(): void
    {
        $event = $this->pricedEvent();
        // The cashier takes payments but reads no contract; the kitchen neither
        $this->pos('GET', "/banquets/{$event->uuid}/contract")->assertForbidden();
        $this->posAs($this->makeStaff(UserRole::COOK, $this->branch), 'GET', "/banquets/{$event->uuid}/contract")->assertForbidden();
        $this->pos('POST', "/banquets/{$event->uuid}/contract/number")->assertForbidden();
    }

    public function test_guest_and_time_warnings_never_refuse(): void
    {
        $event = $this->pricedEvent(240, 200);
        $warnings = $this->pos('GET', "/banquets/{$event->uuid}/warnings")->assertOk()->json('data.warnings');
        $this->assertSame(['guests_over_threshold'], array_column($warnings, 'code'));
        $this->assertStringContainsString("Mehmonlar soni 240: 200 dan ko'p", $warnings[0]['message']);

        // 310 actual guests: the highest threshold passed; an event till 00:30 is outside the window
        $event->forceFill(['guests_actual' => 310, 'ends_at' => $event->starts_at->copy()->addHours(6)->addMinutes(30)])->save();
        $warnings = $this->pos('GET', "/banquets/{$event->uuid}/warnings")->assertOk()->json('data.warnings');
        $this->assertSame(['guests_over_threshold', 'outside_time_window'], array_column($warnings, 'code'));
        $this->assertStringContainsString("310: 300 dan ko'p", $warnings[0]['message']);

        // The store's own thresholds and window; an early osh at 05:00 warns
        BanquetSetting::create(['store_id' => $this->store->id, 'guest_warning_thresholds' => [500], 'window_start' => '05:30', 'window_end' => '23:30']);
        $morning = $this->pricedEvent(400, 100, '2027-01-21', ['starts_at' => Carbon::parse('2027-01-21 05:00', 'Asia/Tashkent'), 'ends_at' => Carbon::parse('2027-01-21 09:00', 'Asia/Tashkent'), 'slot' => 'morning', 'event_type' => 'osh']);
        $this->assertSame(['outside_time_window'], array_column($this->pos('GET', "/banquets/{$morning->uuid}/warnings")->json('data.warnings'), 'code'));
        // On the production sheet, the contract and the settlement too
        $this->assertSame(['outside_time_window'], array_column($this->pos('GET', "/banquets/{$morning->uuid}/production-sheet")->json('data.warnings'), 'code'));
        $this->assertSame(['outside_time_window'], array_column($this->posAs($this->manager, 'GET', "/banquets/{$morning->uuid}/contract")->json('data.warnings'), 'code'));
        $this->assertSame(['outside_time_window'], array_column($this->posAs($this->manager, 'GET', "/banquets/{$morning->uuid}/settlement")->json('data.warnings'), 'code'));
    }
}
