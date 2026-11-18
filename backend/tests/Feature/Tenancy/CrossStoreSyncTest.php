<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: an offline operation id is unique across every store (pos_sync_operations.op_id). Another store's till
 * that sends it is still answered 409 op_id_conflict with the scope on (not left to the unique index, which the
 * applier would answer as a database error to retry forever).
 */
class CrossStoreSyncTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    public function test_an_operation_id_of_another_stores_till_is_refused_as_a_conflict(): void
    {
        $this->setUpPosSync();
        $sale = $this->walkInSale();
        $this->sync([$sale['ops'][0]])->assertOk();

        $store = $this->makeStore('Boshqa choyxona');
        $branch = $this->makeBranch($store, 'Asosiy');
        $owner = $this->makeStaff(UserRole::OWNER, null, $store);
        $till = $this->pairTerminal($owner, $branch);
        $this->pinSession($till, $this->makeStaff(UserRole::CASHIER, $branch, $store));

        $this->sync([$sale['ops'][0]], [], $till['token'])->assertStatus(409)->assertJsonPath('data.reason', 'op_id_conflict');
    }
}
