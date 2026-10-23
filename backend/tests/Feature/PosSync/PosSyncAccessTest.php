<?php

namespace Tests\Feature\PosSync;

use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PosSyncOperation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-31: who may sync and what: only a POS terminal's own token, operations only of its store and branch, attributed
 * only to staff who signed in on that till before the outage and hold the permission; sync errors per store.
 */
class PosSyncAccessTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_only_a_pos_terminal_token_syncs(): void
    {
        $ops = $this->walkInSale()['ops'];

        // A staff PIN session is not the terminal's token
        $this->sync($ops, [], $this->cashierToken)->assertUnauthorized();
        $this->withBearer(null);
        $this->json('POST', '/api/v1/pos/sync', ['operations' => $ops])->assertUnauthorized();

        // A kitchen display is a terminal, but not a till
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $this->sync($ops, [], $kds['token'])->assertForbidden()->assertJsonPath('data.reason', 'terminal_required');
        $this->assertSame(0, PosSyncOperation::count());
    }

    public function test_the_batch_is_validated(): void
    {
        $ops = array_fill(0, 51, $this->walkInSale()['ops'][0]);
        $this->sync($ops)->assertUnprocessable();

        $op = $this->walkInSale()['ops'][0];
        $op['type'] = 'order.merge';
        $this->sync([$op])->assertUnprocessable()->assertJsonValidationErrors('operations.0.type');
    }

    public function test_another_store_s_till_cannot_touch_this_store_s_orders(): void
    {
        $sale = $this->walkInSale();
        $this->sync([$sale['ops'][0]])->assertOk();

        [$otherTill, $otherCashier] = $this->otherStoreTill();
        $this->pinSession($otherTill, $otherCashier);
        $this->seq = 0;
        $this->sync([$this->op('order.round', ['order' => $sale['order'], 'items' => [$this->line($this->tea, '1')]], $otherCashier)], [], $otherTill['token'])
            ->assertOk()->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'order_not_found');

        // Nor attribute an operation to this store's cashier
        $this->sync([$this->op('order.open', ['order' => (string) Str::uuid(), 'channel' => 'pos_takeaway'])], [], $otherTill['token'])
            ->assertOk()->assertJsonPath('data.results.0.reason', 'staff_invalid');

        $this->assertSame(2, Order::where('external_id', $sale['order'])->firstOrFail()->items()->count());
    }

    public function test_staff_must_have_signed_in_on_the_till_and_hold_the_permission(): void
    {
        $order = (string) Str::uuid();
        $open = $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->tea, '1')]], $this->waiter);

        // The waiter never typed their PIN on this till
        $this->sync([$open])->assertOk()->assertJsonPath('data.results.0.reason', 'staff_not_signed_in');

        // Signed in, the waiter may open orders but not take money
        $this->pinSession($this->till, $this->waiter, '7391');
        $this->pinSession($this->till, $this->cashier);
        $retry = $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->tea, '1')]], $this->waiter);
        $this->sync([$retry])->assertOk()->assertJsonPath('data.results.0.status', 'applied');
        $bill = (string) Str::uuid();
        $this->sync([
            $this->op('bill.create', ['order' => $order, 'bill' => $bill], $this->waiter),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'tenders' => [['tender' => 'cash', 'amount' => '5000']]], $this->waiter),
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'permission_denied');
    }

    public function test_an_operation_id_of_another_till_is_never_applied_or_shown(): void
    {
        $sale = $this->walkInSale();
        $this->sync([$sale['ops'][0]])->assertOk();

        $second = $this->pairTerminal($this->owner, $this->branch);
        $this->pinSession($second, $this->cashier);
        $this->sync([$sale['ops'][0]], [], $second['token'])->assertStatus(409)->assertJsonPath('data.reason', 'op_id_conflict');
    }

    public function test_sync_errors_are_listed_per_store_and_branch(): void
    {
        $this->sync([$this->op('order.round', ['order' => (string) Str::uuid(), 'items' => [$this->line($this->tea, '1')]])])->assertOk();
        $opId = (string) PosSyncOperation::value('op_id');

        $this->tillAs('GET', '/pos/sync/errors')->assertOk()->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.errors.0.device.uuid', $this->till['device']->uuid)
            ->assertJsonPath('data.errors.0.staff.id', $this->cashier->id);
        $this->tillAs('GET', '/pos/sync/errors?device=' . $this->till['device']->uuid)->assertOk()->assertJsonPath('data.meta.total', 1);

        // The owner on the web panel sees it; another store's owner neither sees nor resolves it
        $this->withBearer(null);
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/pos/sync/errors')->assertOk()->assertJsonPath('data.meta.total', 1);
        $otherOwner = $this->otherStoreOwner();
        $this->app['auth']->forgetGuards();
        $this->actingAs($otherOwner, 'sanctum')->getJson('/api/v1/pos/sync/errors')->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->actingAs($otherOwner, 'sanctum')->postJson("/api/v1/pos/sync/errors/{$opId}/resolve", ['resolution' => 'dismissed'])->assertNotFound();
        $this->assertNull(PosSyncOperation::value('resolved_at'));
    }

    private ?User $otherOwner = null;

    /**
     * @return array{0: array{device: \App\Models\Device, token: string}, 1: User}
     */
    private function otherStoreTill(): array
    {
        $owner = $this->otherStoreOwner();
        $branch = Branch::where('store_id', $owner->store_id)->firstOrFail();
        $cashier = $this->makeStaff(UserRole::CASHIER, $branch, Store::findOrFail($owner->store_id));

        return [$this->pairTerminal($owner, $branch), $cashier];
    }

    private function otherStoreOwner(): User
    {
        if ($this->otherOwner === null) {
            $store = $this->makeStore('Boshqa choyxona');
            $this->makeBranch($store, 'Asosiy');
            $this->otherOwner = $this->makeStaff(UserRole::OWNER, null, $store);
        }

        return $this->otherOwner;
    }
}
