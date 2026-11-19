<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Domain\Pos\Shifts\CashMovementType;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\PosShift;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-33 (review A, finding 2): a branch is not deleted while staff, unrevoked devices or delivery zones are tied to it.
 * The foreign keys would set their branch to NULL, and NULL means "the whole store": the branch's manager would see
 * every branch, its kitchen display would join every branch's channel and its zones would deliver from anywhere.
 */
class BranchDeletionReferencesTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    private Branch $doomed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
        $this->doomed = $this->makeBranch($this->store, 'Doomed');
    }

    private function deleteDoomed(): \Illuminate\Testing\TestResponse
    {
        return $this->as($this->owner, 'DELETE', "/api/v1/owner/branches/{$this->doomed->id}");
    }

    public function test_staff_devices_and_zones_tied_to_the_branch_block_its_deletion_with_counts(): void
    {
        $manager = $this->makeStaff(UserRole::MANAGER, $this->doomed);
        $this->makeStaff(UserRole::COOK, $this->doomed);
        $terminal = $this->pairTerminal($this->owner, $this->doomed, DeviceType::KDS);
        $zone = $this->makeZone(['branch_id' => $this->doomed->id]);

        $this->deleteDoomed()->assertStatus(422)
            ->assertJsonPath('data.reason', 'branch_in_use')
            ->assertJsonPath('data.staff', 2)
            ->assertJsonPath('data.devices', 1)
            ->assertJsonPath('data.zones', 1)
            ->assertJsonPath('message', "Filialni o'chirib bo'lmaydi: unga 2 ta xodim, 1 ta faol qurilma (terminal), 1 ta yetkazib berish hududi biriktirilgan. Avval xodimlarni boshqa filialga o'tkazing, qurilmalarni bekor qiling va hududlarni boshqa filialga biriktiring yoki o'chiring. O'chirilsa ular butun do'konga tegishli bo'lib qolardi.");
        $this->assertNotNull(Branch::find($this->doomed->id));
        $this->assertSame($this->doomed->id, (int) $manager->fresh()->branch_id);

        // Moved away, revoked, re-pointed: one by one the reasons go
        $manager->forceFill(['branch_id' => $this->branch->id])->save();
        \App\Models\User::where('branch_id', $this->doomed->id)->update(['branch_id' => null]);
        $this->deleteDoomed()->assertStatus(422)->assertJsonPath('data.staff', 0)->assertJsonPath('data.devices', 1)
            ->assertJsonPath('message', "Filialni o'chirib bo'lmaydi: unga 1 ta faol qurilma (terminal), 1 ta yetkazib berish hududi biriktirilgan. Avval xodimlarni boshqa filialga o'tkazing, qurilmalarni bekor qiling va hududlarni boshqa filialga biriktiring yoki o'chiring. O'chirilsa ular butun do'konga tegishli bo'lib qolardi.");

        Device::whereKey($terminal['device']->id)->update(['revoked_at' => now()]);
        $this->deleteDoomed()->assertStatus(422)->assertJsonPath('data.devices', 0)->assertJsonPath('data.zones', 1);

        $zone->forceFill(['branch_id' => $this->branch->id])->save();
        $this->deleteDoomed()->assertOk();
        $this->assertNull(Branch::find($this->doomed->id));
    }

    /**
     * M4 stage 3 audit: the branch's foreign keys are ON DELETE CASCADE into the money tables. A branch that never had
     * an order may still have shifts, cash movements, booking deposits, banquet advances, tips, QR payments or pay
     * statements: deleting it would delete them without a word. Such a branch is made inactive instead.
     */
    public function test_a_branch_with_shift_or_money_history_and_no_order_is_not_deleted(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->doomed, DeviceType::POS);
        $shift = PosShift::create([
            'store_id' => $this->store->id, 'branch_id' => $this->doomed->id, 'device_id' => $terminal['device']->id, 'business_date' => now()->toDateString(),
            'status' => 'closed', 'opened_by' => $this->owner->id, 'opened_at' => now()->subHours(3), 'opening_cash' => '100000.00', 'closed_by' => $this->owner->id, 'closed_at' => now(),
        ]);
        $movement = CashMovement::create([
            'store_id' => $this->store->id, 'branch_id' => $this->doomed->id, 'shift_id' => $shift->id, 'type' => CashMovementType::OPENING, 'amount' => '100000.00', 'user_id' => $this->owner->id,
        ]);
        Device::whereKey($terminal['device']->id)->update(['revoked_at' => now()]); // nothing else is tied to the branch

        $this->deleteDoomed()->assertStatus(422)
            ->assertJsonPath('data.reason', 'branch_in_use')
            ->assertJsonPath('message', "Filialda kassa smenalari yoki pul harakatlari tarixi bor, uni o'chirib bo'lmaydi. O'rniga uni faol emas qiling.");

        $this->assertNotNull(Branch::find($this->doomed->id));
        $this->assertNotNull(PosShift::find($shift->id));
        $this->assertNotNull(CashMovement::find($movement->id));
    }

    public function test_the_older_reasons_still_come_first_and_carry_the_counts(): void
    {
        $this->makeStaff(UserRole::CASHIER, $this->doomed);
        $this->makeOrder(\App\Enums\OrderStatus::DELIVERED, ['branch_id' => $this->doomed->id]);

        $this->deleteDoomed()->assertStatus(422)
            ->assertJsonPath('message', "Filialda buyurtmalar bor, uni o'chirib bo'lmaydi. O'rniga uni faol emas qiling.")
            ->assertJsonPath('data.staff', 1);
    }
}
