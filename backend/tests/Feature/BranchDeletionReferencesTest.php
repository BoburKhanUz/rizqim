<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use App\Models\Device;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_the_older_reasons_still_come_first_and_carry_the_counts(): void
    {
        $this->makeStaff(UserRole::CASHIER, $this->doomed);
        $this->makeOrder(\App\Enums\OrderStatus::DELIVERED, ['branch_id' => $this->doomed->id]);

        $this->deleteDoomed()->assertStatus(422)
            ->assertJsonPath('message', "Filialda buyurtmalar bor, uni o'chirib bo'lmaydi. O'rniga uni faol emas qiling.")
            ->assertJsonPath('data.staff', 1);
    }

    public function test_the_panels_delete_actions_refuse_too(): void
    {
        $this->makeStaff(UserRole::COOK, $this->doomed);

        $this->actingAs($this->owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store);

        Livewire::test(EditBranch::class, ['record' => $this->doomed->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified("Filialni o'chirib bo'lmaydi");
        $this->assertNotNull(Branch::find($this->doomed->id));

        Livewire::test(ListBranches::class)
            ->callTableBulkAction('delete', [$this->doomed])
            ->assertNotified("Filialni o'chirib bo'lmaydi");
        $this->assertNotNull(Branch::find($this->doomed->id));

        // Nothing tied to it: the panel deletes it
        \App\Models\User::where('branch_id', $this->doomed->id)->update(['branch_id' => null]);
        Livewire::test(EditBranch::class, ['record' => $this->doomed->getRouteKey()])
            ->callAction(DeleteAction::class);
        $this->assertNull(Branch::find($this->doomed->id));
    }
}
