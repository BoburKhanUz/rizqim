<?php

namespace Tests\Feature\PosSync;

use App\Enums\UserRole;
use App\Models\PosSyncOperation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave M4 T-1: an offline operation naming a user of another store is rejected (staff_invalid) and its row does not
 * link that user, so the store's sync error list never shows another store's user name. The raw id stays in what the
 * till sent; an operation of the store's own staff keeps its staff.
 */
class PosSyncForeignStaffTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_a_foreign_staff_id_is_not_linked_or_shown(): void
    {
        $otherStore = $this->makeStore('Boshqa choyxona');
        $this->makeBranch($otherStore, 'Asosiy');
        $stranger = $this->makeStaff(UserRole::CASHIER, null, Store::findOrFail($otherStore->id), ['name' => 'Begona Kassir']);
        $customer = User::create(['name' => 'Begona Mijoz', 'phone' => '+998901112233', 'password' => bcrypt('x')]);

        foreach ([$stranger, $customer] as $user) {
            $this->sync([$this->op('order.open', ['order' => (string) Str::uuid(), 'channel' => 'pos_takeaway'], $user)])
                ->assertOk()->assertJsonPath('data.results.0.reason', 'staff_invalid');
        }

        $this->assertSame([null, null], PosSyncOperation::orderBy('seq')->pluck('staff_id')->all());
        $errors = $this->tillAs('GET', '/pos/sync/errors')->assertOk()->assertJsonPath('data.meta.total', 2)->json('data.errors');
        foreach ($errors as $error) {
            $this->assertNull($error['staff']);
        }
        $this->assertStringNotContainsString('Begona', (string) json_encode($errors));

        // The store's own cashier, rejected for another reason, is still named (the owner sees who it was)
        $this->sync([$this->op('order.round', ['order' => (string) Str::uuid(), 'items' => [$this->line($this->tea, '1')]])])
            ->assertOk()->assertJsonPath('data.results.0.status', 'rejected');
        $this->assertSame((int) $this->cashier->id, (int) PosSyncOperation::orderByDesc('seq')->value('staff_id'));
    }
}
