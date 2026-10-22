<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FiscalReceipt;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-11: fiscal settings through /owner/fiscal/settings and /owner/settings, the product fiscal fields of the menu
 * API, the readiness list, and the receipts list and retry for the web panel: permissions and store / branch isolation.
 */
class FiscalApiTest extends TestCase
{
    use RefreshDatabase;
    use FiscalFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    public function test_the_owner_reads_and_saves_fiscal_settings(): void
    {
        $data = $this->as($this->owner, 'GET', '/api/v1/owner/fiscal/settings')->assertOk()->json('data');
        $this->assertNull($data['tin']);
        $this->assertFalse($data['fiscal_settings']['enabled']);
        $this->assertSame('sandbox', $data['fiscal_settings']['driver']);
        $this->assertSame('null', $data['fiscal_settings']['active_driver']);
        $this->assertSame('10202002004000001', $data['fiscal_settings']['charges']['deposit']['mxik']);
        $this->assertNull($data['fiscal_settings']['charges']['room_fee']['mxik']);
        $this->assertSame(['sandbox', 'null'], $data['drivers']);
        $this->assertSame(['vat', '12'], [$data['tax_regimes'][0]['value'], $data['tax_regimes'][0]['vat_rate']]);

        // Fiscal mode needs the TIN and the tax regime
        $this->as($this->owner, 'PUT', '/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['enabled' => true]])
            ->assertStatus(422)
            ->assertJsonPath('errors.tin.0', "Fiskal rejimni yoqish uchun do'kon STIRini kiriting.")
            ->assertJsonPath('errors.tax_regime.0', 'Fiskal rejimni yoqish uchun soliq rejimini tanlang.');

        $this->as($this->owner, 'PUT', '/api/v1/owner/fiscal/settings', [
            'tin' => '301234567',
            'tax_regime' => 'vat_simplified',
            'fiscal_settings' => [
                'enabled' => true,
                'charges' => ['room_fee' => ['mxik' => self::ROOM_MXIK, 'unit' => 'soat'], 'deposit' => ['name' => 'Bron avansi'], 'delivery' => ['mxik' => '10202002002000001']],
                'sandbox' => ['fail_attempts' => 1],
            ],
        ])->assertOk()
            ->assertJsonPath('message', 'Fiskal sozlamalar saqlandi.')
            ->assertJsonPath('data.tin', '301234567')
            ->assertJsonPath('data.fiscal_settings.active_driver', 'sandbox')
            ->assertJsonPath('data.fiscal_settings.charges.room_fee.mxik', self::ROOM_MXIK)
            ->assertJsonPath('data.fiscal_settings.charges.room_fee.unit', 'soat')
            ->assertJsonPath('data.fiscal_settings.charges.deposit.name', 'Bron avansi')
            ->assertJsonPath('data.fiscal_settings.charges.deposit.mxik', '10202002004000001')
            ->assertJsonPath('data.fiscal_settings.charges.delivery.mxik', '10202002002000001')
            ->assertJsonPath('data.fiscal_settings.sandbox.fail_attempts', 1);
        $this->assertSame('vat_simplified', $this->store->refresh()->tax_regime);
        $this->assertSame(1, AuditLog::where('action', 'settings.fiscal_changed')->count());

        // Partial updates keep the rest; a null charge field goes back to the default
        $this->as($this->owner, 'PUT', '/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['charges' => ['deposit' => ['name' => null]]]])
            ->assertOk()
            ->assertJsonPath('data.fiscal_settings.enabled', true)
            ->assertJsonPath('data.fiscal_settings.charges.room_fee.mxik', self::ROOM_MXIK)
            ->assertJsonPath('data.fiscal_settings.charges.deposit.name', 'Avans (bron)');

        // The booking deposit receipt choices P3-17 reads (null: its default)
        $this->as($this->owner, 'PUT', '/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['deposit_refund_receipt' => true]])
            ->assertOk()
            ->assertJsonPath('data.fiscal_settings.deposit_refund_receipt', true)
            ->assertJsonPath('data.fiscal_settings.retained_deposit_receipt', null);
        $this->assertTrue($this->store->refresh()->fiscal_settings['deposit_refund_receipt']);
        $this->as($this->owner, 'PUT', '/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['deposit_refund_receipt' => null]])->assertOk();
        $this->assertArrayNotHasKey('deposit_refund_receipt', $this->store->refresh()->fiscal_settings);

        $errors = $this->as($this->owner, 'PUT', '/api/v1/owner/fiscal/settings', [
            'tin' => '12345',
            'tax_regime' => 'patent',
            'fiscal_settings' => ['driver' => 'acme', 'charges' => ['room_fee' => ['mxik' => '1020'], 'delivery' => ['package_code' => 'X1'], 'tips' => ['mxik' => self::ROOM_MXIK]], 'sandbox' => ['latency_ms' => 99999]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.tin.0', "STIR 9 ta raqamdan iborat bo'lishi kerak.")
            ->assertJsonPath('errors.tax_regime.0', "Soliq rejimi noto'g'ri: vat, vat_simplified yoki turnover.")
            ->assertJsonValidationErrors(['fiscal_settings.charges.tips', 'fiscal_settings.sandbox.latency_ms'])
            ->json('errors');
        $this->assertSame("Fiskal drayver noto'g'ri.", $errors['fiscal_settings.driver'][0]);
        $this->assertSame("MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.", $errors['fiscal_settings.charges.room_fee.mxik'][0]);
        $this->assertSame("Qadoq kodi faqat raqamlardan iborat bo'lishi kerak (ko'pi bilan 20 ta).", $errors['fiscal_settings.charges.delivery.package_code'][0]);
        $this->assertSame('301234567', $this->store->refresh()->tin);
    }

    public function test_fiscal_settings_need_settings_manage_and_no_terminal_session(): void
    {
        $this->as($this->manager, 'GET', '/api/v1/owner/fiscal/settings')->assertForbidden();
        $this->as($this->manager, 'PUT', '/api/v1/owner/fiscal/settings', ['tin' => '301234567'])->assertForbidden();
        $this->as($this->customer, 'GET', '/api/v1/owner/fiscal/settings')->assertForbidden();
        $this->as(null, 'GET', '/api/v1/owner/fiscal/settings')->assertUnauthorized();

        $token = $this->pinSession($this->owner);
        $this->bearerJson('GET', '/api/v1/owner/fiscal/settings', $token)->assertForbidden();
        $this->bearerJson('PUT', '/api/v1/owner/fiscal/settings', $token, ['tin' => '301234567'])->assertForbidden();
        $this->assertNull($this->store->refresh()->tin);

        // Another store's owner changes their own store only
        $this->as($this->foreignOwner, 'PUT', '/api/v1/owner/fiscal/settings', ['tin' => '309999999'])->assertOk();
        $this->assertNull($this->store->refresh()->tin);
        $this->assertSame('309999999', $this->foreignStore->refresh()->tin);
    }

    public function test_the_store_settings_endpoint_carries_the_fiscal_fields(): void
    {
        $base = ['is_open' => true, 'payment_settings' => ['cash' => ['enabled' => true]]];

        $this->as($this->owner, 'GET', '/api/v1/owner/settings')->assertOk()
            ->assertJsonPath('data.tin', null)
            ->assertJsonPath('data.fiscal_settings.enabled', false);

        $this->as($this->owner, 'POST', '/api/v1/owner/settings', $base + ['tin' => '30123456X'])
            ->assertStatus(422)->assertJsonPath('errors.tin.0', "STIR 9 ta raqamdan iborat bo'lishi kerak.");
        $this->as($this->owner, 'POST', '/api/v1/owner/settings', $base + ['tin' => '301234567', 'tax_regime' => 'turnover', 'fiscal_settings' => ['enabled' => true]])->assertOk();

        $this->as($this->owner, 'GET', '/api/v1/owner/settings')->assertOk()
            ->assertJsonPath('data.tin', '301234567')
            ->assertJsonPath('data.tax_regime', 'turnover')
            ->assertJsonPath('data.fiscal_settings.enabled', true);

        // Left out: kept (older clients never send them); the store a staff member gets elsewhere has no fiscal settings
        $this->as($this->owner, 'POST', '/api/v1/owner/settings', $base)->assertOk();
        $this->assertSame('301234567', $this->store->refresh()->tin);
        $user = $this->as($this->owner, 'GET', '/api/v1/user')->assertOk()->json('data.store');
        $this->assertArrayNotHasKey('fiscal_settings', $user);
        $this->assertSame('301234567', $user['tin']);
    }

    public function test_the_menu_api_takes_and_returns_the_fiscal_fields(): void
    {
        $variants = json_encode([['name' => '1 kg', 'price' => 90000, 'unit' => 'kg', 'unit_label' => 'kg (guruch)']]);

        $created = $this->as($this->owner, 'POST', '/api/v1/owner/products', [
            'name' => 'Qozonda osh', 'category_id' => $this->product->category_id, 'is_active' => true, 'variants' => $variants,
            'kind' => 'service', 'mxik_code' => '10202002007000001', 'package_code' => '1500211', 'vat_rate' => '12',
        ])->assertOk()->json('data');
        $this->assertSame(['service', '10202002007000001', '1500211', '12.00'], [$created['kind'], $created['mxik_code'], $created['package_code'], $created['vat_rate']]);
        $this->assertSame('kg (guruch)', $created['variants'][0]['unit_label']);

        $listed = collect($this->as($this->owner, 'GET', '/api/v1/owner/products')->assertOk()->json('data'))->firstWhere('id', $created['id']);
        $this->assertSame('10202002007000001', $listed['mxik_code']);

        // An update that leaves the fields out keeps them; an empty value clears one
        $variantId = $created['variants'][0]['id'];
        $this->as($this->owner, 'POST', "/api/v1/owner/products/{$created['id']}", [
            'name' => 'Qozonda osh', 'category_id' => $this->product->category_id, 'variants' => json_encode([['id' => $variantId, 'name' => '1 kg', 'price' => 95000]]),
        ])->assertOk();
        $product = Product::with('variants')->findOrFail($created['id']);
        $this->assertSame(['10202002007000001', 'kg (guruch)'], [$product->mxik_code, $product->variants[0]->unit_label]);

        $this->as($this->owner, 'POST', "/api/v1/owner/products/{$created['id']}", [
            'name' => 'Qozonda osh', 'category_id' => $this->product->category_id, 'variants' => json_encode([['id' => $variantId, 'name' => '1 kg', 'price' => 95000, 'unit_label' => '']]),
            'vat_rate' => '', 'kind' => 'dish',
        ])->assertOk();
        $product->refresh()->load('variants');
        $this->assertSame(['dish', null, null], [$product->kind->value, $product->vat_rate, $product->variants[0]->unit_label]);

        $this->as($this->owner, 'POST', '/api/v1/owner/products', [
            'name' => 'X', 'category_id' => $this->product->category_id, 'variants' => $variants,
            'kind' => 'drink', 'mxik_code' => '1020200100', 'package_code' => 'AB-1', 'vat_rate' => 120,
        ])->assertStatus(422)
            ->assertJsonPath('errors.kind.0', "Mahsulot turi noto'g'ri: dish (taom) yoki service (xizmat).")
            ->assertJsonPath('errors.mxik_code.0', "MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.")
            ->assertJsonPath('errors.package_code.0', "Qadoq kodi faqat raqamlardan iborat bo'lishi kerak (ko'pi bilan 20 ta).")
            ->assertJsonPath('errors.vat_rate.0', "QQS stavkasi 0 dan 100 gacha bo'lishi kerak.");
    }

    public function test_the_readiness_list_shows_what_blocks_fiscal_selling(): void
    {
        $this->product->forceFill(['mxik_code' => self::OSH_MXIK])->save();
        $missing = Product::create(['store_id' => $this->store->id, 'category_id' => $this->product->category_id, 'name' => 'Choy', 'slug' => 'choy-' . uniqid(), 'status' => 'active']);
        Product::create(['store_id' => $this->foreignStore->id, 'category_id' => $this->product->category_id, 'name' => 'Foreign', 'slug' => 'f-' . uniqid(), 'status' => 'active']);
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        $this->as($cashier, 'GET', '/api/v1/owner/fiscal/readiness')->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.tin_set', false)
            ->assertJsonPath('data.products_missing_mxik.0.id', $missing->id)
            ->assertJsonCount(1, 'data.products_missing_mxik')
            ->assertJsonPath('data.charges_missing_mxik', ['room_fee', 'place_fee', 'min_spend_topup', 'service_charge', 'delivery']);
        $this->as($this->customer, 'GET', '/api/v1/owner/fiscal/readiness')->assertForbidden();
    }

    private function seedReceipts(): array
    {
        $this->fiscalMode($this->store);
        $this->fiscalMode($this->foreignStore);
        $this->product->forceFill(['mxik_code' => self::OSH_MXIK])->save();

        $mainSale = $this->issuer()->issue($this->document(key: 'main-sale'));
        $other = new \App\Domain\Fiscal\FiscalDocument(FiscalReceiptType::SALE, $this->store->id, $this->otherBranch->id, null, [$this->line('5000')], [$this->cash('5000')], \App\Support\Money::of(5000), 'other-sale');
        $otherSale = $this->issuer()->issue($other);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'sandbox' => ['reject' => true]]])->save();
        $failed = $this->issuer()->issue($this->document(FiscalReceiptType::ADVANCE, [$this->line('20000', '10202002004000001')], key: 'main-advance'));
        $foreign = $this->issuer()->issue($this->document(key: 'foreign', store: $this->foreignStore));
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true]])->save();

        return [$mainSale->receiptUuid, $otherSale->receiptUuid, $failed->receiptUuid, $foreign->receiptUuid];
    }

    public function test_the_receipts_list_is_the_stores_own_and_filters(): void
    {
        [$mainSale, $otherSale, $failed, $foreign] = $this->seedReceipts();

        $page = $this->as($this->owner, 'GET', '/api/v1/owner/fiscal/receipts')->assertOk()->json('data');
        $this->assertSame([$failed, $otherSale, $mainSale], array_column($page['data'], 'uuid'));
        $this->assertSame(3, $page['total']);
        $first = $page['data'][2];
        $this->assertSame(['sale', 'issued', 'cash', 'sandbox', true, '100000.00', '10714.29'], [$first['type'], $first['status'], $first['payment_form'], $first['driver'], $first['non_fiscal'], $first['total'], $first['vat_total']]);
        $this->assertSame(self::OSH_MXIK, $first['lines'][0]['mxik_code']);
        $this->assertArrayNotHasKey('provider_payload', $first);

        $this->assertSame([$failed], array_column($this->as($this->owner, 'GET', '/api/v1/owner/fiscal/receipts?status=failed')->assertOk()->json('data.data'), 'uuid'));
        $this->assertSame([$failed], array_column($this->as($this->owner, 'GET', '/api/v1/owner/fiscal/receipts?type=advance')->assertOk()->json('data.data'), 'uuid'));
        $this->assertSame([$otherSale], array_column($this->as($this->owner, 'GET', "/api/v1/owner/fiscal/receipts?branch_id={$this->otherBranch->id}")->assertOk()->json('data.data'), 'uuid'));
        $this->assertSame([$failed, $mainSale], array_column($this->as($this->owner, 'GET', '/api/v1/owner/fiscal/receipts?subject_type=order&subject_id=1')->assertOk()->json('data.data'), 'uuid'));

        FiscalReceipt::where('uuid', $mainSale)->update(['created_at' => now()->subDays(3)]);
        $today = now()->setTimezone('Asia/Tashkent')->format('Y-m-d');
        $this->assertSame([$failed, $otherSale], array_column($this->as($this->owner, 'GET', "/api/v1/owner/fiscal/receipts?from={$today}&to={$today}")->assertOk()->json('data.data'), 'uuid'));

        $this->as($this->owner, 'GET', '/api/v1/owner/fiscal/receipts?status=lost&type=x&from=2026-13-01&branch_id=99999999999999999999')
            ->assertStatus(422)->assertJsonValidationErrors(['status', 'type', 'from', 'branch_id']);
        $this->as($this->owner, 'GET', "/api/v1/owner/fiscal/receipts?branch_id={$this->foreignBranch->id}")->assertNotFound();

        // One receipt; another store's is not found
        $this->as($this->owner, 'GET', "/api/v1/owner/fiscal/receipts/{$mainSale}")->assertOk()->assertJsonPath('data.uuid', $mainSale);
        $this->as($this->owner, 'GET', "/api/v1/owner/fiscal/receipts/{$foreign}")->assertNotFound()->assertJsonPath('message', 'Chek topilmadi.');
        $this->as($this->owner, 'GET', '/api/v1/owner/fiscal/receipts/not-a-uuid')->assertNotFound();
        $this->as($this->foreignOwner, 'GET', '/api/v1/owner/fiscal/receipts')->assertOk()->assertJsonPath('data.data.0.uuid', $foreign)->assertJsonPath('data.total', 1);
    }

    public function test_branch_staff_see_their_branchs_receipts_only(): void
    {
        [$mainSale, $otherSale, $failed] = $this->seedReceipts();
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        // The manager reads through reports.view, bound to their branch
        $this->assertSame([$failed, $mainSale], array_column($this->as($this->manager, 'GET', '/api/v1/owner/fiscal/receipts')->assertOk()->json('data.data'), 'uuid'));
        $this->as($this->manager, 'GET', "/api/v1/owner/fiscal/receipts?branch_id={$this->otherBranch->id}")->assertForbidden();
        $this->as($this->manager, 'GET', "/api/v1/owner/fiscal/receipts/{$otherSale}")->assertForbidden();
        $this->as($this->manager, 'GET', "/api/v1/owner/fiscal/receipts/{$mainSale}")->assertOk();

        // The owner on a branch terminal (PIN session) acts in that branch
        $token = $this->pinSession($this->owner);
        $this->assertSame([$failed, $mainSale], array_column($this->bearerJson('GET', '/api/v1/owner/fiscal/receipts', $token)->assertOk()->json('data.data'), 'uuid'));

        $this->as($cashier, 'GET', '/api/v1/owner/fiscal/receipts')->assertForbidden();
        $this->as($this->courier, 'GET', "/api/v1/owner/fiscal/receipts/{$mainSale}")->assertForbidden();
    }

    public function test_the_owner_retries_a_failed_receipt(): void
    {
        [$mainSale, , $failed, $foreign] = $this->seedReceipts();

        $this->as($this->manager, 'POST', "/api/v1/owner/fiscal/receipts/{$failed}/retry")->assertForbidden();
        $this->bearerJson('POST', "/api/v1/owner/fiscal/receipts/{$failed}/retry", $this->pinSession($this->owner))->assertForbidden();
        $this->as($this->owner, 'POST', "/api/v1/owner/fiscal/receipts/{$foreign}/retry")->assertNotFound();
        $this->as($this->owner, 'POST', "/api/v1/owner/fiscal/receipts/{$mainSale}/retry")
            ->assertStatus(422)->assertJsonPath('data.reason', 'already_issued');

        $this->as($this->owner, 'POST', "/api/v1/owner/fiscal/receipts/{$failed}/retry")->assertOk()
            ->assertJsonPath('message', "Chek ro'yxatdan o'tdi.")
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.fiscal_sign', null) // an advance has none
            ->assertJsonPath('data.attempts', 2);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $this->receipt($failed)->status);
        $this->assertSame(1, AuditLog::where('action', 'fiscal.receipt_retried')->count());

        // Still failing: the answer says why
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'sandbox' => ['fail_attempts' => 9]]])->save();
        $pending = $this->issuer()->issue($this->document(key: 'retry-pending'));
        $this->as($this->owner, 'POST', "/api/v1/owner/fiscal/receipts/{$pending->receiptUuid}/retry")->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('message', "Chek hali ro'yxatdan o'tmadi: Sinov provayderi javob bermadi (2-urinish).");
    }
}
