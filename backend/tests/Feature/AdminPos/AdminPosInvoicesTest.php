<?php

namespace Tests\Feature\AdminPos;

use App\Enums\DunningStage;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\SubscriptionDunningEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-24 Hisob-fakturalar: the invoices of every store, filters and counts (overdue by the Tashkent date, the latest
 * dunning step), the card, and marking paid / voiding behind finance.manage, a step-up and a reason.
 */
class AdminPosInvoicesTest extends TestCase
{
    use RefreshDatabase;
    use AdminPosFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    private function dunning(\App\Models\SubscriptionInvoice $invoice, DunningStage $stage): void
    {
        SubscriptionDunningEvent::create([
            'store_id' => $invoice->store_id,
            'subscription_invoice_id' => $invoice->id,
            'stage' => $stage,
            'notified' => true,
            'recipients' => 1,
            'occurred_at' => now(),
        ]);
    }

    public function test_the_list_shows_every_stores_invoices_with_overdue_days_and_the_latest_dunning_step(): void
    {
        $overdue = $this->invoice(['due_date' => now('Asia/Tashkent')->subDays(4)->toDateString()]);
        $this->dunning($overdue, DunningStage::REMINDER_1);
        $this->dunning($overdue, DunningStage::REMINDER_3);
        $open = $this->invoice(['due_date' => now('Asia/Tashkent')->addDays(3)->toDateString()], $this->foreignStore);
        $paid = $this->invoice(['status' => 'paid', 'paid_amount' => '249000.00', 'paid_at' => now(), 'due_date' => now()->subDays(20)->toDateString()]);
        $void = $this->invoice(['status' => 'void', 'voided_at' => now(), 'due_date' => now()->subDays(20)->toDateString()]);

        $token = $this->signIn($this->makeMember(PlatformRole::FINANCE));
        $response = $this->adminJson('GET', '/pos/invoices', $token)->assertOk();
        $this->assertSame([$void->uuid, $paid->uuid, $open->uuid, $overdue->uuid], array_column($response->json('data.data'), 'uuid'));
        $this->assertSame(['issued' => 2, 'overdue' => 1, 'paid' => 1, 'void' => 1, 'outstanding' => '498000.00'], $response->json('data.counts'));

        $row = collect($response->json('data.data'))->firstWhere('uuid', $overdue->uuid);
        $this->assertSame([true, 4, 'reminder_3', '3-kun eslatmasi', 'Lifecycle Burger'], [$row['overdue'], $row['overdue_days'], $row['dunning_stage'], $row['dunning_label'], $row['store']['name']]);
        $this->assertSame([false, 0, null], [collect($response->json('data.data'))->firstWhere('uuid', $open->uuid)['overdue'], collect($response->json('data.data'))->firstWhere('uuid', $open->uuid)['overdue_days'], collect($response->json('data.data'))->firstWhere('uuid', $open->uuid)['dunning_stage']]);

        $this->assertSame([$overdue->uuid], array_column($this->adminJson('GET', '/pos/invoices?status=overdue', $token)->assertOk()->json('data.data'), 'uuid'));
        $this->assertSame([$paid->uuid], array_column($this->adminJson('GET', '/pos/invoices?status=paid', $token)->json('data.data'), 'uuid'));
        $this->assertSame([$open->uuid], array_column($this->adminJson('GET', "/pos/invoices?store_id={$this->foreignStore->id}", $token)->json('data.data'), 'uuid'));
        $this->assertSame([$overdue->uuid], array_column($this->adminJson('GET', '/pos/invoices?dunning=reminder_3', $token)->json('data.data'), 'uuid'));
        $this->assertSame([], $this->adminJson('GET', '/pos/invoices?dunning=reminder_1', $token)->json('data.data'));
        $this->assertSame([$paid->uuid], array_column($this->adminJson('GET', '/pos/invoices?q=' . substr($paid->number, -6), $token)->json('data.data'), 'uuid'));
        $this->adminJson('GET', '/pos/invoices?status=bogus', $token)->assertStatus(422);

        $second = $this->adminJson('GET', '/pos/invoices?per_page=3&page=2', $token)->assertOk();
        $this->assertSame([2, 2, 3, 4], [$second->json('data.current_page'), $second->json('data.last_page'), $second->json('data.per_page'), $second->json('data.total')]);
    }

    public function test_the_card_has_the_lines_every_dunning_step_and_the_debt_split(): void
    {
        $invoice = $this->invoice(['due_date' => now('Asia/Tashkent')->subDays(8)->toDateString()]);
        $this->dunning($invoice, DunningStage::REMINDER_7);
        $this->dunning($invoice, DunningStage::LOCKED);
        $this->store->forceFill(['debt_balance' => '300000.00', 'subscription_debt' => '249000.00'])->save();

        $token = $this->signIn($this->makeMember(PlatformRole::SALES));
        $data = $this->adminJson('GET', "/pos/invoices/{$invoice->uuid}", $token)->assertOk()->json('data');
        $this->assertSame(['locked', 'Cheklandi'], [$data['invoice']['dunning_stage'], $data['invoice']['dunning_label']]);
        $this->assertSame(['reminder_7', 'locked'], array_column($data['invoice']['dunning'], 'stage'));
        $this->assertSame('Asosiy tarif', $data['invoice']['lines'][0]['label']);
        $this->assertSame('249000.00', $data['invoice']['outstanding']);
        $this->assertSame(['300000.00', '249000.00', '51000.00'], [$data['store']['debt_balance'], $data['store']['subscription_debt'], $data['store']['commission_debt']]);

        $this->adminJson('GET', '/pos/invoices/' . \Illuminate\Support\Str::uuid(), $token)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->adminJson('GET', '/pos/invoices/not-a-uuid', $token)->assertNotFound();
    }

    public function test_marking_paid_needs_finance_manage_a_step_up_and_a_reason_and_is_audited(): void
    {
        $invoice = $this->invoice(['due_date' => now('Asia/Tashkent')->subDays(2)->toDateString()]);
        $this->dunning($invoice, DunningStage::REMINDER_1);

        // Sales may read and manage subscriptions but not money
        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/paid", $this->confirmedSession($this->makeMember(PlatformRole::SALES)), ['reason' => "Naqd to'landi"])
            ->assertForbidden()->assertJsonPath('code', 'forbidden');

        $finance = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->signIn($finance);
        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/paid", $token, ['reason' => "Naqd to'landi"])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($finance, $token);
        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/paid", $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertSame('issued', $invoice->refresh()->status->value);

        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/paid", $token, ['reason' => "Naqd to'landi, kvitansiya 12"])->assertOk()
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.invoice.overdue', false)
            ->assertJsonPath('message', "Hisob-faktura to'langan deb belgilandi.");
        $this->assertSame('paid', $invoice->refresh()->status->value);
        $this->assertNotNull($invoice->paid_at);

        $audit = AuditLog::where('action', 'admin.pos.invoice_paid')->sole();
        $this->assertSame([$finance->id, $this->store->id, $invoice->id, ['status' => 'issued'], ['status' => 'paid']], [$audit->user_id, $audit->store_id, $audit->model_id, $audit->before, $audit->after]);
        $this->assertSame("Naqd to'landi, kvitansiya 12", $audit->payload_json['reason']);
        $this->assertSame(1, AuditLog::where('action', 'subscription_invoice.paid')->count());

        // Again: nothing changes, nothing more is recorded
        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/paid", $token, ['reason' => 'Qayta'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.invoice_paid')->count());
    }

    public function test_voiding_takes_the_invoice_out_and_a_voided_one_cannot_be_marked_paid(): void
    {
        $invoice = $this->invoice();
        $finance = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($finance);

        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/void", $token, ['reason' => 'x'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/void", $token, ['reason' => "Xato hisob-faktura"])->assertOk()
            ->assertJsonPath('data.invoice.status', 'void')->assertJsonPath('data.invoice.outstanding', '0.00');

        $invoice->refresh();
        $this->assertSame(['void', "Xato hisob-faktura", $finance->id], [$invoice->status->value, $invoice->void_reason, $invoice->voided_by]);
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.invoice_voided')->count());

        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/paid", $token, ['reason' => 'Kech bo\'ldi'])->assertStatus(422)->assertJsonPath('code', 'invoice_void');
        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/void", $token, ['reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.invoice_voided')->count());
        $this->adminJson('POST', '/pos/invoices/' . \Illuminate\Support\Str::uuid() . '/void', $token, ['reason' => 'Yo\'q'])->assertNotFound();
    }

    public function test_roles_without_billing_access_see_nothing(): void
    {
        $invoice = $this->invoice();

        foreach ([PlatformRole::SUPPORT, PlatformRole::OPERATOR] as $role) {
            $token = $this->signIn($this->makeMember($role));
            $this->adminJson('GET', '/pos/invoices', $token)->assertForbidden();
            $this->adminJson('GET', "/pos/invoices/{$invoice->uuid}", $token)->assertForbidden();
        }

        // The viewer reads, never changes
        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $this->adminJson('GET', '/pos/invoices', $this->signIn($viewer))->assertOk();
        $this->adminJson('POST', "/pos/invoices/{$invoice->uuid}/void", $this->confirmedSession($viewer), ['reason' => 'Sinov'])->assertForbidden();
        $this->assertSame('issued', $invoice->refresh()->status->value);

        $this->adminJson('GET', '/pos/invoices')->assertUnauthorized();
    }
}
