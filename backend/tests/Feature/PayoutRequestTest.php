<?php

namespace Tests\Feature;

use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
