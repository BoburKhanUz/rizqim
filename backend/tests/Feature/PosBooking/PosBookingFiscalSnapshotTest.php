<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Models\CustomerAccount;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-26 on P3-17's money with real receipts (FiscalService, the sandbox driver): a deposit, its refund and a nasiya
 * repayment answer with the receipt as registered (`fiscal.receipt`: fiscal sign, number, QR, terminal, lines,
 * payments) so the till prints it; a cashier opens or changes an account with a manager's approval; a pre-ordered dish
 * is added by its variant's uuid.
 */
class PosBookingFiscalSnapshotTest extends TestCase
{
    use PosBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();

        // Real receipts: the sandbox registers them at once
        $this->app->forgetInstance(FiscalIssuer::class);
        $this->app->forgetInstance(FiscalLineBuilder::class);
        $this->assertInstanceOf(FiscalService::class, app(FiscalIssuer::class));
        $this->store->forceFill([
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => [
                    'deposit' => ['mxik' => '10202002001000003'],
                    'credit' => ['mxik' => '10202002001000004'],
                ],
                'deposit_refund_receipt' => true,
            ],
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $fiscal
     */
    private function assertPrintable(array $fiscal, string $type, string $total, bool $signed): void
    {
        $this->assertSame('issued', $fiscal['status']);
        $receipt = $fiscal['receipt'];
        $this->assertIsArray($receipt);
        $this->assertSame($fiscal['receipt_uuid'], $receipt['uuid']);
        $this->assertSame($type, $receipt['type']);
        $this->assertSame('issued', $receipt['status']);
        $this->assertSame($total, $receipt['total']);
        $this->assertTrue($receipt['non_fiscal'], 'sandbox receipts have no legal force');
        // Sale and refund receipts carry the fiscal sign and the QR; advance and credit receipts have neither (VM 943)
        $this->assertSame($signed, $receipt['fiscal_sign'] !== null);
        $this->assertSame($signed, $receipt['qr_url'] !== null);
        $this->assertNotEmpty($receipt['receipt_number']);
        $this->assertNotEmpty($receipt['terminal_id']);
        $this->assertNotNull($receipt['issued_at']);
        $this->assertCount(1, $receipt['lines']);
        $this->assertSame($total, $receipt['lines'][0]['amount']);
        $this->assertSame($total, Money::sum(...array_column($receipt['payments'], 'amount'))->toString());
        $this->assertArrayNotHasKey('provider_payload', $receipt);
        $this->assertArrayNotHasKey('driver', $receipt);
    }

    public function test_a_deposit_and_its_refund_answer_with_their_receipts(): void
    {
        $uuid = $this->booked();
        $taken = $this->takeDeposit($uuid, '500000', 'cash')->assertCreated();
        $this->assertPrintable($taken->json('data.fiscal'), 'advance', '500000.00', false);
        $this->assertSame('cash', $taken->json('data.fiscal.receipt.payment_form'));
        $this->assertSame($taken->json('data.payment.fiscal_receipt_uuid'), $taken->json('data.fiscal.receipt_uuid'));

        $payment = $taken->json('data.payment.uuid');
        $refund = $this->api($this->manager, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits/{$payment}/refund", ['reason' => "Mehmon bekor qildi"])->assertOk();
        $this->assertPrintable($refund->json('data.fiscal'), 'refund', '500000.00', true);
        $this->assertSame($taken->json('data.fiscal.receipt_uuid'), $refund->json('data.fiscal.receipt.original_receipt_uuid'));

        // The till reads it again (pos.take_payment of the branch)
        $this->api($this->cashier, 'GET', '/api/v1/pos/fiscal-receipts/' . $refund->json('data.fiscal.receipt_uuid'))->assertOk()
            ->assertJsonPath('data.type', 'refund')
            ->assertJsonPath('data.fiscal_sign', $refund->json('data.fiscal.receipt.fiscal_sign'));
    }

    public function test_a_repayment_answers_with_its_credit_receipt(): void
    {
        $account = (string) $this->api($this->manager, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Akmal aka', 'credit_limit' => '2000000'])->assertCreated()->json('data.uuid');
        app(CustomerAccountLedger::class)->charge($account, Money::of('300000'), (string) Str::uuid(), (int) $this->manager->id);

        $response = $this->api($this->cashier, 'POST', "/api/v1/pos/customer-accounts/{$account}/repayments", ['amount' => '120000', 'tender' => 'card', 'card_type' => 'humo', 'transaction_id' => '771'], ['Idempotency-Key' => 'repay-snap'])
            ->assertCreated();
        $this->assertPrintable($response->json('data.fiscal'), 'credit', '120000.00', false);
        $this->assertSame('card', $response->json('data.fiscal.receipt.payments.0.form'));
        $this->assertSame($response->json('data.entry.fiscal_receipt_uuid'), $response->json('data.fiscal.receipt_uuid'));
    }

    public function test_a_cashier_opens_and_changes_an_account_with_a_managers_approval(): void
    {
        $this->api($this->cashier, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Bobur'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'customer_accounts.manage');

        $created = $this->api($this->cashier, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Bobur', 'approval_token' => $this->approvals->grant((int) $this->manager->id, 'customer_accounts.manage')])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $this->branch->id);
        $uuid = (string) $created->json('data.uuid');
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_account.created']);

        $this->api($this->cashier, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['credit_limit' => '900000'])->assertForbidden();
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['credit_limit' => '900000', 'approval_token' => $this->approvals->grant((int) $this->manager->id, 'customer_accounts.manage')])
            ->assertOk()->assertJsonPath('data.credit_limit', '900000.00');
        $this->assertSame('900000.00', (string) CustomerAccount::where('uuid', $uuid)->value('credit_limit'));

        // A token for another permission does not do
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['note' => 'x', 'approval_token' => $this->approvals->grant((int) $this->manager->id, 'orders.cancel')])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_invalid');
    }

    public function test_a_pre_ordered_dish_by_its_variant_uuid(): void
    {
        $uuid = $this->booked();
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['product_variant_uuid' => $this->variant->uuid, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_variant_id', $this->variant->id)
            ->assertJsonPath('data.items.0.product_variant_uuid', $this->variant->uuid);

        $foreign = \App\Models\ProductVariant::create([
            'product_id' => \App\Models\Product::create(['store_id' => $this->foreignStore->id, 'category_id' => $this->product->category_id, 'name' => 'Lavash', 'slug' => 'lavash-' . Str::random(5), 'status' => 'active'])->id,
            'name' => 'Katta', 'price' => 30000, 'stock' => 5, 'is_active' => true, 'uuid' => (string) Str::uuid(),
        ]);
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['product_variant_uuid' => $foreign->uuid, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonPath('data.reason', 'validation')->assertJsonValidationErrors(['product_variant_uuid']);
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['quantity' => 1])->assertUnprocessable();
    }
}
