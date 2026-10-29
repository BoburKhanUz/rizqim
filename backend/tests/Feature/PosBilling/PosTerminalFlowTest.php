<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Shifts\ShiftOwnerSummary;
use App\Enums\DeviceType;
use App\Models\AuditLog;
use App\Models\BankTerminalReport;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PosShift;
use App\Models\User;
use App\Services\Notifications\NotificationRouter;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P6-16 (D5): fiscal terminal flows on the fake fiscal layer: the card's last four digits on a payment, the bank
 * terminal's day total compared with the Z (manual at the close, an import matched by RRN, an entry before the close
 * worked out again at it), the owner's alert above the threshold, the branch's card mode and installation checklist,
 * the smart kassa flag of a till, the bootstrap section, and the `terminal_card_flow` module gate (which never stops
 * a payment or a close).
 */
class PosTerminalFlowTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    /** @var PosOwnerAlerts&object{sent: list<string>} */
    private PosOwnerAlerts $alerts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
        $this->alerts = new class(app(TelegramMessenger::class), app(NotificationRouter::class)) extends PosOwnerAlerts
        {
            /** @var list<string> */
            public array $sent = [];

            public function send(int $storeId, string $text): void
            {
                $this->sent[] = $text;
            }
        };
        $this->app->instance(PosOwnerAlerts::class, $this->alerts);
    }

    private function owner(string $method, string $uri, array $data = [], ?User $user = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user ?? $this->owner, 'sanctum')->json($method, '/api/v1' . $uri, $data);
    }

    private function moduleOff(?Branch $branch = null): void
    {
        $branch ??= $this->branch;
        $settings = is_array($branch->pos_settings) ? $branch->pos_settings : [];
        $settings['modules'] = ['terminal_card_flow' => false] + (array) ($settings['modules'] ?? []);
        $branch->forceFill(['pos_settings' => $settings])->save();
    }

    /**
     * A paid bill: card payments with their RRNs (and optional last four digits).
     *
     * @param  list<array{0: string, 1: string}>  $cards  [amount, rrn]
     */
    private function cardSale(array $cards): string
    {
        $total = $this->sumOf(array_column($cards, 0));
        $order = $this->posOrder([['Osh', '1', $total]]);
        $bill = (string) $this->bill($order)->assertCreated()->json('data.bills.0.uuid');
        $this->pay($bill, array_map(fn (array $card) => ['tender' => 'card', 'amount' => $card[0], 'card_type' => 'humo', 'transaction_id' => $card[1]], $cards))
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');

        return $bill;
    }

    private function close(PosShift $shift, array $extra = []): TestResponse
    {
        return $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => []] + $extra);
    }

    public function test_a_card_payment_keeps_the_rrn_and_the_optional_last_four_digits(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '120000']]);
        $bill = (string) $this->bill($order)->json('data.bills.0.uuid');

        $this->pay($bill, [['tender' => 'card', 'amount' => '50000', 'card_type' => 'uzcard', 'transaction_id' => '000123456789', 'card_last4' => '12ab']])
            ->assertStatus(422)->assertJsonPath('data.reason', 'card_last4_invalid');

        $this->pay($bill, [
            ['tender' => 'card', 'amount' => '50000', 'card_type' => 'uzcard', 'transaction_id' => '000123456789', 'card_last4' => '4417'],
            ['tender' => 'card', 'amount' => '70000', 'card_type' => 'humo', 'transaction_id' => '987'],
        ])->assertOk()
            ->assertJsonPath('data.bill.payments.0.card_last4', '4417')
            ->assertJsonPath('data.bill.payments.0.transaction_id', '000123456789')
            ->assertJsonPath('data.bill.payments.1.card_last4', null);

        $this->assertSame(['4417', null], Payment::orderBy('id')->pluck('card_last4')->all());
        // The receipt asked for carries both on its card payment
        $document = array_values($this->fiscal->documents)[0];
        $this->assertSame('card', $document->payments[0]->form->value);
        $this->assertSame('000123456789', $document->payments[0]->transactionId);
        $this->assertSame('4417', $document->payments[0]->cardLast4);
    }

    public function test_the_bank_total_given_at_the_close_is_compared_with_the_shift_card_money_and_shows_on_the_z(): void
    {
        $shift = $this->openShift();
        $this->cardSale([['80000', '111'], ['40000', '222']]);
        $order = $this->posOrder([['Choy', '1', '10000']]);
        $this->pay((string) $this->bill($order)->json('data.bills.0.uuid'), [['tender' => 'cash', 'amount' => '10000']])->assertOk();

        $this->close($shift, ['bank_terminal' => ['total' => '120000', 'count' => 2, 'terminal_ref' => 'HUMO-01']])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.bank_terminal.bank_total', '120000.00')
            ->assertJsonPath('data.bank_terminal.rizqim_total', '120000.00')
            ->assertJsonPath('data.bank_terminal.rizqim_count', 2)
            ->assertJsonPath('data.bank_terminal.rizqim_breakdown.payments', '120000.00')
            ->assertJsonPath('data.bank_terminal.difference', '0.00')
            ->assertJsonPath('data.bank_terminal.over_threshold', false)
            ->assertJsonPath('data.bank_terminal.z_number', 1)
            ->assertJsonPath('data.bank_terminal.terminal_ref', 'HUMO-01');
        $this->assertSame([], $this->alerts->sent);

        // The shift's detail keeps the row; the owner's Telegram Z names it
        $this->pos('GET', "/shifts/{$shift->uuid}")->assertOk()->assertJsonPath('data.bank_terminal.source', 'manual');
        $text = app(ShiftOwnerSummary::class)->text($shift->refresh(), (array) $shift->summary);
        $this->assertStringContainsString("Bank terminali: 120 000 so'm, Rizqim (karta): 120 000 so'm, farq 0 so'm", $text);
    }

    public function test_a_difference_above_the_threshold_alerts_the_owner_once(): void
    {
        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", ['difference_threshold' => '1000'])->assertOk();
        $shift = $this->openShift();
        $this->cardSale([['100000', '7']]);

        // Within the threshold: no alert
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '100500'])->assertOk()
            ->assertJsonPath('data.difference', '500.00')
            ->assertJsonPath('data.over_threshold', false)
            ->assertJsonPath('data.threshold', '1000.00');
        $this->assertSame([], $this->alerts->sent);

        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '95000', 'note' => 'Terminal cheki'])->assertOk()
            ->assertJsonPath('data.difference', '-5000.00')
            ->assertJsonPath('data.over_threshold', true);
        $this->assertCount(1, $this->alerts->sent);
        $this->assertStringContainsString("farq -5 000 so'm", $this->alerts->sent[0]);
        $this->assertStringContainsString("Bank terminali: 95 000 so'm, Rizqim (karta): 100 000 so'm", $this->alerts->sent[0]);

        // The same difference again is not a new alert; the close does not repeat it either
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '95000'])->assertOk();
        $this->close($shift)->assertOk()->assertJsonPath('data.bank_terminal.over_threshold', true);
        $this->assertCount(1, $this->alerts->sent);
        $this->assertSame(3, AuditLog::where('action', 'pos.bank_terminal_report')->count());
    }

    public function test_an_entry_before_the_close_is_worked_out_again_with_the_whole_shift(): void
    {
        $shift = $this->openShift();
        $this->cardSale([['50000', '1']]);
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '80000'])->assertOk()
            ->assertJsonPath('data.over_threshold', true);
        $this->assertCount(1, $this->alerts->sent);

        // A later card payment closes the gap: at the Z the report agrees again
        $this->cardSale([['30000', '2']]);
        $this->close($shift)->assertOk()
            ->assertJsonPath('data.bank_terminal.rizqim_total', '80000.00')
            ->assertJsonPath('data.bank_terminal.difference', '0.00')
            ->assertJsonPath('data.bank_terminal.over_threshold', false);
        $this->assertCount(1, $this->alerts->sent);
    }

    public function test_an_imported_bank_report_is_matched_by_rrn(): void
    {
        $shift = $this->openShift();
        $this->cardSale([['60000', '000000123'], ['40000', '456']]);

        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", [
            'source' => 'import',
            'transactions' => [['rrn' => '123', 'amount' => '60000'], ['rrn' => '999', 'amount' => '45000']],
        ])->assertOk()
            ->assertJsonPath('data.source', 'import')
            ->assertJsonPath('data.bank_total', '105000.00')
            ->assertJsonPath('data.bank_count', 2)
            ->assertJsonPath('data.difference', '5000.00')
            ->assertJsonPath('data.transactions.0.matched', true)
            ->assertJsonPath('data.transactions.1.matched', false)
            ->assertJsonPath('data.unmatched.bank.0.rrn', '999')
            ->assertJsonPath('data.unmatched.rizqim.0.rrn', '456')
            ->assertJsonPath('data.unmatched.rizqim.0.kind', 'payment');

        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['source' => 'import', 'transactions' => []])
            ->assertStatus(422)->assertJsonPath('data.reason', 'bank_transactions_required');
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['source' => 'manual'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'bank_total_required');
        // Not a smart kassa: the provider has no report for it
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['source' => 'provider'])
            ->assertStatus(409)->assertJsonPath('data.reason', 'provider_report_unavailable');
    }

    public function test_card_refunds_come_off_the_shift_card_money(): void
    {
        $shift = $this->openShift();
        $this->cardSale([['90000', '31']]);
        $payment = Payment::firstOrFail();
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '20000', 'reason' => 'Xato', 'shift_uuid' => $shift->uuid])->assertCreated();

        $this->posAs($this->manager, 'PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '70000'])->assertOk()
            ->assertJsonPath('data.rizqim_total', '70000.00')
            ->assertJsonPath('data.rizqim_breakdown.refunds', '20000.00')
            ->assertJsonPath('data.difference', '0.00');
    }

    public function test_without_the_module_nothing_is_asked_and_nothing_stops_a_payment_or_the_close(): void
    {
        $this->moduleOff();
        $shift = $this->openShift();
        $this->cardSale([['50000', '5']]);

        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '50000'])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->bearerJson('GET', '/api/v1/pos/bootstrap', $this->cashierToken)->assertOk()->assertJsonPath('data.terminal_flow', null);

        $this->close($shift, ['bank_terminal' => ['total' => '1']])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.bank_terminal', null);
        $this->assertSame(0, BankTerminalReport::count());
        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", ['card_mode' => 'terminal_only'])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_the_owner_sets_the_card_mode_and_ticks_the_checklist(): void
    {
        $this->owner('GET', '/owner/fiscal/terminal-flow')->assertOk()
            ->assertJsonPath('data.fiscal.enabled', false)
            ->assertJsonPath('data.branches.0.module_enabled', true)
            ->assertJsonPath('data.branches.0.setup.card_mode', null)
            ->assertJsonPath('data.branches.0.setup.checklist_complete', false)
            ->assertJsonPath('data.branches.0.setup.difference_threshold', '0.00')
            ->assertJsonPath('data.branches.0.setup.ask_bank_total', true)
            ->assertJsonPath('data.branches.0.setup.checklist.0.key', 'old_kassa_unused')
            ->assertJsonPath('data.branches.0.devices.0.uuid', $this->till['device']->uuid)
            ->assertJsonCount(3, 'data.checklist_items');

        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", [
            'card_mode' => 'terminal_only',
            'checklist' => ['old_kassa_unused' => true, 'bank_terminal_mode' => true],
            'difference_threshold' => '2000',
            'ask_bank_total' => false,
        ])->assertOk()
            ->assertJsonPath('data.card_mode', 'terminal_only')
            ->assertJsonPath('data.checklist.0.done', true)
            ->assertJsonPath('data.checklist.0.done_by.id', $this->owner->id)
            ->assertJsonPath('data.checklist.2.done', false)
            ->assertJsonPath('data.checklist_complete', false)
            ->assertJsonPath('data.difference_threshold', '2000.00')
            ->assertJsonPath('data.ask_bank_total', false);

        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", ['checklist' => ['scanner_2d' => true, 'old_kassa_unused' => false]])->assertOk()
            ->assertJsonPath('data.checklist.0.done', false)
            ->assertJsonPath('data.checklist.0.done_by', null)
            ->assertJsonPath('data.checklist.2.done', true);
        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", ['checklist' => ['fax' => true]])->assertStatus(422)->assertJsonValidationErrors('checklist.fax');
        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", ['card_mode' => 'cheque'])->assertStatus(422)->assertJsonValidationErrors('card_mode');
        $this->assertSame(2, AuditLog::where('action', 'settings.terminal_flow_changed')->count());

        // The till reads it at start-up
        $this->bearerJson('GET', '/api/v1/pos/bootstrap', $this->cashierToken)->assertOk()
            ->assertJsonPath('data.terminal_flow.card_mode', 'terminal_only')
            ->assertJsonPath('data.terminal_flow.ask_bank_total', false)
            ->assertJsonPath('data.terminal_flow.difference_threshold', '2000.00')
            ->assertJsonPath('data.terminal_flow.smart_kassa', false);

        // A manager (no settings.manage) and a terminal PIN session cannot change it; another store's branch is not found
        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", ['card_mode' => 'none'], $this->manager)->assertForbidden();
        $this->bearerJson('PUT', "/api/v1/owner/branches/{$this->branch->id}/terminal-flow", $this->cashierToken, ['card_mode' => 'none'])->assertForbidden();
        $other = $this->makeBranch($this->makeStore('Boshqa'), 'Boshqa filial');
        $this->owner('PUT', "/owner/branches/{$other->id}/terminal-flow", ['card_mode' => 'none'])->assertNotFound();
    }

    public function test_the_owner_marks_a_till_as_a_smart_kassa(): void
    {
        $device = $this->till['device'];
        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['smart_kassa' => true, 'fiscal_kassa_id' => 'RHMT-0042'])->assertOk()
            ->assertJsonPath('data.smart_kassa', true)
            ->assertJsonPath('data.fiscal_kassa_id', 'RHMT-0042')
            ->assertJsonPath('data.kassa_status.registered', true)
            ->assertJsonPath('data.kassa_status.kassa_id', 'RHMT-0042');
        $this->assertTrue((bool) $device->refresh()->smart_kassa);
        $this->assertSame(1, AuditLog::where('action', 'devices.smart_kassa_changed')->count());

        $this->bearerJson('GET', '/api/v1/pos/bootstrap', $this->cashierToken)->assertOk()
            ->assertJsonPath('data.terminal_flow.smart_kassa', true)
            ->assertJsonPath('data.terminal_flow.fiscal_kassa_id', 'RHMT-0042');

        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['fiscal_kassa_id' => 'bad id!'])->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS)['device'];
        $this->owner('PUT', "/owner/fiscal/devices/{$kds->uuid}", ['smart_kassa' => true])->assertNotFound();

        $this->moduleOff();
        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['smart_kassa' => false])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_the_panel_lists_the_reports_and_corrects_one_after_the_z(): void
    {
        $shift = $this->openShift();
        $this->cardSale([['100000', '9']]);
        $this->close($shift)->assertOk()->assertJsonPath('data.bank_terminal', null);

        $this->owner('PUT', "/owner/fiscal/bank-reports/{$shift->uuid}", ['bank_total' => '90000'], $this->manager)->assertOk()
            ->assertJsonPath('data.z_number', 1)
            ->assertJsonPath('data.over_threshold', true)
            ->assertJsonPath('data.entered_by.id', $this->manager->id);
        $this->assertCount(1, $this->alerts->sent);
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$shift->uuid}", ['bank_total' => '100000'])->assertOk()
            ->assertJsonPath('data.over_threshold', false);

        $other = $this->openShift('0', $this->pairTerminal($this->owner, $this->branch)['device']);
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$other->uuid}", ['bank_total' => '5000'])->assertOk()->assertJsonPath('data.over_threshold', true);

        $this->owner('GET', '/owner/fiscal/bank-reports')->assertOk()->assertJsonCount(2, 'data');
        $this->owner('GET', '/owner/fiscal/bank-reports?over_threshold=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shift_uuid', $other->uuid);
        $this->owner('GET', '/owner/fiscal/bank-reports', [], $this->cashier)->assertForbidden();
        $this->owner('PUT', '/owner/fiscal/bank-reports/' . \Illuminate\Support\Str::uuid(), ['bank_total' => '1'])->assertNotFound();
    }
}
