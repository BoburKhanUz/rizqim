<?php

namespace Tests\Feature\BillingV2;

use App\Enums\DeviceType;
use App\Enums\StoreStatus;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Services\Finance\Billing\PlanMigration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-21: `billing:migrate-plans [--dry-run]` — P1-23 plans to Start / Asosiy by the features in use, from the next
 * month, the first month never dearer than before; commission kept unless it was the old default; the debt split
 * (a store blocked only by invoices unblocked); a dry run reports exactly what the real run does and changes nothing;
 * running again changes nothing (docs/BILLING.md).
 */
class BillingMigratePlansTest extends TestCase
{
    use BillingV2Fixtures;
    use RefreshDatabase;

    private Store $pos;

    private Store $mixed;

    private Store $bare;

    private Store $commissionOnly;

    protected function setUp(): void
    {
        parent::setUp();

        TariffPlan::query()->update(['trial_days' => 0]);
        $this->pos = $this->billingStore('POS Cafe');
        $this->terminal($this->pos, '2026-08-01 10:00');
        $this->terminal($this->pos, '2026-08-01 11:00');
        $this->billing()->assignPlan($this->pos, TariffPlan::where('code', 'pos')->sole(), false, null, $this->at('2026-09-01 00:00'));

        $this->mixed = $this->billingStore('Mixed Cafe');
        $this->terminal($this->mixed, '2026-08-01 10:00');
        $this->terminal($this->mixed, '2026-08-01 10:00', null, DeviceType::KDS);
        $this->billing()->assignPlan($this->mixed, TariffPlan::where('code', 'mixed')->sole(), false, null, $this->at('2026-09-01 00:00'));

        $this->bare = $this->billingStore('Bare Cafe');
        $this->terminal($this->bare, '2026-08-01 10:00');

        $this->commissionOnly = $this->billingStore('Commission Cafe', ['commission_rate' => 12]);
        $this->billing()->assignPlan($this->commissionOnly, TariffPlan::where('code', 'commission')->sole(), false, null, $this->at('2026-09-01 00:00'));

        // The trials of the P1-23 plans are over; September is billed (POS Cafe owes 300 000 and is blocked by it)
        $this->billing()->run($this->at('2026-10-20 00:15'));
        $this->pos->forceFill(['credit_limit' => -100000, 'status' => StoreStatus::PAYMENT_REQUIRED->value])->save();
        // As the invoices of before Billing v2: not yet in the subscription part of the debt
        Store::query()->update(['subscription_debt' => 0]);
    }

    public function test_a_dry_run_reports_what_the_real_run_does_and_changes_nothing(): void
    {
        $before = $this->snapshot();
        $dry = app(PlanMigration::class)->run(true, null, $this->at('2026-10-20 12:00'));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, AuditLog::where('action', 'billing.migrated')->count());

        $real = app(PlanMigration::class)->run(false, null, $this->at('2026-10-20 12:00'));
        $this->assertSame($dry, $real);

        $rows = collect($real)->keyBy('store_id');
        $pos = $rows[$this->pos->id];
        $this->assertSame(['pos', 'start'], [$pos['plan_from'], $pos['plan_to']]);
        $this->assertSame(['300000.00', '208000.00', '208000.00'], [$pos['price_old'], $pos['price_new'], $pos['first_month']]);
        $this->assertSame(['0.00% (tarif)', '0.00% (hamma kanal)'], [$pos['commission_from'], $pos['commission_to']]);
        $this->assertSame(['plan_scheduled', 'commission_flat_kept', 'debt_split', 'unblocked'], $pos['actions']);

        $mixed = $rows[$this->mixed->id];
        $this->assertSame('asosiy', $mixed['plan_to']);   // it uses a KDS
        $this->assertSame(['200000.00', '249000.00', '200000.00'], [$mixed['price_old'], $mixed['price_new'], $mixed['first_month']]);
        $this->assertSame('5.00% (hamma kanal)', $mixed['commission_to']);

        $this->assertSame(['terminals_without_plan', 'commission_by_channel'], $rows[$this->bare->id]['actions']);
        $this->assertSame(['commission_plan_ended'], $rows[$this->commissionOnly->id]['actions']);
    }

    public function test_the_real_run_switches_next_month_keeps_the_first_price_and_is_idempotent(): void
    {
        app(PlanMigration::class)->run(false, null, $this->at('2026-10-20 12:00'));

        // Commission
        $this->assertSame('0.00', $this->pos->fresh()->commission_rate);
        $this->assertSame('5.00', $this->mixed->fresh()->commission_rate);
        $this->assertNull($this->bare->fresh()->commission_rate);
        $this->assertSame('12.00', $this->commissionOnly->fresh()->commission_rate);
        $this->assertSame(0, StoreSubscription::query()->current()->where('store_id', $this->commissionOnly->id)->count());

        // The debt split unblocked the store blocked only by its invoice
        $pos = $this->pos->fresh();
        $this->assertSame(['300000.00', '300000.00', StoreStatus::ACTIVE], [$pos->debt_balance, $pos->subscription_debt, $pos->status]);
        $this->assertSame(4, AuditLog::where('action', 'billing.migrated')->count());

        // Again: nothing changes (the store with terminals and no plan is still reported)
        $again = app(PlanMigration::class)->run(false, null, $this->at('2026-10-21 12:00'));
        $this->assertSame([[$this->bare->id, ['terminals_without_plan']]], array_map(fn ($row) => [$row['store_id'], $row['actions']], $again));
        $this->assertSame(4, AuditLog::where('action', 'billing.migrated')->count());

        // October at the old price; November on the new plan, never dearer than before; December the new price
        $this->billing()->run($this->at('2026-11-01 00:15'));
        $this->billing()->run($this->at('2026-12-01 00:15'));
        $this->billing()->run($this->at('2027-01-01 00:15'));
        $amounts = fn (Store $store) => SubscriptionInvoice::where('store_id', $store->id)->orderBy('period')->pluck('amount', 'period')->all();
        $this->assertSame(['2026-09' => '300000.00', '2026-10' => '300000.00', '2026-11' => '208000.00', '2026-12' => '208000.00'], $amounts($this->pos));
        $this->assertSame(['2026-09' => '200000.00', '2026-10' => '200000.00', '2026-11' => '200000.00', '2026-12' => '249000.00'], $amounts($this->mixed));
        $november = SubscriptionInvoice::where('store_id', $this->mixed->id)->where('period', '2026-11')->sole();
        $this->assertSame(['base', 'price_lock'], array_column($november->lines, 'type'));
        $this->assertSame('asosiy', $november->plan->code);
    }

    public function test_the_command_prints_the_report(): void
    {
        $this->artisan('billing:migrate-plans', ['--dry-run' => true])
            ->expectsOutputToContain('Sinov (--dry-run)')
            ->expectsOutputToContain('plan_scheduled: 2')
            ->assertSuccessful();

        $this->assertSame('pos', StoreSubscription::query()->current()->where('store_id', $this->pos->id)->sole()->plan->code);
        $this->assertNull(StoreSubscription::query()->current()->where('store_id', $this->pos->id)->sole()->next_tariff_plan_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return json_decode((string) json_encode([
            'stores' => \Illuminate\Support\Facades\DB::table('stores')->orderBy('id')->get(['id', 'commission_rate', 'debt_balance', 'subscription_debt', 'status'])->toArray(),
            'subscriptions' => \Illuminate\Support\Facades\DB::table('store_subscriptions')->orderBy('id')->get(['id', 'status', 'tariff_plan_id', 'next_tariff_plan_id', 'price_lock_plan_id'])->toArray(),
            'invoices' => \Illuminate\Support\Facades\DB::table('subscription_invoices')->orderBy('id')->get(['id', 'status', 'paid_amount'])->toArray(),
        ]), true);
    }
}
