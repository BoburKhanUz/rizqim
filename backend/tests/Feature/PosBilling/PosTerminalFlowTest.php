<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Shifts\ShiftOwnerSummary;
use App\Enums\DeviceType;
use App\Models\AuditLog;
use App\Models\BankTerminalReport;
use App\Models\Branch;
use App\Models\Device;
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
    private function cardSale(array $cards, ?string $token = null, ?Device $device = null): string
    {
        $total = $this->sumOf(array_column($cards, 0));
        $order = $this->posOrder([['Osh', '1', $total]], $device !== null ? ['device_id' => $device->id] : []);
        $bill = (string) $this->bill($order, token: $token)->assertCreated()->json('data.bills.0.uuid');
        $this->pay($bill, array_map(fn (array $card) => ['tender' => 'card', 'amount' => $card[0], 'card_type' => 'humo', 'transaction_id' => $card[1]], $cards), token: $token)
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');

        return $bill;
    }

    private function close(PosShift $shift, array $extra = [], ?string $token = null): TestResponse
    {
        return $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => []] + $extra, $token);
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

        // Review P6 S#5: before the Z the comparison is provisional: the till sees what it typed only, nobody is alerted
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '100500'])->assertOk()
            ->assertJsonPath('data.final', false)
            ->assertJsonPath('data.bank_total', '100500.00')
            ->assertJsonPath('data.difference', null)
            ->assertJsonPath('data.rizqim_total', null)
            ->assertJsonPath('data.over_threshold', null)
            ->assertJsonPath('data.threshold', null);
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '95000', 'note' => 'Terminal cheki'])->assertOk()
            ->assertJsonPath('data.difference', null);
        $this->assertSame([], $this->alerts->sent);
        $this->assertSame('-5000.00', (string) BankTerminalReport::sole()->difference); // worked out, not shown

        // At the Z: final, over the threshold, the owner told once
        $this->close($shift)->assertOk()
            ->assertJsonPath('data.bank_terminal.final', true)
            ->assertJsonPath('data.bank_terminal.difference', '-5000.00')
            ->assertJsonPath('data.bank_terminal.threshold', '1000.00')
            ->assertJsonPath('data.bank_terminal.over_threshold', true)
            ->assertJsonPath('data.bank_terminal.history.0.bank_total', '100500.00');
        $this->assertCount(1, $this->alerts->sent);
        $this->assertStringContainsString("farq -5 000 so'm", $this->alerts->sent[0]);
        $this->assertStringContainsString("Bank terminali: 95 000 so'm, Rizqim (karta): 100 000 so'm", $this->alerts->sent[0]);
        $this->assertSame(2, AuditLog::where('action', 'pos.bank_terminal_report')->count());

        // The till cannot rewrite it after its Z (S#5); the owner (finance.manage) corrects it, and the alert stays
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '100000'])->assertForbidden()->assertJsonPath('data.reason', 'bank_report_locked');
        $this->posAs($this->manager, 'PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '100000'])->assertForbidden()->assertJsonPath('data.reason', 'bank_report_locked');
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$shift->uuid}", ['bank_total' => '100000'], $this->manager)->assertForbidden()->assertJsonPath('data.reason', 'bank_report_locked');
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$shift->uuid}", ['bank_total' => '100000', 'note' => 'Chek qayta sanaldi'])->assertOk()
            ->assertJsonPath('data.difference', '0.00')
            ->assertJsonPath('data.over_threshold', true)
            ->assertJsonPath('data.history.1.bank_total', '95000.00')
            ->assertJsonPath('data.history.1.over_threshold', true);
        $this->assertNotNull(BankTerminalReport::sole()->alerted_at);
        $this->owner('GET', '/owner/fiscal/bank-reports?over_threshold=1')->assertOk()->assertJsonCount(1, 'data');
        $this->assertCount(1, $this->alerts->sent);
    }

    public function test_an_entry_before_the_close_is_worked_out_again_with_the_whole_shift(): void
    {
        $shift = $this->openShift();
        $this->cardSale([['50000', '1']]);
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '80000'])->assertOk()
            ->assertJsonPath('data.final', false);
        $this->assertTrue((bool) BankTerminalReport::sole()->over_threshold); // provisional: nobody is told
        $this->assertSame([], $this->alerts->sent);

        // A later card payment closes the gap: at the Z the report agrees, and there never was an alert
        $this->cardSale([['30000', '2']]);
        $this->close($shift)->assertOk()
            ->assertJsonPath('data.bank_terminal.rizqim_total', '80000.00')
            ->assertJsonPath('data.bank_terminal.difference', '0.00')
            ->assertJsonPath('data.bank_terminal.over_threshold', false);
        $this->assertSame([], $this->alerts->sent);
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
            ->assertJsonPath('data.transactions.0.matched', null) // the till: once final
            ->assertJsonPath('data.unmatched', null);
        // The panel (finance.view) sees the matching at once
        $this->owner('GET', '/owner/fiscal/bank-reports')->assertOk()
            ->assertJsonPath('data.0.final', false)
            ->assertJsonPath('data.0.difference', '5000.00')
            ->assertJsonPath('data.0.transactions.0.matched', true)
            ->assertJsonPath('data.0.transactions.1.matched', false)
            ->assertJsonPath('data.0.unmatched.bank.0.rrn', '999')
            ->assertJsonPath('data.0.unmatched.rizqim.0.rrn', '456')
            ->assertJsonPath('data.0.unmatched.rizqim.0.kind', 'payment');

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

        $this->posAs($this->manager, 'PUT', "/shifts/{$shift->uuid}/bank-report", ['bank_total' => '70000'])->assertOk();
        $this->close($shift)->assertOk()
            ->assertJsonPath('data.bank_terminal.rizqim_total', '70000.00')
            ->assertJsonPath('data.bank_terminal.rizqim_breakdown.refunds', '20000.00')
            ->assertJsonPath('data.bank_terminal.difference', '0.00');
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
            ->assertJsonPath('data.terminal_flow.shared_terminal', false)
            ->assertJsonPath('data.terminal_flow.smart_kassa', false)
            ->assertJsonMissingPath('data.terminal_flow.difference_threshold'); // review P6 S#5: never sent to the till

        // A manager (no settings.manage) and a terminal PIN session cannot change it; another store's branch is not found
        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", ['card_mode' => 'none'], $this->manager)->assertForbidden();
        $this->bearerJson('PUT', "/api/v1/owner/branches/{$this->branch->id}/terminal-flow", $this->cashierToken, ['card_mode' => 'none'])->assertForbidden();
        $other = $this->makeBranch($this->makeStore('Boshqa'), 'Boshqa filial');
        $this->owner('PUT', "/owner/branches/{$other->id}/terminal-flow", ['card_mode' => 'none'])->assertNotFound();
    }

    public function test_the_owner_marks_a_till_as_a_smart_kassa(): void
    {
        $device = $this->till['device'];
        $this->store->forceFill(['tin' => '301234567'])->save();
        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['smart_kassa' => true, 'fiscal_kassa_id' => ' 301234567-0042 '])->assertOk()
            ->assertJsonPath('data.smart_kassa', true)
            ->assertJsonPath('data.fiscal_kassa_id', '301234567-0042')
            ->assertJsonPath('data.kassa_status.registered', true)
            ->assertJsonPath('data.kassa_status.kassa_id', '301234567-0042');
        $this->assertTrue((bool) $device->refresh()->smart_kassa);
        $this->assertSame(1, AuditLog::where('action', 'devices.smart_kassa_changed')->count());

        $this->bearerJson('GET', '/api/v1/pos/bootstrap', $this->cashierToken)->assertOk()
            ->assertJsonPath('data.terminal_flow.smart_kassa', true)
            ->assertJsonPath('data.terminal_flow.fiscal_kassa_id', '301234567-0042');

        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['fiscal_kassa_id' => 'bad id!'])->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS)['device'];
        $this->owner('PUT', "/owner/fiscal/devices/{$kds->uuid}", ['smart_kassa' => true])->assertNotFound();

        $this->moduleOff();
        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['smart_kassa' => false])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_a_smart_kassa_id_is_the_store_s_and_belongs_to_one_till(): void
    {
        // Review P6 S#4
        $device = $this->till['device'];
        $second = $this->pairTerminal($this->owner, $this->branch)['device'];

        // No STIR yet: the id cannot be checked
        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['smart_kassa' => true, 'fiscal_kassa_id' => '301234567-0042'])
            ->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        $this->store->forceFill(['tin' => '301234567'])->save();

        // Another taxpayer's kassa: refused, and the sandbox says so
        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['smart_kassa' => true, 'fiscal_kassa_id' => '309999999-0001'])
            ->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['smart_kassa' => true, 'fiscal_kassa_id' => 'RHMT-0042'])
            ->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        // ...unless the platform's sandbox table lists it under the store's STIR
        config(['fiscal.sandbox.kassas' => ['RHMT-0042' => '301234567']]);
        $this->owner('PUT', "/owner/fiscal/devices/{$device->uuid}", ['smart_kassa' => true, 'fiscal_kassa_id' => 'rhmt-0042'])->assertOk()
            ->assertJsonPath('data.fiscal_kassa_id', 'RHMT-0042');

        // Format: 6–32 latin letters, digits and dashes
        foreach (['ab12', str_repeat('A', 33), '-30123456', '3012:3456', '301234567_1'] as $bad) {
            $this->owner('PUT', "/owner/fiscal/devices/{$second->uuid}", ['fiscal_kassa_id' => $bad])->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        }

        // One till per id: in the store, and among the active smart kassas of every store
        $this->owner('PUT', "/owner/fiscal/devices/{$second->uuid}", ['smart_kassa' => true, 'fiscal_kassa_id' => 'RHMT-0042'])
            ->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        $this->owner('PUT', "/owner/fiscal/devices/{$second->uuid}", ['fiscal_kassa_id' => 'RHMT-0042'])
            ->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        $otherStore = $this->makeStore('Boshqa');
        $foreign = $this->pairTerminal($this->makeStaff(\App\Enums\UserRole::OWNER, null, $otherStore), $this->makeBranch($otherStore, 'Boshqa filial'))['device'];
        $foreign->forceFill(['smart_kassa' => true, 'fiscal_kassa_id' => '301234567-0099'])->save();
        $this->owner('PUT', "/owner/fiscal/devices/{$second->uuid}", ['smart_kassa' => true, 'fiscal_kassa_id' => '301234567-0099'])
            ->assertStatus(422)->assertJsonValidationErrors('fiscal_kassa_id');
        // The database backs it
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $second->forceFill(['smart_kassa' => true, 'fiscal_kassa_id' => $foreign->fiscal_kassa_id])->save();
    }

    public function test_the_migration_keeps_the_oldest_of_duplicate_kassa_ids(): void
    {
        $migration = require database_path('migrations/2026_11_11_200100_add_unique_fiscal_kassa_id_to_devices_table.php');
        $migration->down();
        $first = $this->till['device'];
        $second = $this->pairTerminal($this->owner, $this->branch)['device'];
        $first->forceFill(['smart_kassa' => true, 'fiscal_kassa_id' => ' rhmt-1 '])->save();
        $second->forceFill(['smart_kassa' => true, 'fiscal_kassa_id' => 'RHMT-1'])->save();

        $migration->up();
        $this->assertSame('RHMT-1', $first->refresh()->fiscal_kassa_id);
        $this->assertNull($second->refresh()->fiscal_kassa_id);
    }

    public function test_a_shared_terminal_compares_the_day_total_with_every_shift_of_the_day(): void
    {
        // Review P6 C#M2: two tills on one bank terminal, settled once at the end of the day
        $this->owner('PUT', "/owner/branches/{$this->branch->id}/terminal-flow", ['shared_terminal' => true, 'difference_threshold' => '1000'])->assertOk()
            ->assertJsonPath('data.shared_terminal', true);
        $this->bearerJson('GET', '/api/v1/pos/bootstrap', $this->cashierToken)->assertOk()->assertJsonPath('data.terminal_flow.shared_terminal', true);

        $till2 = $this->pairTerminal($this->owner, $this->branch);
        $cashier2 = $this->makeStaff(\App\Enums\UserRole::CASHIER, $this->branch);
        $token2 = $this->pinSession($till2, $cashier2, '6402');
        $first = $this->openShift('0');
        $second = $this->openShift('0', $till2['device'], $cashier2);
        $this->cardSale([['70000', '11']]);
        $this->cardSale([['50000', '21']], $token2, $till2['device']);

        // The first Z: the terminal shows the whole day so far. Before, this alerted (bank 120 000 vs the shift's 70 000)
        $this->close($first, ['bank_terminal' => ['total' => '120000']])->assertOk()
            ->assertJsonPath('data.bank_terminal.shared', true)
            ->assertJsonPath('data.bank_terminal.final', false)
            ->assertJsonPath('data.bank_terminal.difference', null)
            ->assertJsonCount(2, 'data.bank_terminal.shifts');
        $this->assertSame([], $this->alerts->sent);

        // More cards on the second till; its Z enters the day's final total: one report for both shifts
        $this->cardSale([['30000', '22']], $token2, $till2['device']);
        $this->close($second, ['bank_terminal' => ['total' => '150000']], $token2)->assertOk()
            ->assertJsonPath('data.bank_terminal.final', true)
            ->assertJsonPath('data.bank_terminal.bank_total', '150000.00')
            ->assertJsonPath('data.bank_terminal.rizqim_total', '150000.00')
            ->assertJsonPath('data.bank_terminal.rizqim_count', 3)
            ->assertJsonPath('data.bank_terminal.difference', '0.00')
            ->assertJsonPath('data.bank_terminal.over_threshold', false)
            ->assertJsonPath('data.bank_terminal.shifts.0.uuid', $first->uuid)
            ->assertJsonPath('data.bank_terminal.shifts.1.uuid', $second->uuid);
        $this->assertSame(1, BankTerminalReport::count());
        $this->assertSame([], $this->alerts->sent);
        // Both Z reports show the same day's report
        $this->pos('GET', "/shifts/{$first->uuid}")->assertOk()->assertJsonPath('data.bank_terminal.difference', '0.00');

        // A short day total, corrected by the owner: compared with every shift, one alert
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$first->uuid}", ['bank_total' => '140000'])->assertOk()
            ->assertJsonPath('data.difference', '-10000.00')
            ->assertJsonPath('data.over_threshold', true);
        $this->assertCount(1, $this->alerts->sent);
        $this->assertStringContainsString('Umumiy terminal (2 ta smena): Z #1, Z #2', $this->alerts->sent[0]);

        // A third shift the same day reopens the comparison: the cashier sees no figures, the owner (finance.manage) does
        $third = $this->openShift('0');
        $this->pos('GET', "/shifts/{$third->uuid}")->assertOk()
            ->assertJsonPath('data.bank_terminal.final', false)
            ->assertJsonPath('data.bank_terminal.difference', null);
        $this->posAs($this->owner, 'GET', "/shifts/{$third->uuid}")->assertOk()
            ->assertJsonPath('data.bank_terminal.difference', '-10000.00')
            ->assertJsonPath('data.bank_terminal.over_threshold', true)
            ->assertJsonCount(3, 'data.bank_terminal.shifts');
    }

    public function test_without_a_shared_terminal_each_shift_has_its_own_report(): void
    {
        $till2 = $this->pairTerminal($this->owner, $this->branch);
        $cashier2 = $this->makeStaff(\App\Enums\UserRole::CASHIER, $this->branch);
        $token2 = $this->pinSession($till2, $cashier2, '6402');
        $first = $this->openShift('0');
        $second = $this->openShift('0', $till2['device'], $cashier2);
        $this->cardSale([['70000', '11']]);
        $this->cardSale([['50000', '21']], $token2, $till2['device']);

        $this->close($first, ['bank_terminal' => ['total' => '70000']])->assertOk()
            ->assertJsonPath('data.bank_terminal.shared', false)
            ->assertJsonPath('data.bank_terminal.final', true)
            ->assertJsonPath('data.bank_terminal.difference', '0.00')
            ->assertJsonCount(1, 'data.bank_terminal.shifts');
        $this->close($second, ['bank_terminal' => ['total' => '50000']], $token2)->assertOk()
            ->assertJsonPath('data.bank_terminal.rizqim_total', '50000.00');
        $this->assertSame(2, BankTerminalReport::count());
    }

    public function test_the_panel_lists_the_reports_and_corrects_one_after_the_z(): void
    {
        $shift = $this->openShift();
        $this->cardSale([['100000', '9']]);
        $this->close($shift)->assertOk()->assertJsonPath('data.bank_terminal', null);

        // After the Z only finance.manage enters or corrects it (review P6 S#5)
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$shift->uuid}", ['bank_total' => '90000'], $this->manager)->assertForbidden()
            ->assertJsonPath('data.reason', 'bank_report_locked');
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$shift->uuid}", ['bank_total' => '90000'])->assertOk()
            ->assertJsonPath('data.z_number', 1)
            ->assertJsonPath('data.final', true)
            ->assertJsonPath('data.over_threshold', true)
            ->assertJsonPath('data.entered_by.id', $this->owner->id);
        $this->assertCount(1, $this->alerts->sent);
        // A correction keeps the alert and the earlier entry
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$shift->uuid}", ['bank_total' => '100000'])->assertOk()
            ->assertJsonPath('data.difference', '0.00')
            ->assertJsonPath('data.over_threshold', true)
            ->assertJsonPath('data.history.0.bank_total', '90000.00');

        // An open shift: a manager (pos.close_shift) may enter it, and sees no comparison yet
        $other = $this->openShift('0', $this->pairTerminal($this->owner, $this->branch)['device']);
        $this->owner('PUT', "/owner/fiscal/bank-reports/{$other->uuid}", ['bank_total' => '5000'], $this->manager)->assertOk()
            ->assertJsonPath('data.final', false)
            ->assertJsonPath('data.difference', null);
        $this->assertCount(1, $this->alerts->sent);

        $this->owner('GET', '/owner/fiscal/bank-reports')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.difference', '5000.00');
        $this->owner('GET', '/owner/fiscal/bank-reports?over_threshold=1')->assertOk()->assertJsonCount(2, 'data');
        $this->owner('GET', '/owner/fiscal/bank-reports', [], $this->cashier)->assertForbidden();
        $this->owner('PUT', '/owner/fiscal/bank-reports/' . \Illuminate\Support\Str::uuid(), ['bank_total' => '1'])->assertNotFound();
    }
}
