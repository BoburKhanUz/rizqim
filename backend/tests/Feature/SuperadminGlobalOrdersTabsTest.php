<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Superadmin\Resources\GlobalOrders\Pages\ListGlobalOrders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The superadmin's order list works across all stores: the superadmin has no store (store_id is null), so the tabs and
 * their counters must not be scoped by the signed-in user's store.
 */
class SuperadminGlobalOrdersTabsTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->actingAs(User::create(['name' => 'Super Admin', 'phone' => '+998909990001', 'role' => UserRole::SUPER_ADMIN->value, 'password' => bcrypt('password')]));
        Filament::setCurrentPanel('superadmin');
    }

    public function test_tabs_count_and_list_the_orders_of_every_store(): void
    {
        $otherStore = $this->makeStore('Other Grill');
        $otherBranch = $this->makeBranch($otherStore, 'Other Branch');

        $new = $this->makeOrder(OrderStatus::NEW);
        $otherNew = $this->makeOrder(OrderStatus::NEW, ['store_id' => $otherStore->id, 'branch_id' => $otherBranch->id]);
        $preparing = $this->makeOrder(OrderStatus::PREPARING);
        $otherDelivery = $this->makeOrder(OrderStatus::DELIVERY, ['store_id' => $otherStore->id, 'branch_id' => $otherBranch->id]);
        $delivered = $this->makeOrder(OrderStatus::DELIVERED);
        $otherCanceled = $this->makeOrder(OrderStatus::CANCELED, ['store_id' => $otherStore->id, 'branch_id' => $otherBranch->id]);

        $page = Livewire::test(ListGlobalOrders::class);

        $badges = collect($page->instance()->getTabs())->map(fn ($tab) => (int) $tab->getBadge())->all();
        $this->assertSame(['active' => 4, 'new' => 2, 'preparing' => 1, 'delivering' => 1, 'completed' => 2], $badges);

        $page->set('activeTab', 'new')->assertCanSeeTableRecords([$new, $otherNew])->assertCanNotSeeTableRecords([$preparing, $delivered]);
        $page->set('activeTab', 'delivering')->assertCanSeeTableRecords([$otherDelivery]);
        $page->set('activeTab', 'completed')->assertCanSeeTableRecords([$delivered, $otherCanceled])->assertCanNotSeeTableRecords([$new]);
    }

    public function test_every_status_lands_in_one_stage_tab(): void
    {
        $orders = collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status) => [$status->value => $this->makeOrder($status)]);

        $page = Livewire::test(ListGlobalOrders::class);
        $badges = collect($page->instance()->getTabs())->map(fn ($tab) => (int) $tab->getBadge())->all();
        $this->assertSame(['active' => 7, 'new' => 1, 'preparing' => 3, 'delivering' => 3, 'completed' => 4], $badges);

        $page->set('activeTab', 'preparing')->assertCanSeeTableRecords([$orders['ready']]);
        $page->set('activeTab', 'delivering')->assertCanSeeTableRecords([$orders['courier_assigned'], $orders['on_the_way']]);
        $page->set('activeTab', 'completed')->assertCanSeeTableRecords([$orders['rejected'], $orders['refunded']])
            ->assertCanNotSeeTableRecords([$orders['ready']]);
    }
}
