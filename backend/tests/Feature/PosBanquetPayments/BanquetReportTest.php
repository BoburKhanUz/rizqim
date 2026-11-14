<?php

namespace Tests\Feature\PosBanquetPayments;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\BanquetPaymentSetting;
use App\Models\RolePermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P6-53: the owner's "Tadbirlar" report (events, guests, total, paid, outstanding, overdue, cancelled with retained
 * and refunded parts) and its Excel export; the phone masked for a report-only reader; tenant isolation; the payment
 * rules (defaults, validation across rows, audit, version, the module gate).
 */
class BanquetReportTest extends TestCase
{
    use PosBanquetPaymentsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquetPayments();
        $this->openShift();
        $this->openShift('0', $this->managerTill['device'], $this->manager);
    }

    public function test_the_report_numbers(): void
    {
        // A: 10 000 000, deposit paid in cash (3 000 000)
        $a = $this->event('10000000', '2026-12-20');
        $this->payRow($a, $this->rows($a)->first())->assertCreated();
        // B: 6 000 000, nothing paid; its deposit (due today) will be overdue
        $this->event('6000000', '2026-12-10', ['customer_name' => 'Aliyevlar', 'guests_guaranteed' => 150, 'guests_minimum' => 100]);
        // C: cancelled 10 days ahead with 1 000 000 paid by card: all retained
        $c = $this->event('5000000', '2026-11-24');
        $this->payRow($c, $this->rows($c)->first(), ['tender' => 'card', 'amount' => '1000000', 'card_type' => 'humo', 'transaction_id' => 'X'])->assertCreated();
        $this->pos('POST', "/banquets/{$c->uuid}/cancellation", ['reason' => 'Bekor'], $this->managerToken)->assertOk();
        // Another month and another store: not in the report
        $this->event('9000000', '2027-02-01');
        $other = $this->makeStore('Boshqa');
        $this->event('7000000', '2026-12-15', ['store_id' => $other->id], $this->makeBranch($other, 'B'));

        $this->travelTo(Carbon::parse('2026-11-15 10:00:00', 'Asia/Tashkent'));
        $report = $this->panel($this->owner, 'GET', '/owner/pos/banquets/report?from=2026-11-01&to=2026-12-31')->assertOk()->json('data');

        $this->assertSame(2, $report['events']);
        $this->assertSame(1, $report['cancelled']);
        $this->assertSame(350, $report['guests']);
        $this->assertSame('16000000.00', $report['total']);
        $this->assertSame('4000000.00', $report['paid']);
        $this->assertSame('13000000.00', $report['outstanding']);
        $this->assertSame(['count' => 1, 'amount' => '1800000.00'], $report['overdue']);
        $this->assertSame('1000000.00', $report['retained']);
        $this->assertSame('0.00', $report['refunded']);
        $this->assertCount(3, $report['rows']);
        $this->assertSame(['2026-11-24', '2026-12-10', '2026-12-20'], array_column($report['rows'], 'event_date'));
        $this->assertSame('+998901234567', $report['rows'][0]['customer_phone']);
        $this->assertSame(['1000000.00', '0.00', '1000000.00', 'cancelled'], [$report['rows'][0]['paid'], $report['rows'][0]['outstanding'], $report['rows'][0]['retained'], $report['rows'][0]['status']]);
        $this->assertSame(['0.00', '6000000.00', '1800000.00'], [$report['rows'][1]['paid'], $report['rows'][1]['outstanding'], $report['rows'][1]['overdue']]);

        // A report-only reader: phones masked
        RolePermissionOverride::create(['store_id' => $this->store->id, 'role' => UserRole::CASHIER->value, 'permission' => 'reports.view', 'allowed' => true]);
        $accountant = $this->cashier;
        $masked = $this->panel($accountant, 'GET', '/owner/pos/banquets/report?from=2026-11-01&to=2026-12-31')->assertOk()->json('data.rows.0.customer_phone');
        $this->assertStringContainsString('*', (string) $masked);

        $this->panel($this->owner, 'GET', '/owner/pos/banquets/export?from=2026-11-01&to=2026-12-31')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertTrue(AuditLog::where('action', 'banquet.report_exported')->exists());
    }

    public function test_payment_rules_defaults_validation_audit_and_the_module_gate(): void
    {
        $this->panel($this->owner, 'GET', '/owner/pos/banquets/payment-settings')->assertOk()
            ->assertJsonPath('data.version', 0)
            ->assertJsonPath('data.schedule_template.0.kind', 'deposit')
            ->assertJsonPath('data.cancellation_rules.1.min_days', 14)
            ->assertJsonPath('data.cancellation_rules.1.retained_percent', '30')
            ->assertJsonPath('data.reminder_days', [3, 1]);

        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/payment-settings', ['schedule_template' => [
            ['kind' => 'deposit', 'percent' => 60], ['kind' => 'interim', 'percent' => 50, 'days_before' => 10], ['kind' => 'final', 'days_before' => 0],
        ]])->assertUnprocessable()->assertJsonValidationErrors('schedule_template');
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/payment-settings', ['schedule_template' => [
            ['kind' => 'deposit', 'percent' => 60], ['kind' => 'interim', 'percent' => 20, 'days_before' => 10],
        ]])->assertUnprocessable()->assertJsonValidationErrors('schedule_template');
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/payment-settings', ['cancellation_rules' => [['min_days' => 30, 'retained_percent' => 0]]])
            ->assertUnprocessable()->assertJsonValidationErrors('cancellation_rules');

        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/payment-settings', ['version' => 0, 'cancellation_rules' => [
            ['min_days' => 0, 'retained_percent' => 50], ['min_days' => 60, 'retained_percent' => 10],
        ]])->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.cancellation_rules.0.min_days', 60);
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/payment-settings', ['version' => 0, 'reminder_days' => [2]])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertTrue(AuditLog::where('action', 'banquet.payment_settings_updated')->exists());

        // The new band applies to a cancellation quote (67 days: 10 %)
        $event = $this->event('10000000', '2027-01-20');
        $this->payRow($event, $this->rows($event)->first())->assertCreated();
        $this->panel($this->manager, 'GET', "/pos/banquets/{$event->uuid}/cancellation")->assertOk()
            ->assertJsonPath('data.rule_percent', '10')->assertJsonPath('data.retained', '300000.00');

        // A reader without banquets.manage cannot change the rules; the module gates saving, never reading
        $this->panel($this->cashier, 'GET', '/owner/pos/banquets/payment-settings')->assertForbidden();
        $settings = $this->branch->fresh()->pos_settings;
        $settings['modules']['banquet'] = false;
        $this->branch->forceFill(['pos_settings' => $settings])->save();
        app()->forgetScopedInstances();
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/payment-settings', ['reminder_days' => [2]])->assertForbidden()->assertJsonPath('code', 'module_disabled');
        $this->panel($this->owner, 'GET', '/owner/pos/banquets/payment-settings')->assertOk();
        $this->assertSame(1, BanquetPaymentSetting::count());
    }
}
