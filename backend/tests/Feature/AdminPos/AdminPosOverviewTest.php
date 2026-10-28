<?php

namespace Tests\Feature\AdminPos;

use App\Enums\PlatformRole;
use App\Services\Finance\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-24: the numbers on the tabs, each group behind its own permission; and the P4-21 billing endpoints the
 * Obunalar and Tariflar tabs are built on, as the POS roles reach them.
 */
class AdminPosOverviewTest extends TestCase
{
    use RefreshDatabase;
    use AdminPosFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    private function seedEverything(): void
    {
        $this->invoice(['due_date' => now('Asia/Tashkent')->subDay()->toDateString()]);
        $this->invoice(['due_date' => now('Asia/Tashkent')->addDay()->toDateString()], $this->foreignStore);
        $this->device(['last_seen_at' => now()->subHour()]);
        $this->device(['last_seen_at' => now()]);
        $this->shift(['difference' => '-100.00']);
        $this->shift(['force_closed' => true]);
        $receipt = fn (string $status) => \App\Models\FiscalReceipt::forceCreate([
            'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'cash', 'status' => $status, 'driver' => 'sandbox',
            'idempotency_key' => bin2hex(random_bytes(8)), 'total' => '1000.00', 'lines' => [], 'payments' => [],
        ]);
        $receipt('failed');
        $receipt('pending');
        $this->syncOp($this->device(['name' => 'Second']), 'conflict');
    }

    public function test_a_superadmin_gets_every_group(): void
    {
        $this->seedEverything();
        $this->billing()->assignPlan($this->store, \App\Models\TariffPlan::where('code', 'asosiy')->sole());
        $this->billing()->assignPlan($this->foreignStore, \App\Models\TariffPlan::where('code', 'start')->sole());

        $data = $this->adminJson('GET', '/pos/overview', $this->signIn($this->makeMember(PlatformRole::SUPERADMIN)))->assertOk()->json('data');

        // The store with the overdue invoice is past due
        $this->assertEquals(['trialing' => 1, 'past_due' => 1], $data['subscriptions']['counts']);
        $this->assertSame([0, 3], [$data['subscriptions']['trial_ending'], $data['subscriptions']['trial_ending_days']]);
        $this->assertSame(['issued' => 2, 'overdue' => 1], ['issued' => $data['invoices']['issued'], 'overdue' => $data['invoices']['overdue']]);
        $this->assertSame([3, 2, 1], [$data['devices']['total'], $data['devices']['online'], $data['devices']['offline']]);
        $this->assertSame([1, 1, 0, 2], [$data['shifts']['difference'], $data['shifts']['force_closed'], $data['shifts']['open'], $data['shifts']['all']]);
        $this->assertSame([1, 1], [$data['fiscal']['failed'], $data['fiscal']['pending']]);
        $this->assertSame(['rejected' => 0, 'conflict' => 1, 'open' => 1], $data['sync_errors']);
    }

    public function test_trials_ending_in_three_days_are_counted(): void
    {
        $this->billing()->assignPlan($this->store, \App\Models\TariffPlan::where('code', 'asosiy')->sole());
        $this->billing()->assignPlan($this->foreignStore, \App\Models\TariffPlan::where('code', 'start')->sole());
        \App\Models\StoreSubscription::where('store_id', $this->store->id)->update(['trial_ends_at' => now()->addDays(2)]);

        $token = $this->signIn($this->makeMember(PlatformRole::SALES));
        $this->assertSame(1, $this->adminJson('GET', '/pos/overview', $token)->json('data.subscriptions.trial_ending'));

        // The Obunalar tab's own list (P4-21) filters the same trials
        $list = $this->adminJson('GET', '/billing/subscriptions?trial_ends_within=3', $token)->assertOk();
        $this->assertSame([$this->store->id], array_column(array_column($list->json('data.data'), 'store'), 'id'));
    }

    public function test_each_group_needs_its_own_permission(): void
    {
        $this->seedEverything();

        // Finance sees the money side only
        $finance = $this->adminJson('GET', '/pos/overview', $this->signIn($this->makeMember(PlatformRole::FINANCE)))->assertOk()->json('data');
        $this->assertNotNull($finance['subscriptions']);
        $this->assertNotNull($finance['invoices']);
        $this->assertNull($finance['devices']);
        $this->assertNull($finance['shifts']);
        $this->assertNull($finance['fiscal']);
        $this->assertNull($finance['sync_errors']);

        // The operator sees the tills only
        $operator = $this->adminJson('GET', '/pos/overview', $this->signIn($this->makeMember(PlatformRole::OPERATOR)))->assertOk()->json('data');
        $this->assertNull($operator['subscriptions']);
        $this->assertNull($operator['invoices']);
        $this->assertNotNull($operator['devices']);
        $this->assertSame(1, $operator['fiscal']['failed']);

        // Sales, with both
        $sales = $this->adminJson('GET', '/pos/overview', $this->signIn($this->makeMember(PlatformRole::SALES)))->assertOk()->json('data');
        $this->assertNotNull($sales['invoices']);
        $this->assertNotNull($sales['sync_errors']);

        $this->adminJson('GET', '/pos/overview', $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertForbidden();
        $this->adminJson('GET', '/pos/overview')->assertUnauthorized();
    }

    private function billing(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }
}
