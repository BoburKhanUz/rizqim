<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\PayoutRequest;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P2-30 (security): no finance or payout response carries a full card number. POST /owner/payouts used to answer the
 * stored PayoutRequest model and GET /owner/finance listed the models (and the wallet notes "Payout completed to card:
 * <number>"); both now send the masked shape of GET /owner/payouts. GET /owner/finance is finance.view.
 */
class PayoutCardMaskingTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    private const CARD = '8600123456789012';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpManagement();
        $this->store->forceFill(['wallet_balance' => 500000, 'min_payout_reserve' => 100000])->save();
    }

    /**
     * Every 13-19 digit run (spaces or dashes allowed) in a response: none may be there.
     */
    private function assertNoCardNumber(TestResponse $response): void
    {
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString(self::CARD, $content);
        $this->assertStringNotContainsString('8600 1234 5678 9012', $content);
        $this->assertDoesNotMatchRegularExpression('/(?<!\d)\d(?:[ -]?\d){12,18}(?!\d)/', $content, 'a card-like number left the API');
    }

    public function test_requesting_a_payout_answers_the_masked_card_only(): void
    {
        $response = $this->as($this->owner, 'POST', '/api/v1/owner/payouts', [
            'amount' => 150000,
            'card_number' => '8600 1234 5678 9012',
            'notes' => 'Kartam 8600-1234-5678-9012 ga',
        ])->assertOk()
            ->assertJsonPath('data.card', '8600 **** **** 9012')
            ->assertJsonPath('data.card_number', '8600 **** **** 9012')
            ->assertJsonPath('data.amount', '150000.00')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.notes', 'Kartam 8600 **** **** 9012 ga');
        $this->assertNoCardNumber($response);
        $this->assertSame(
            ['id', 'amount', 'card', 'status', 'notes', 'resolution_notes', 'processed_at', 'created_at', 'store_id', 'card_number', 'processed_by', 'updated_at'],
            array_keys($response->json('data')),
        );

        // The full number is stored for the platform (it pays the card out), never sent back
        $this->assertDatabaseHas('payout_requests', ['card_number' => self::CARD]);
    }

    public function test_the_finance_overview_masks_payouts_and_card_numbers_in_notes(): void
    {
        $payout = PayoutRequest::create(['store_id' => $this->store->id, 'amount' => 120000, 'card_number' => self::CARD, 'status' => 'pending', 'notes' => 'Karta: ' . self::CARD]);
        StoreWalletTransaction::create([
            'store_id' => $this->store->id, 'type' => 'payout', 'amount' => '-50000.00', 'balance_before' => '550000.00', 'balance_after' => '500000.00',
            'notes' => 'Payout completed to card: ' . self::CARD,
        ]);
        StoreTransaction::create([
            'store_id' => $this->store->id, 'type' => 'adjustment', 'amount' => '1000.00', 'balance_before' => '0.00', 'balance_after' => '1000.00',
            'notes' => 'Tuzatish, karta 8600 1234 5678 9012',
        ]);

        $response = $this->as($this->owner, 'GET', '/api/v1/owner/finance')->assertOk()
            ->assertJsonPath('data.payout_requests.0.id', $payout->id)
            ->assertJsonPath('data.payout_requests.0.card', '8600 **** **** 9012')
            ->assertJsonPath('data.payout_requests.0.card_number', '8600 **** **** 9012')
            ->assertJsonPath('data.payout_requests.0.notes', 'Karta: 8600 **** **** 9012')
            ->assertJsonPath('data.wallet_transactions.0.notes', 'Payout completed to card: 8600 **** **** 9012')
            ->assertJsonPath('data.ledger_transactions.0.notes', 'Tuzatish, karta 8600 **** **** 9012');
        $this->assertNoCardNumber($response);
    }

    public function test_no_finance_endpoint_ever_sends_a_card_number(): void
    {
        $this->as($this->owner, 'POST', '/api/v1/owner/payouts', ['amount' => 100000, 'card_number' => self::CARD])->assertOk();
        $payout = PayoutRequest::firstOrFail();
        // The platform pays it out (Filament): the wallet row notes the card
        $this->actingAs($this->makeStaff(UserRole::SUPER_ADMIN));
        $payout->update(['status' => 'completed', 'resolution_notes' => 'Yuborildi: ' . self::CARD]);

        foreach (['/api/v1/owner/finance', '/api/v1/owner/payouts', '/api/v1/owner/finance/wallet', '/api/v1/owner/finance/ledger', '/api/v1/owner/payments'] as $uri) {
            $this->assertNoCardNumber($this->as($this->owner, 'GET', $uri)->assertOk());
        }
        $this->assertSame('Yuborildi: 8600 **** **** 9012', $this->as($this->owner, 'GET', '/api/v1/owner/payouts')->json('data.data.0.resolution_notes'));
        $this->assertStringContainsString(self::CARD, (string) StoreWalletTransaction::firstOrFail()->notes);
    }

    public function test_the_finance_overview_is_finance_view(): void
    {
        foreach ([$this->manager, $this->courier, $this->customer, $this->makeStaff(UserRole::CASHIER, $this->branch)] as $user) {
            $this->as($user, 'GET', '/api/v1/owner/finance')->assertForbidden();
        }
        $this->bearerJson('GET', '/api/v1/owner/finance', $this->pinSession($this->owner))->assertForbidden()->assertJsonPath('data.reason', 'staff_session_not_allowed');

        // Granted to managers: they read the balances; payouts and top-ups stay the owner's
        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::FINANCE_VIEW, true);
        $this->as($this->manager, 'GET', '/api/v1/owner/finance')->assertOk()->assertJsonPath('data.wallet_balance', 500000);
        $this->as($this->manager, 'POST', '/api/v1/owner/payouts', ['amount' => 100000, 'card_number' => self::CARD])->assertForbidden();
        $this->as($this->manager, 'POST', '/api/v1/owner/finance/top-up', ['amount' => 50000, 'provider' => 'click'])->assertForbidden();
        $this->bearerJson('GET', '/api/v1/owner/finance', $this->pinSession($this->manager))->assertForbidden();

        // Another store's owner sees their own store
        $this->as($this->foreignOwner, 'GET', '/api/v1/owner/finance')->assertOk()->assertJsonPath('data.wallet_balance', 0)->assertJsonCount(0, 'data.payout_requests');
    }
}
