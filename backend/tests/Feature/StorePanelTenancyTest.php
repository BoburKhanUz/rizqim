<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Widgets\AnalyticsHeroChart;
use App\Filament\Widgets\AnalyticsStatsWidget;
use App\Filament\Widgets\InventoryLowStockWidget;
use App\Filament\Widgets\LiveActivityFeed;
use App\Filament\Widgets\ManagerOrdersOverviewWidget;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\PaymentsRecentWidget;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopProductsChart;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\Ops\InventoryVelocityService;
use App\Services\Ops\KitchenLoadService;
use App\Services\Ops\PaymentInsightService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every figure and list in the store panel covers the tenant store only, and for a branch manager only their branch
 * (the scope of OrderPolicy and the API's order lists): no restaurant sees another restaurant's orders, sales or stock.
 */
class StorePanelTenancyTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private Store $foreignStore;
    private Branch $foreignBranch;
    private Product $foreignProduct;
    private ProductVariant $foreignVariant;

    // Today's orders: three of this store's (main branch, second branch), two of another restaurant's
    private Order $mainNew;
    private Order $secondPreparing;
    private Order $secondNew;
    private Order $foreignNew;
    private Order $foreignDelivered;

    protected function setUp(): void
    {
        parent::setUp();

        // The widgets count "today" and "this hour": one instant for the orders and the figures
        $this->freezeTime();
        $this->setUpLifecycleStore();

        $this->foreignStore = $this->makeStore('Foreign Grill');
        $this->foreignBranch = $this->makeBranch($this->foreignStore, 'Foreign Branch');
        $this->foreignProduct = Product::create([
            'store_id' => $this->foreignStore->id,
            'category_id' => $this->product->category_id,
            'name' => 'Foreign Plov',
            'slug' => 'foreign-plov-' . Str::random(5),
            'status' => 'active',
        ]);
        $this->foreignVariant = ProductVariant::create([
            'product_id' => $this->foreignProduct->id,
            'name' => 'Foreign Portion',
            'price' => 70000,
            'stock' => 3,
            'is_active' => true,
            'uuid' => (string) Str::uuid(),
        ]);

        $foreign = ['store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id];
        $this->mainNew = $this->orderWith('MAIN1', OrderStatus::NEW, 50000, 'Cheeseburger', 2);
        $this->secondPreparing = $this->orderWith('SECOND1', OrderStatus::PREPARING, 30000, 'Lavash', 1, ['branch_id' => $this->otherBranch->id]);
        $this->secondNew = $this->orderWith('SECOND2', OrderStatus::NEW, 20000, 'Lavash', 1, ['branch_id' => $this->otherBranch->id]);
        $this->foreignNew = $this->orderWith('FOREIGN1', OrderStatus::NEW, 70000, 'Foreign Plov', 5, $foreign);
        $this->foreignDelivered = $this->orderWith('FOREIGN2', OrderStatus::DELIVERED, 90000, 'Foreign Plov', 4, $foreign);
    }

    /**
     * An order with one item and its status history entry.
     */
    private function orderWith(string $shortCode, OrderStatus $status, int $total, string $productName, int $quantity, array $attributes = []): Order
    {
        $order = $this->makeOrder($status, ['short_code' => $shortCode, 'total_price' => $total] + $attributes);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => $productName,
            'quantity' => $quantity,
            'unit_price' => $total / $quantity,
            'total_price' => $total,
        ]);
        OrderStatusHistory::create(['order_id' => $order->id, 'user_id' => $this->customer->id, 'status' => $status->value]);

        return $order;
    }

    private function signIn(User $user, ?Store $tenant = null): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($tenant ?? $this->store);
    }

    /**
     * A stats widget's figures by label; a count-up stat animates to "let end = <figure>;", a zero one has none.
     */
    private function statFigures(string $widget): array
    {
        return collect(Livewire::test($widget)->invade()->getStats())
            ->mapWithKeys(fn (Stat $stat) => [
                (string) $stat->getLabel() => preg_match('/let end = ([\d.]+);/', (string) $stat->getValue(), $match) ? (float) $match[1] : (string) $stat->getValue(),
            ])
            ->all();
    }

    private function chartData(string $widget): array
    {
        return Livewire::test($widget)->invade()->getData();
    }

    public function test_owner_dashboard_counts_only_the_stores_own_orders(): void
    {
        $this->signIn($this->owner);

        $stats = $this->statFigures(StatsOverview::class);
        $this->assertEquals(30000, $stats['Revenue Today']); // the preparing order; the other store's delivered 90 000 is not ours
        $this->assertEquals(3, $stats['Orders Today']);
        $this->assertEquals(3, $stats['Active Orders']);
        $this->assertEquals(10000, $stats['Average Check']);

        // NEW, PREPARING, DELIVERING, COMPLETED
        $this->assertSame([2, 1, 0, 0], $this->chartData(OrdersChart::class)['datasets'][0]['data']);

        $revenue = $this->chartData(RevenueChart::class)['datasets'][0]['data'];
        $this->assertEquals(30000, end($revenue));

        $top = $this->chartData(TopProductsChart::class);
        $this->assertEqualsCanonicalizing(['Cheeseburger', 'Lavash'], $top['labels']);
        $this->assertEquals([2, 2], $top['datasets'][0]['data']);
    }

    public function test_branch_manager_dashboard_counts_only_their_branchs_orders(): void
    {
        $this->signIn($this->manager);

        $stats = $this->statFigures(StatsOverview::class);
        $this->assertEquals('0 UZS', $stats['Revenue Today']);
        $this->assertEquals(1, $stats['Orders Today']);
        $this->assertEquals(1, $stats['Active Orders']);

        $this->assertSame([1, 0, 0, 0], $this->chartData(OrdersChart::class)['datasets'][0]['data']);
        $this->assertSame(['Cheeseburger'], $this->chartData(TopProductsChart::class)['labels']);
        $revenue = $this->chartData(RevenueChart::class)['datasets'][0]['data'];
        $this->assertEquals(0, end($revenue));

        $managerStats = collect(Livewire::test(ManagerOrdersOverviewWidget::class)->invade()->getStats())
            ->mapWithKeys(fn (Stat $stat) => [(string) $stat->getLabel() => $stat->getValue()]);
        $this->assertEquals(1, $managerStats['Bugungi Buyurtmalar']);
        $this->assertEquals(1, $managerStats['Kutilayotgan']);

        $feed = Livewire::test(LiveActivityFeed::class)->invade()->getActivities();
        $this->assertSame([$this->mainNew->id], $feed->pluck('order_id')->all());
    }

    public function test_activity_feed_and_order_badges_follow_the_store_and_the_managers_branch(): void
    {
        $this->signIn($this->owner);

        $feed = Livewire::test(LiveActivityFeed::class)->invade()->getActivities();
        $this->assertEqualsCanonicalizing([$this->mainNew->id, $this->secondPreparing->id, $this->secondNew->id], $feed->pluck('order_id')->all());
        $this->assertEquals(2, Livewire::test(ListOrders::class)->instance()->getTabs()['new']->getBadge());

        // The badge counted the whole store although the manager's table only lists their branch
        $this->signIn($this->manager);
        $tabs = Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$this->mainNew])
            ->assertCanNotSeeTableRecords([$this->secondNew, $this->foreignNew])
            ->instance()
            ->getTabs();
        $this->assertEquals(1, $tabs['new']->getBadge());
        $this->assertEquals(1, $tabs['active']->getBadge());
        $this->assertEquals(0, $tabs['preparing']->getBadge());
    }

    public function test_analytics_payments_and_inventory_pages_show_only_the_stores_records(): void
    {
        $this->variant->update(['stock' => 4]);
        $this->signIn($this->owner);

        $hourly = $this->chartData(AnalyticsHeroChart::class)['datasets'][0]['data'];
        $this->assertSame(3, end($hourly));
        $this->assertSame('3 Orders', $this->statFigures(AnalyticsStatsWidget::class)['Orders Today']);

        Livewire::test(PaymentsRecentWidget::class)
            ->assertCanSeeTableRecords([$this->mainNew, $this->secondPreparing, $this->secondNew])
            ->assertCanNotSeeTableRecords([$this->foreignNew, $this->foreignDelivered])
            ->assertDontSee('FOREIGN1');

        Livewire::test(InventoryLowStockWidget::class)
            ->assertCanSeeTableRecords([$this->variant])
            ->assertCanNotSeeTableRecords([$this->foreignVariant])
            ->assertDontSee('Foreign Portion');
    }

    public function test_ops_figures_follow_the_tenant_and_a_branch_managers_branch(): void
    {
        $this->signIn($this->owner);
        $this->assertSame(2, KitchenLoadService::getPendingAcceptCount());
        $this->assertSame(1, KitchenLoadService::getActivePrepCount());
        $this->assertEqualsWithDelta(100000 / 3, PaymentInsightService::getAverageOrderValue(), 0.01);

        $this->signIn($this->manager);
        $this->assertSame(1, KitchenLoadService::getPendingAcceptCount());
        $this->assertSame(0, KitchenLoadService::getActivePrepCount());
        $this->assertSame('Cheeseburger', InventoryVelocityService::getTopSellingProductToday()['name']);

        // A super admin has no store of their own: the figures are the tenant's, not the first store's
        $admin = User::create(['name' => 'Platform Admin', 'phone' => '+998900000001', 'password' => bcrypt('password'), 'role' => UserRole::SUPER_ADMIN->value]);
        $this->signIn($admin, $this->foreignStore);
        $this->assertSame(1, KitchenLoadService::getPendingAcceptCount());
        $this->assertSame('Foreign Plov', InventoryVelocityService::getTopSellingProductToday()['name']);

        // A new order reaches the branch figures too, not only the store-wide ones
        $this->signIn($this->manager);
        $this->orderWith('MAIN2', OrderStatus::NEW, 10000, 'Cheeseburger', 1);
        $this->assertSame(2, KitchenLoadService::getPendingAcceptCount());
    }

    public function test_employee_order_figures_count_only_orders_at_this_store(): void
    {
        // The courier also orders as a customer, here and at another restaurant
        $this->makeOrder(OrderStatus::DELIVERED, ['short_code' => 'OWNSHOP', 'user_id' => $this->courier->id, 'total_price' => 40000]);
        $this->makeOrder(OrderStatus::DELIVERED, ['short_code' => 'ELSEWHERE', 'user_id' => $this->courier->id, 'total_price' => 60000,
            'store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id]);
        $this->signIn($this->owner);

        $employees = Livewire::test(ManageEmployees::class)
            ->assertTableColumnStateSet('orders_count', 1, $this->courier)
            ->assertTableColumnStateSet('total_spent', 40000, $this->courier)
            ->mountTableAction('view', $this->courier)
            ->assertSchemaComponentStateSet('orders_count', 1)
            ->assertSchemaComponentStateSet('total_spent', 40000);

        // The profile's "Recent Orders Timeline"
        $schema = $employees->instance()->{$employees->instance()->getMountedActionSchemaName()};
        $timeline = $schema->getFlatComponents(withHidden: true)['id'];
        $timelineHtml = (string) $timeline->formatState($timeline->getState());
        $this->assertStringContainsString('Order #OWNSHOP', $timelineHtml);
        $this->assertStringNotContainsString('ELSEWHERE', $timelineHtml);
    }

    public function test_branch_manager_sees_and_assigns_couriers_of_their_own_branch_only(): void
    {
        Http::fake(); // a created employee's invite goes to the owner's Telegram when a bot is configured
        $storeWideCourier = $this->makeStaff(UserRole::COURIER);
        $secondBranchCourier = $this->makeStaff(UserRole::COURIER, $this->otherBranch);
        $foreignCourier = $this->makeStaff(UserRole::COURIER, $this->foreignBranch, $this->foreignStore);

        $this->signIn($this->owner);
        Livewire::test(ManageEmployees::class)
            ->assertCanSeeTableRecords([$this->manager, $this->courier, $storeWideCourier, $secondBranchCourier])
            ->assertCanNotSeeTableRecords([$foreignCourier])
            ->callAction('create', data: ['name' => 'Foreign Branch Courier', 'role' => UserRole::COURIER->value, 'branch_id' => $this->foreignBranch->id])
            ->assertHasActionErrors(['branch_id']);

        $this->signIn($this->manager);
        Livewire::test(ManageEmployees::class)
            ->assertCanSeeTableRecords([$this->courier, $storeWideCourier])
            ->assertCanNotSeeTableRecords([$secondBranchCourier, $foreignCourier, $this->manager])
            ->callAction('create', data: ['name' => 'Second Branch Courier', 'role' => UserRole::COURIER->value, 'branch_id' => $this->otherBranch->id])
            ->assertHasActionErrors(['branch_id']);
        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'Main Branch Courier', 'role' => UserRole::COURIER->value, 'branch_id' => $this->branch->id])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('users', ['name' => 'Foreign Branch Courier']);
        $this->assertDatabaseMissing('users', ['name' => 'Second Branch Courier']);
        $this->assertDatabaseHas('users', ['name' => 'Main Branch Courier', 'store_id' => $this->store->id, 'branch_id' => $this->branch->id]);
    }

    public function test_a_product_name_only_has_to_be_unique_within_the_store(): void
    {
        Product::create(['store_id' => $this->foreignStore->id, 'category_id' => $this->product->category_id, 'name' => 'Lagman', 'slug' => 'lagman', 'status' => 'active']);
        $this->signIn($this->owner);
        Filament::bootCurrentPanel(); // as a panel request does: a new product gets the tenant as its store
        $undoRepeaterFake = Repeater::fake();
        $lagman = [
            'name' => 'Lagman',
            'category_id' => $this->product->category_id,
            'variants' => [['name' => 'Portion', 'price' => 30000, 'is_active' => true]],
        ];

        // Another restaurant's "Lagman" neither blocks the name nor gets revealed by a "taken" error
        Livewire::test(CreateProduct::class)->fillForm($lagman)->call('create')->assertHasNoFormErrors();
        $this->assertSame('lagman', Product::where('store_id', $this->store->id)->where('name', 'Lagman')->sole()->slug);

        // Within the store a name still has to be unique
        Livewire::test(CreateProduct::class)->fillForm($lagman)->call('create')->assertHasFormErrors(['slug']);

        $undoRepeaterFake();
    }

    /**
     * Through the real middleware (the panel boots, so Filament's tenancy scope is on): resources without their own
     * query scope stay inside the tenant, and an order's customer is still shown.
     */
    public function test_panel_pages_keep_to_the_tenant_and_still_show_each_orders_customer(): void
    {
        $tenant = $this->store->uuid;

        $this->actingAs($this->owner)->get("/admin/{$tenant}/branches")
            ->assertOk()->assertSee('Second Branch')->assertDontSee('Foreign Branch');
        $this->actingAs($this->owner)->get("/admin/{$tenant}/products")
            ->assertOk()->assertSee('Cheeseburger')->assertDontSee('Foreign Plov');
        $this->actingAs($this->owner)->get("/admin/{$tenant}/branches/{$this->foreignBranch->getRouteKey()}/edit")->assertNotFound();
        $this->actingAs($this->owner)->get("/admin/{$tenant}/products/{$this->foreignProduct->getRouteKey()}/edit")->assertNotFound();
        $this->actingAs($this->owner)->get("/admin/{$tenant}/orders/{$this->foreignNew->getRouteKey()}")->assertNotFound();
        $this->actingAs($this->owner)->get("/admin/{$this->foreignStore->uuid}")->assertNotFound();

        // Filament's tenancy scope on users (store_id = tenant) used to hide every customer from the panel
        $this->actingAs($this->owner)->get("/admin/{$tenant}/orders")
            ->assertOk()->assertSee('MAIN1')->assertSee($this->customer->name)->assertDontSee('FOREIGN1');
        $this->actingAs($this->owner)->get("/admin/{$tenant}/orders/{$this->mainNew->getRouteKey()}")
            ->assertOk()->assertSee($this->customer->name)->assertSee($this->customer->phone);
    }
}
