<?php

namespace Tests\Feature;

use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Filament\Pages\StoreFinance;
use App\Filament\Superadmin\Resources\PayoutRequests\Pages\EditPayoutRequest;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::forceCreate([
            'name' => 'Payout Cafe',
            'slug' => 'payout-cafe',
            'status' => 'active',
            'wallet_balance' => 300000,
            'min_payout_reserve' => 100000,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'phone' => '+998904440000',
            'role' => UserRole::OWNER->value,
            'store_id' => $this->store->id,
            'password' => bcrypt('password'),
        ]);
    }

    private function requestPayout(float $amount, string $card = '8600123456789012', ?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->postJson('/api/v1/owner/payouts', [
            'amount' => $amount,
            'card_number' => $card,
        ]);
    }

    private function pendingPayout(float $amount): PayoutRequest
    {
        return PayoutRequest::create([
            'store_id' => $this->store->id,
            'amount' => $amount,
            'card_number' => '8600123456789012',
            'status' => 'pending',
        ]);
    }

    public function test_pending_payouts_count_against_the_available_balance(): void
    {
        // wallet 300k - reserve 100k = 200k withdrawable in total
        $this->requestPayout(150000)->assertOk();
        $this->requestPayout(100000)->assertStatus(422)->assertJsonPath('success', false);
        $this->requestPayout(50000)->assertOk();
        $this->requestPayout(1000)->assertStatus(422);

        $this->assertEquals(200000, (float) PayoutRequest::where('store_id', $this->store->id)->where('status', 'pending')->sum('amount'));
    }

    public function test_card_number_must_be_sixteen_digits(): void
    {
        $this->requestPayout(10000, '8600 1234 5678 9012')->assertOk();
        $this->assertDatabaseHas('payout_requests', ['card_number' => '8600123456789012']);

        $this->requestPayout(10000, '8600-1234-5678-9012')->assertStatus(422);
        $this->requestPayout(10000, '86001234567890123')->assertStatus(422);
        $this->requestPayout(10000, '860012345678901a')->assertStatus(422);
    }

    public function test_only_owners_can_use_finance_endpoints(): void
    {
        $manager = User::create([
            'name' => 'Manager',
            'phone' => '+998904440001',
            'role' => UserRole::MANAGER->value,
            'store_id' => $this->store->id,
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($manager)->getJson('/api/v1/owner/finance')->assertStatus(403);
        $this->requestPayout(10000, user: $manager)->assertStatus(403);
        $this->actingAs($manager)->postJson('/api/v1/owner/finance/top-up', ['amount' => 50000, 'provider' => 'click'])
            ->assertStatus(403);

        $this->assertSame(0, PayoutRequest::count());
    }

    public function test_approving_twice_from_stale_copies_debits_once(): void
    {
        $payout = $this->pendingPayout(100000);
        $firstAdmin = PayoutRequest::findOrFail($payout->id);
        $secondAdmin = PayoutRequest::findOrFail($payout->id);

        $firstAdmin->update(['status' => 'completed']);

        try {
            $secondAdmin->update(['status' => 'completed']);
            $this->fail('A second approval must be refused.');
        } catch (\DomainException) {
        }

        $this->assertEquals(200000, (float) $this->store->fresh()->wallet_balance);
        $this->assertSame(1, StoreWalletTransaction::where('store_id', $this->store->id)
            ->where('type', StoreTransactionType::PAYOUT->value)->count());
        $this->assertSame('completed', $payout->fresh()->status);
    }

    public function test_superadmin_panel_debits_once_and_reports_refused_approvals(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'phone' => '+998904440009',
            'role' => UserRole::SUPER_ADMIN->value,
            'password' => bcrypt('password'),
        ]);
        $payout = $this->pendingPayout(100000);

        Filament::setCurrentPanel('superadmin');
        $this->actingAs($admin);

        // Two tabs opened while the request was still pending.
        $firstTab = Livewire::test(EditPayoutRequest::class, ['record' => $payout->getRouteKey()]);
        $secondTab = Livewire::test(EditPayoutRequest::class, ['record' => $payout->getRouteKey()]);

        $firstTab->fillForm(['status' => 'completed'])->call('save')->assertHasNoFormErrors();
        $secondTab->fillForm(['status' => 'completed'])->call('save');

        $this->assertEquals(200000, (float) $this->store->fresh()->wallet_balance);
        $this->assertSame(1, StoreWalletTransaction::where('store_id', $this->store->id)
            ->where('type', StoreTransactionType::PAYOUT->value)->count());
        $this->assertSame($admin->id, $payout->fresh()->processed_by);

        // An approval the wallet cannot cover is refused on the page instead of overdrawing or erroring.
        $tooLarge = $this->pendingPayout(250000);
        Livewire::test(EditPayoutRequest::class, ['record' => $tooLarge->getRouteKey()])
            ->fillForm(['status' => 'completed'])
            ->call('save')
            ->assertNotified('Xatolik');

        $this->assertSame('pending', $tooLarge->fresh()->status);
        $this->assertEquals(200000, (float) $this->store->fresh()->wallet_balance);
    }

    public function test_owner_panel_payout_action_applies_the_same_locked_rules_as_the_api(): void
    {
        $this->actingAs($this->owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store->fresh());

        Livewire::test(StoreFinance::class)
            ->callAction('requestPayout', data: ['amount' => 150000, 'card_number' => '8600 1234 5678 9012'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('payout_requests', [
            'store_id' => $this->store->id,
            'amount' => 150000,
            'card_number' => '8600123456789012',
            'status' => 'pending',
        ]);

        // Pending requests count against what is left (300k - 150k pending - 100k reserve = 50k).
        Livewire::test(StoreFinance::class)
            ->callAction('requestPayout', data: ['amount' => 100000, 'card_number' => '8600123456789012'])
            ->assertHasActionErrors(['amount']);

        Livewire::test(StoreFinance::class)
            ->callAction('requestPayout', data: ['amount' => 10000, 'card_number' => '8600-1234-5678-901a'])
            ->assertHasActionErrors(['card_number']);

        // The page's figures can be stale (the wallet just dropped): the locked check still refuses.
        Store::whereKey($this->store->id)->update(['wallet_balance' => 160000]);
        Livewire::test(StoreFinance::class)
            ->callAction('requestPayout', data: ['amount' => 50000, 'card_number' => '8600123456789012'])
            ->assertNotified('Xatolik');

        $this->assertSame(1, PayoutRequest::count());
    }

    public function test_processed_payout_cannot_be_reopened_and_approved_again(): void
    {
        $payout = $this->pendingPayout(100000);
        $payout->update(['status' => 'completed']);

        try {
            $payout->update(['status' => 'pending']);
            $this->fail('A processed payout must not be reopened.');
        } catch (\DomainException) {
        }

        $this->assertSame('completed', $payout->fresh()->status);
        $this->assertEquals(200000, (float) $this->store->fresh()->wallet_balance);
    }

    public function test_approval_never_overdraws_the_wallet(): void
    {
        $payout = $this->pendingPayout(100000);
        $this->store->forceFill(['wallet_balance' => 50000])->save();

        try {
            $payout->update(['status' => 'completed']);
            $this->fail('Approval must be refused when the wallet cannot cover it.');
        } catch (\DomainException) {
        }

        $this->assertEquals(50000, (float) $this->store->fresh()->wallet_balance);
        $this->assertSame('pending', $payout->fresh()->status);

        $payout->refresh()->update(['status' => 'rejected', 'resolution_notes' => 'Mablag\' yetarli emas']);
        $this->assertSame('rejected', $payout->fresh()->status);
        $this->assertEquals(50000, (float) $this->store->fresh()->wallet_balance);
    }
}
