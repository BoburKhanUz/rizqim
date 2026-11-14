<?php

namespace Tests\Feature\PosBanquetOps;

use App\Domain\Pos\Banquet\Payments\BanquetCancellationService;
use App\Domain\Pos\Banquet\Payments\BanquetPaymentSettings;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\BanquetSetting;
use App\Models\Order;
use App\Services\Order\StaffOrderPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6 wave 5 review fixes (H3).
 *  - S1: the store-wide banquet rules (settings, payment rules, operations settings, packages, extras) are changed only
 *    by the owner or a store-wide manager; a manager bound to one branch (the default manager role carries
 *    banquets.manage) reads them and gets 403 `store_wide_only` on every write; a hall's rent stays branch-scoped.
 *  - S2: the event-day order's note (kitchen tickets, KDS) carries no customer data: "Tadbir: <type>, <halls>, <date>".
 *  - S5: the banquet settings audits show the package codes and osh ingredient keys; the contract template as a diff
 *    summary.
 *  - I2: the phone search of GET /pos/banquets has its own limiter (20 a minute per staff member and terminal).
 */
class BanquetScopePrivacyTest extends TestCase
{
    use PosBanquetOpsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBanquetOps();
    }

    public function test_branch_bound_manager_cannot_change_store_wide_banquet_rules(): void
    {
        $this->assertNotNull($this->manager->branch_id, 'fixture: the manager is bound to the main branch');
        // Another branch's event 6 days ahead: the default rule keeps 100 % of what was paid
        $other = $this->event('30000000', '2026-11-20', [], $this->otherBranch);
        $this->assertEquals(100.0, (float) app(BanquetCancellationService::class)->quote($other)['rule_percent']);

        $writes = [
            'payment-settings' => ['PUT', '/owner/pos/banquets/payment-settings', ['cancellation_rules' => [['min_days' => 0, 'retained_percent' => 0]]]],
            'settings' => ['PUT', '/owner/pos/banquets/settings', ['advance_mxik' => '99999999999999999']],
            'ops-settings' => ['PUT', '/owner/pos/banquets/ops-settings', ['contract_template' => 'Bo\'sh shartnoma {{total}}']],
            'package create' => ['POST', '/owner/pos/banquets/packages', ['name' => 'Arzon', 'per_person_price' => '1']],
            'package change' => ['PATCH', "/owner/pos/banquets/packages/{$this->package->id}", ['per_person_price' => '1']],
            'package delete' => ['DELETE', "/owner/pos/banquets/packages/{$this->package->id}", []],
            'extra create' => ['POST', '/owner/pos/banquets/extras', ['name' => 'Bepul', 'price' => '0', 'pricing' => 'per_event']],
            'extra change' => ['PATCH', "/owner/pos/banquets/extras/{$this->cake->id}", ['price' => '1']],
            'extra delete' => ['DELETE', "/owner/pos/banquets/extras/{$this->cake->id}", []],
        ];
        foreach ($writes as $what => [$method, $uri, $body]) {
            $response = $this->panel($this->manager, $method, $uri, $body);
            $this->assertSame(403, $response->status(), "a manager bound to branch {$this->branch->id} changed the store-wide {$what}");
            $this->assertSame('store_wide_only', $response->json('data.reason'), $what);
        }

        $this->assertEquals(100.0, (float) app(BanquetCancellationService::class)->quote($other->refresh())['rule_percent']);
        $this->assertSame(0, (int) app(BanquetPaymentSettings::class)->of((int) $this->store->id)['version']);
        $this->assertNull(BanquetSetting::where('store_id', $this->store->id)->first());
        $this->assertSame('150000.00', (string) $this->package->fresh()->per_person_price);
        $this->assertTrue((bool) $this->package->fresh()->is_active);
        $this->assertSame('800000.00', (string) $this->cake->fresh()->price);

        // Reads stay open, and the rent of the manager's own hall is theirs to set
        foreach (['settings', 'payment-settings', 'ops-settings', 'packages', 'extras', 'halls'] as $page) {
            $this->panel($this->manager, 'GET', "/owner/pos/banquets/{$page}")->assertOk();
        }
        $this->panel($this->manager, 'PUT', "/owner/pos/banquets/halls/{$this->hallA}/rates", ['rates' => ['evening' => '6000000']])->assertOk();
    }

    public function test_owner_and_store_wide_manager_still_change_store_wide_banquet_rules(): void
    {
        $storeWide = $this->makeStaff(UserRole::MANAGER);
        $this->assertNull($storeWide->branch_id);

        foreach ([$this->owner, $storeWide] as $i => $user) {
            $this->panel($user, 'PUT', '/owner/pos/banquets/payment-settings', ['cancellation_rules' => [['min_days' => 0, 'retained_percent' => 50 + $i]]])->assertOk();
            $this->panel($user, 'PUT', '/owner/pos/banquets/settings', ['hold_hours' => 48 + $i])->assertOk();
            $this->panel($user, 'PUT', '/owner/pos/banquets/ops-settings', ['reserve_percent' => (string) (5 + $i)])->assertOk();
            $this->panel($user, 'PATCH', "/owner/pos/banquets/packages/{$this->package->id}", ['per_person_price' => (string) (160000 + $i)])->assertOk();
            $this->panel($user, 'POST', '/owner/pos/banquets/extras', ['name' => "Gul {$i}", 'price' => '100000', 'pricing' => 'per_event'])->assertCreated();
            $this->panel($user, 'PATCH', "/owner/pos/banquets/extras/{$this->napkins->id}", ['price' => (string) (6000 + $i)])->assertOk();
        }

        $this->assertSame(49, (int) BanquetSetting::where('store_id', $this->store->id)->value('hold_hours'));
        $this->assertSame('160001.00', (string) $this->package->fresh()->per_person_price);
        $this->assertEquals(51, app(BanquetPaymentSettings::class)->of((int) $this->store->id)['cancellation_rules'][0]['retained_percent']);
    }

    public function test_event_day_order_note_carries_no_customer_data_to_the_kitchen(): void
    {
        $this->openShift('100000');
        foreach ([['title' => null], ['title' => "Aziz va Malika to'yi"]] as $i => $attributes) {
            $event = $this->pricedEvent(date: $i === 0 ? '2027-01-20' : '2027-01-21', attributes: $attributes);
            [$deposit] = $this->rows($event)->all();
            $this->payRow($event, $deposit, ['tender' => 'cash'])->assertCreated();
            $this->pos('POST', "/banquets/{$event->uuid}/start")->assertOk();

            $order = Order::findOrFail($event->refresh()->order_id);
            $date = $event->event_date->format('d.m.Y');
            $this->assertSame("Tadbir: To'y, Oq zal, {$date}", $order->notes);

            $ticket = app(StaffOrderPresenter::class)->ticket($order);
            $paper = (string) json_encode($ticket, JSON_UNESCAPED_UNICODE);
            $this->assertStringContainsString("Tadbir: To'y, Oq zal, {$date}", $paper);
            $this->assertStringNotContainsString('Karimovlar', $paper, "the kitchen ticket carries the banquet customer's name");
            $this->assertStringNotContainsString('Aziz va Malika', $paper, "the kitchen ticket carries the event's title");
            $this->assertStringNotContainsString('901234567', $paper);
        }
    }

    public function test_banquet_settings_audits_show_codes_and_a_contract_diff_summary(): void
    {
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/settings', [
            'advance_mxik' => '10305001001000000', 'advance_package_code' => '1501234',
            'cancellation_mxik' => '10305001002000000', 'cancellation_package_code' => '1505678',
        ])->assertOk();
        $after = AuditLog::where('action', 'banquet.settings_updated')->latest('id')->firstOrFail()->after;
        $this->assertSame('1501234', $after['advance_package_code']);
        $this->assertSame('1505678', $after['cancellation_package_code']);
        $this->assertSame('10305001001000000', $after['advance_mxik']);

        $settings = $this->panel($this->owner, 'GET', '/owner/pos/banquets/ops-settings')->assertOk()->json('data');
        $template = "Shartnoma\nBuyurtmachi: {{customer_name}}\nJami: {{total}}";
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/ops-settings', ['contract_template' => $template, 'version' => $settings['version']])->assertOk();
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/ops-settings', [
            'package_package_code' => '1509999',
            'contract_template' => "Shartnoma\nBuyurtmachi: {{customer_name}}\nJami: {{total}} so'm\nImzo",
            'osh' => ['persons_per_kg' => '5', 'kazans' => [10, 20], 'ingredients' => [
                ['key' => 'rice', 'name' => 'Guruch', 'unit' => 'kg', 'per_kg' => '1'],
                ['key' => 'carrot', 'name' => 'Sabzi', 'unit' => 'kg', 'per_kg' => '0.8'],
            ]],
        ])->assertOk();

        $audit = AuditLog::where('action', 'banquet.ops_settings_updated')->latest('id')->firstOrFail();
        $this->assertSame('1509999', $audit->after['mxik']['package']['package_code']);
        $this->assertSame(['rice', 'carrot'], array_column($audit->after['osh']['ingredients'], 'slug'));
        $this->assertSame(['length', 'lines', 'sha1', 'changed_lines'], array_keys($audit->after['contract_template']));
        $this->assertSame([3, 4], $audit->after['contract_template']['changed_lines']);
        $this->assertSame(4, $audit->after['contract_template']['lines']);
        $this->assertSame(3, $audit->before['contract_template']['lines']);
        $this->assertStringNotContainsString('[redacted]', (string) json_encode([$audit->before, $audit->after]));
    }

    public function test_the_phone_search_has_its_own_limiter(): void
    {
        $this->event('30000000', '2027-02-10');
        for ($i = 0; $i < 20; $i++) {
            $this->pos('GET', '/banquets?phone=4567')->assertOk();
        }
        $this->pos('GET', '/banquets?phone=4567')->assertStatus(429)->assertJsonPath('data.reason', 'search_rate_limited');
        // The list by dates is not this limiter's
        $this->pos('GET', '/banquets?from=2027-02-01&to=2027-02-28')->assertOk()->assertJsonCount(1, 'data.events');
        // Another staff member on another terminal searches on
        $this->pos('GET', '/banquets?phone=4567', [], $this->managerToken)->assertOk();
    }
}
