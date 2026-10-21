<?php

namespace Tests\Feature;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Enums\UserRole;
use App\Models\AutoGuestItem;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ServiceFeeSetting;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;

/**
 * P3-12 helpers: a branch with a hall (two rooms, a table, a tapchan) and a VIP hall, its cashier and waiter, a menu
 * of tea, bread, a kazan plov sold by the kg and a shashlik, the POS requests (with an Idempotency-Key each), and fake
 * KitchenStationResolver (P3-13) and ManagerApprovals (P3-18).
 */
trait PosHallFixtures
{
    use MenuFixtures;
    use StaffOrderFixtures;

    protected User $cashier;

    protected User $waiter;

    protected Hall $hall;

    protected Hall $vipHall;

    protected DiningTable $room;

    protected DiningTable $vipRoom;

    protected DiningTable $table;

    protected DiningTable $tapchan;

    protected ProductVariant $tea;

    protected ProductVariant $bread;

    protected ProductVariant $plov;

    protected ProductVariant $shashlik;

    /** @var array<string, array{permission: string, device: int|null, user: int}> valid approval tokens */
    public array $approvals = [];

    protected function setUpPosHall(): void
    {
        $this->setUpLifecycleStore();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->waiter = $this->makeStaff(UserRole::WAITER, $this->branch);

        $this->hall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        $this->vipHall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'VIP', 'sort_order' => 2]);
        $this->room = $this->place($this->hall, '1-xona', 'room', ['seats' => 10]);
        $this->table = $this->place($this->hall, 'Stol 5', 'table', ['seats' => 4]);
        $this->tapchan = $this->place($this->hall, 'Tapchan 1', 'tapchan', ['seats' => 8]);
        $this->vipRoom = $this->place($this->vipHall, 'VIP-1', 'room', ['seats' => 16]);

        $this->tea = $this->makeVariant($this->makeProduct('Choy'), '5000', ['name' => 'Choynak', 'stock' => 1000]);
        $this->bread = $this->makeVariant($this->makeProduct('Non'), '4000', ['name' => 'Dona', 'stock' => 1000]);
        $this->plov = $this->makeVariant($this->makeProduct('Osh', ['batch_tracked' => true]), '120000', [
            'name' => 'Choyxona oshi', 'unit' => 'kg', 'quantity_step' => '0.5', 'min_quantity' => '0.5', 'stock' => 1000,
        ]);
        $this->shashlik = $this->makeVariant($this->makeProduct('Shashlik'), '18000', ['name' => 'Six', 'stock' => 1000]);

        // P3-13 and P3-18 are faked: every line goes to station 77; approval tokens are the ones the test issues
        $this->app->instance(KitchenStationResolver::class, new class implements KitchenStationResolver
        {
            public function stationIdFor(OrderItem $item): ?int
            {
                return 77;
            }
        });
        $test = $this;
        $this->app->instance(ManagerApprovals::class, new class($test) implements ManagerApprovals
        {
            public function __construct(private readonly \Tests\TestCase $test) {}

            public function consume(string $token, string $permission, ?int $deviceId): int
            {
                $approval = $this->test->approvals[$token] ?? null;
                if ($approval === null || $approval['permission'] !== $permission || $approval['device'] !== $deviceId) {
                    throw new RuntimeException('Invalid approval');
                }
                unset($this->test->approvals[$token]);

                return $approval['user'];
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function place(Hall $hall, string $name, string $kind, array $attributes = []): DiningTable
    {
        return DiningTable::create(array_merge([
            'store_id' => $hall->store_id,
            'branch_id' => $hall->branch_id,
            'hall_id' => $hall->id,
            'name' => $name,
            'kind' => $kind,
        ], $attributes));
    }

    protected function approval(string $permission, User $manager, ?int $deviceId = null): string
    {
        $token = Str::random(24);
        $this->approvals[$token] = ['permission' => $permission, 'device' => $deviceId, 'user' => (int) $manager->id];

        return $token;
    }

    protected function serviceFee(string $percent, ?Hall $hall = null, string $placeFee = '0', string $mode = 'per_person'): ServiceFeeSetting
    {
        return ServiceFeeSetting::create([
            'store_id' => $this->store->id,
            'branch_id' => $hall?->branch_id ?? $this->branch->id,
            'hall_id' => $hall?->id,
            'service_charge_percent' => $percent,
            'place_fee_amount' => $placeFee,
            'place_fee_mode' => $mode,
        ]);
    }

    protected function choyNonRule(?Hall $hall = null, string $teaPerGuest = '0.5', string $breadPerGuest = '1'): void
    {
        foreach ([[$this->tea, $teaPerGuest], [$this->bread, $breadPerGuest]] as [$variant, $quantity]) {
            AutoGuestItem::create([
                'store_id' => $this->store->id,
                'branch_id' => $this->branch->id,
                'hall_id' => $hall?->id,
                'product_variant_id' => $variant->id,
                'quantity_per_guest' => $quantity,
            ]);
        }
    }

    /**
     * A POS request as $actor (a user, or a PIN session token), with a fresh Idempotency-Key unless one is given.
     *
     * @param  array<string, mixed>  $data
     */
    protected function pos(User|string $actor, string $method, string $uri, array $data = [], ?string $key = null): TestResponse
    {
        $headers = $method === 'GET' ? [] : ['Idempotency-Key' => $key ?? (string) Str::uuid()];

        if (is_string($actor)) {
            return $this->withBearer($actor)->withHeaders($headers)->json($method, "/api/v1{$uri}", $data);
        }

        $this->app['auth']->forgetGuards();

        return $this->actingAs($actor, 'sanctum')->withHeaders($headers)->json($method, "/api/v1{$uri}", $data);
    }

    /**
     * POST /pos/orders: a hall order at $table (default: the room) for $guests.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function openPos(User|string $actor, ?DiningTable $table = null, int $guests = 4, array $extra = []): TestResponse
    {
        return $this->pos($actor, 'POST', '/pos/orders', array_merge([
            'branch_id' => $this->branch->id,
            'channel' => 'pos_hall',
            'table_id' => ($table ?? $this->room)->id,
            'guests_count' => $guests,
        ], $extra));
    }

    protected function openedOrder(TestResponse $response): Order
    {
        $response->assertCreated();

        return Order::where('uuid', $response->json('data.uuid'))->firstOrFail();
    }

    /**
     * @return array{variant_id: string, quantity: string|int}
     */
    protected function line(ProductVariant $variant, string|int $quantity = 1, array $extra = []): array
    {
        return array_merge(['variant_id' => $variant->uuid, 'quantity' => $quantity], $extra);
    }
}
