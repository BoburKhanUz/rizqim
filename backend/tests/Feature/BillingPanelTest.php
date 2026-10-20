<?php

namespace Tests\Feature;

use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\UserRole;
use App\Filament\Pages\SubscriptionBilling;
use App\Filament\Superadmin\Resources\StoreSubscriptions\Pages\ListStoreSubscriptions;
use App\Filament\Superadmin\Resources\SubscriptionInvoices\Pages\ListSubscriptionInvoices;
use App\Filament\Superadmin\Resources\TariffPlans\Pages\CreateTariffPlan;
use App\Models\StoreSubscription;
use App\Models\StoreTransaction;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Models\User;
use App\Services\Finance\SubscriptionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1-23: the superadmin screens (plans, assigning a plan, voiding an invoice) and the store panel's read-only page.
 */
class BillingPanelTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use BillingFixtures;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->admin = User::create(['name' => 'Admin', 'phone' => '+998901110007', 'password' => bcrypt('x'), 'role' => UserRole::SUPER_ADMIN->value]);
    }

    private function asSuperAdmin(): void
    {
        Filament::setCurrentPanel('superadmin');
        $this->actingAs($this->admin);
    }

    public function test_superadmin_creates_a_plan_with_exact_prices(): void
    {
        $this->asSuperAdmin();

        Livewire::test(CreateTariffPlan::class)
            ->fillForm([
                'name' => 'Premium',
                'code' => 'premium',
                'is_active' => true,
                'commission_percent' => '3.5',
                'monthly_base_fee' => '250000.10',
                'monthly_fee_per_terminal' => '99999.99',
                'included_terminals' => 2,
                'trial_days' => 7,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = TariffPlan::where('code', 'premium')->sole();
        $this->assertSame('3.50', $plan->commission_percent);
        $this->assertSame('250000.10', $plan->monthly_base_fee);
        $this->assertSame('99999.99', $plan->monthly_fee_per_terminal);

        // Codes are unique
        Livewire::test(CreateTariffPlan::class)
            ->fillForm(['name' => 'Again', 'code' => 'premium', 'included_terminals' => 0, 'trial_days' => 0])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);
    }

    public function test_superadmin_assigns_and_changes_a_plan(): void
    {
        $this->asSuperAdmin();
        $mixed = TariffPlan::where('code', 'mixed')->sole();
        $pos = TariffPlan::where('code', 'pos')->sole();

        Livewire::test(ListStoreSubscriptions::class)
            ->callTableAction('assign', data: ['store_id' => $this->store->id, 'tariff_plan_id' => $mixed->id, 'mode' => 'next'])
            ->assertHasNoTableActionErrors();

        $subscription = StoreSubscription::where('store_id', $this->store->id)->sole();
        $this->assertSame($mixed->id, $subscription->tariff_plan_id);
        $this->assertSame($this->admin->id, $subscription->created_by);
        $this->assertSame('5.00', $this->store->fresh()->commissionRate());

        Livewire::test(ListStoreSubscriptions::class)
            ->callTableAction('change', $subscription, data: ['tariff_plan_id' => $pos->id, 'mode' => 'now'])
            ->assertHasNoTableActionErrors();

        $this->assertSame($pos->id, StoreSubscription::query()->current()->where('store_id', $this->store->id)->sole()->tariff_plan_id);
        $this->assertSame('0.00', $this->store->fresh()->commissionRate());
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.plan_changed', 'store_id' => $this->store->id, 'user_id' => $this->admin->id]);
    }

    public function test_superadmin_voids_an_invoice_and_the_ledger_is_reversed(): void
    {
        $billing = app(SubscriptionService::class);
        $billing->assignPlan($this->store, $this->plan(['monthly_base_fee' => 90000]), false, null, $this->tashkent('2026-10-01 00:00'));
        $billing->run($this->tashkent('2026-11-01 00:15'));
        $invoice = SubscriptionInvoice::sole();

        $this->asSuperAdmin();
        Livewire::test(ListSubscriptionInvoices::class)
            ->assertCanSeeTableRecords([$invoice])
            ->callTableAction('void', $invoice, data: ['reason' => 'Chegirma'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(SubscriptionInvoiceStatus::VOID, $invoice->fresh()->status);
        $this->assertSame(['90000.00', '-90000.00'], StoreTransaction::orderBy('id')->pluck('amount')->all());
        $this->assertSame('0.00', $this->store->fresh()->debt_balance);
    }

    public function test_the_store_panel_page_is_read_only_for_finance_view_and_scoped_to_the_tenant(): void
    {
        $billing = app(SubscriptionService::class);
        $other = $this->makeStore('Other Store');
        $plan = $this->plan(['monthly_base_fee' => 60000]);
        $billing->assignPlan($this->store, $plan, false, null, $this->tashkent('2026-10-01 00:00'));
        $billing->assignPlan($other, $plan, false, null, $this->tashkent('2026-10-01 00:00'));
        $billing->run($this->tashkent('2026-11-01 00:15'));
        [$mine, $theirs] = [SubscriptionInvoice::where('store_id', $this->store->id)->sole(), SubscriptionInvoice::where('store_id', $other->id)->sole()];

        $this->actingAs($this->owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store->fresh());

        $this->assertTrue(SubscriptionBilling::canAccess());
        Livewire::test(SubscriptionBilling::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertSee($plan->name);

        $this->actingAs($this->manager);
        $this->assertFalse(SubscriptionBilling::canAccess());
    }
}
