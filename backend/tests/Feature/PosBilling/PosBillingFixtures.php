<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosShift;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\StaffOrderFixtures;

/**
 * P3-18 helpers: a branch with a paired till, its cashier (PIN session on the till) and manager (PIN 5926), POS orders
 * with lines and charges, the bill/payment/shift requests (each with its own Idempotency-Key), and fake fiscal and
 * ledger services (P3-11, P3-17) the tests read back.
 */
trait PosBillingFixtures
{
    use StaffOrderFixtures;

    protected User $cashier;

    protected User $waiter;

    /** @var array{device: Device, token: string} */
    protected array $till;

    protected string $cashierToken;

    protected FakeFiscalIssuer $fiscal;

    protected FakeAdvanceLedger $advances;

    protected FakeAccountLedger $accounts;

    protected const MANAGER_PIN = '5926';

    protected function setUpPosBilling(): void
    {
        $this->setUpLifecycleStore();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
        $this->givePin($this->manager, self::MANAGER_PIN);

        $this->fiscal = new FakeFiscalIssuer();
        $this->advances = new FakeAdvanceLedger();
        $this->accounts = new FakeAccountLedger();
        $this->app->instance(FiscalIssuer::class, $this->fiscal);
        $this->app->instance(FiscalLineBuilder::class, new FakeFiscalLineBuilder());
        $this->app->instance(AdvanceLedger::class, $this->advances);
        $this->app->instance(CustomerAccountLedger::class, $this->accounts);

        $this->till = $this->pairTerminal($this->owner, $this->branch);
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
    }

    protected function openShift(string $openingCash = '100000', ?Device $device = null, ?User $user = null): PosShift
    {
        return app(ShiftService::class)->open($user ?? $this->cashier, $device ?? $this->till['device'], Money::of($openingCash));
    }

    /**
     * A POS hall order (accepted, version 1) with lines [name, quantity, total] and order columns (discount, charges).
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $lines
     * @param  array<string, mixed>  $attributes
     */
    protected function posOrder(array $lines = [['Osh', '1.5', '120000'], ['Choy', '2', '10000']], array $attributes = []): Order
    {
        $order = Order::create($attributes + [
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'status' => OrderStatus::ACCEPTED,
            'order_type' => 'dine_in',
            'channel' => 'pos_hall',
            'payment_method' => 'cash',
            'payment_owner' => 'STORE',
            'commission_amount' => 0,
            'guests_count' => 4,
            'device_id' => $this->till['device']->id,
            'version' => 1,
        ]);

        $subtotal = Money::zero();
        foreach ($lines as [$name, $quantity, $total]) {
            $this->line($order, $name, $quantity, $total);
            $subtotal = $subtotal->plus($total);
        }

        $total = $subtotal->minus($order->discount ?? 0)
            ->plus($order->service_charge_amount ?? 0)->plus($order->place_fee_amount ?? 0)
            ->plus($order->room_fee_amount ?? 0)->plus($order->min_spend_topup_amount ?? 0);
        $order->forceFill(['subtotal' => $subtotal->toString(), 'total_price' => $total->toString()])->save();

        return $order->refresh();
    }

    protected function line(Order $order, string $name, string $quantity, string $total): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => null,
            'product_name' => $name,
            'quantity' => $quantity,
            'unit' => str_contains($quantity, '.') ? 'kg' : 'piece',
            'unit_price' => Money::of($total)->toString(),
            'total_price' => Money::of($total)->toString(),
        ]);
    }

    /**
     * A POS request with a fresh Idempotency-Key (or the given one) as the cashier on the till, or another token.
     */
    protected function pos(string $method, string $uri, array $data = [], ?string $token = null, ?string $key = null): TestResponse
    {
        $this->withBearer($token ?? $this->cashierToken);

        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->json($method, '/api/v1/pos' . $uri, $data);
    }

    protected function posAs(User $user, string $method, string $uri, array $data = [], ?string $key = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->json($method, '/api/v1/pos' . $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function bill(Order $order, string $mode = 'single', array $extra = [], ?string $token = null): TestResponse
    {
        return $this->pos('POST', "/orders/{$order->uuid}/bills", ['version' => (int) $order->refresh()->version, 'mode' => $mode] + $extra, $token);
    }

    /**
     * @param  list<array<string, mixed>>  $tenders
     */
    protected function pay(string $billUuid, array $tenders, array $extra = [], ?string $token = null): TestResponse
    {
        return $this->pos('POST', "/bills/{$billUuid}/payments", ['tenders' => $tenders] + $extra, $token);
    }

    /**
     * A manager's one-time approval token for $permission on the till (as the cashier's session asks for it).
     */
    protected function approval(string $permission, ?string $token = null): string
    {
        return (string) $this->pos('POST', '/approvals', [
            'permission' => $permission,
            'user_id' => $this->manager->id,
            'pin' => self::MANAGER_PIN,
        ], $token)->assertCreated()->json('data.token');
    }

    /**
     * Sum of decimal strings.
     *
     * @param  iterable<mixed>  $amounts
     */
    protected function sumOf(iterable $amounts): string
    {
        $sum = Money::zero();
        foreach ($amounts as $amount) {
            $sum = $sum->plus((string) $amount);
        }

        return $sum->toString();
    }
}
