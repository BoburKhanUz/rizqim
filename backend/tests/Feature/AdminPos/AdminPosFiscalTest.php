<?php

namespace Tests\Feature\AdminPos;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\FiscalReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-24 Fiskal cheklar: failed and pending receipts of every store, errors per provider, and the retry.
 */
class AdminPosFiscalTest extends TestCase
{
    use RefreshDatabase;
    use AdminPosFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
        $this->product->forceFill(['mxik_code' => self::OSH_MXIK])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeReceipt(string $status, array $attributes = []): FiscalReceipt
    {
        return FiscalReceipt::forceCreate(array_merge([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'type' => 'sale',
            'payment_form' => 'cash',
            'status' => $status,
            'driver' => 'sandbox',
            'idempotency_key' => bin2hex(random_bytes(8)),
            'total' => '50000.00',
            'lines' => [],
            'payments' => [],
            'error' => null,
            'attempts' => 0,
        ], $attributes));
    }

    public function test_the_list_shows_open_receipts_with_reasons_and_the_errors_per_provider(): void
    {
        $failed = $this->makeReceipt('failed', ['error' => "Soliq serveri rad etdi: MXIK noto'g'ri", 'attempts' => 5, 'created_at' => now()->subDays(12)]);
        $pending = $this->makeReceipt('pending', ['attempts' => 1, 'next_attempt_at' => now()->addMinutes(5)]);
        $issued = $this->makeReceipt('issued', ['issued_at' => now()]);
        $other = $this->makeReceipt('failed', ['driver' => 'didox', 'error' => 'Timeout', 'store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id]);
        $this->makeReceipt('failed', ['driver' => 'didox', 'error' => 'Timeout 2', 'store_id' => $this->foreignStore->id, 'branch_id' => null]);
        $external = $this->makeReceipt('pending', ['driver' => 'external', 'provider' => 'payme']);

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $response = $this->adminJson('GET', '/pos/fiscal-receipts', $token)->assertOk();

        // The default is every open (failed or pending) receipt, however old
        $this->assertNotContains($issued->uuid, array_column($response->json('data.data'), 'uuid'));
        $this->assertContains($failed->uuid, array_column($response->json('data.data'), 'uuid'));
        $this->assertSame(5, $response->json('data.total'));
        $this->assertSame(
            ['failed' => 3, 'pending' => 2, 'by_driver' => [
                ['driver' => 'didox', 'failed' => 2, 'pending' => 0, 'last_error' => 'Timeout 2'],
                ['driver' => 'sandbox', 'failed' => 1, 'pending' => 1, 'last_error' => "Soliq serveri rad etdi: MXIK noto'g'ri"],
                ['driver' => 'external', 'failed' => 0, 'pending' => 1, 'last_error' => null],
            ]],
            $response->json('data.summary'),
        );

        $row = collect($response->json('data.data'))->firstWhere('uuid', $failed->uuid);
        $this->assertSame(['failed', 'sandbox', 5, true, 'Lifecycle Burger', 'Main Branch', '50000.00'], [$row['status'], $row['driver'], $row['attempts'], $row['retryable'], $row['store']['name'], $row['branch']['name'], $row['total']]);
        $this->assertStringContainsString('MXIK', $row['error']);
        $this->assertFalse(collect($response->json('data.data'))->firstWhere('uuid', $external->uuid)['retryable']);

        $ids = fn (string $query) => array_column($this->adminJson('GET', '/pos/fiscal-receipts' . $query, $token)->assertOk()->json('data.data'), 'uuid');
        $this->assertSame([$failed->uuid], $ids('?status=failed&driver=sandbox'));
        $this->assertSame([$pending->uuid], $ids('?status=pending&driver=sandbox'));
        $this->assertSame([$issued->uuid], $ids('?status=issued'));
        $this->assertCount(6, $ids('?status=all'));
        $this->assertContains($other->uuid, $ids("?store_id={$this->foreignStore->id}"));
        $this->assertSame([$failed->uuid], $ids('?from=' . now()->subDays(13)->toDateString() . '&to=' . now()->subDays(11)->toDateString()));
        $this->assertSame([$pending->uuid], $ids('?q=' . substr($pending->uuid, 0, 23)));
        $this->adminJson('GET', '/pos/fiscal-receipts?status=bogus', $token)->assertStatus(422);
    }

    public function test_the_card_hides_the_providers_raw_answer(): void
    {
        $receipt = $this->makeReceipt('failed', [
            'error' => str_repeat('Uzun xato. ', 60), 'provider_payload' => ['token' => 'secret-token'],
            'lines' => [['name' => 'Osh', 'mxik_code' => self::OSH_MXIK, 'quantity' => '1', 'amount' => '50000.00']],
        ]);

        $token = $this->signIn($this->makeMember(PlatformRole::SALES));
        $data = $this->adminJson('GET', "/pos/fiscal-receipts/{$receipt->uuid}", $token)->assertOk()->json('data');

        $this->assertLessThanOrEqual(300, mb_strlen($data['error']));
        $this->assertSame(str_repeat('Uzun xato. ', 60), $data['full_error']);
        $this->assertSame('Osh', $data['lines'][0]['name']);
        $this->assertStringNotContainsString('secret-token', json_encode($data));
        $this->assertArrayNotHasKey('provider_payload', $data);

        $this->adminJson('GET', '/pos/fiscal-receipts/' . \Illuminate\Support\Str::uuid(), $token)->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_retry_sends_a_failed_receipt_again_with_a_reason_and_an_audit_entry(): void
    {
        $this->fiscalMode($this->store);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'sandbox' => ['reject' => true]]])->save();
        $failed = $this->issuer()->issue($this->document(key: 'retry-me'));
        $this->assertSame(FiscalReceiptStatus::FAILED, $this->receipt($failed->receiptUuid)->status);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true]])->save();

        // Operators read only
        $this->adminJson('POST', "/pos/fiscal-receipts/{$failed->receiptUuid}/retry", $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR)), ['reason' => 'Qayta'])->assertForbidden();

        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->signIn($sales);
        $this->adminJson('POST', "/pos/fiscal-receipts/{$failed->receiptUuid}/retry", $token, ['reason' => 'Qayta'])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($sales, $token);
        $this->adminJson('POST', "/pos/fiscal-receipts/{$failed->receiptUuid}/retry", $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->adminJson('POST', "/pos/fiscal-receipts/{$failed->receiptUuid}/retry", $token, ['reason' => "MXIK tuzatildi"])->assertOk()
            ->assertJsonPath('message', "Chek ro'yxatdan o'tdi.")
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.retryable', false);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $this->receipt($failed->receiptUuid)->status);

        $audit = AuditLog::where('action', 'admin.pos.fiscal_receipt_retried')->sole();
        $this->assertSame([$sales->id, $this->store->id, 'failed', 'issued'], [$audit->user_id, $audit->store_id, $audit->before['status'], $audit->after['status']]);
        $this->assertSame('MXIK tuzatildi', $audit->payload_json['reason']);

        // Issued: nothing to retry
        $this->adminJson('POST', "/pos/fiscal-receipts/{$failed->receiptUuid}/retry", $token, ['reason' => 'Yana'])->assertStatus(422)->assertJsonPath('code', 'already_issued');
        $external = $this->makeReceipt('pending', ['driver' => 'external', 'provider' => 'click']);
        $this->adminJson('POST', "/pos/fiscal-receipts/{$external->uuid}/retry", $token, ['reason' => 'Click'])->assertStatus(422)->assertJsonPath('code', 'already_issued');
        $this->adminJson('POST', '/pos/fiscal-receipts/' . \Illuminate\Support\Str::uuid() . '/retry', $token, ['reason' => 'Yo\'q'])->assertNotFound();
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.fiscal_receipt_retried')->count());
    }

    public function test_a_retry_the_provider_still_refuses_says_why(): void
    {
        $this->fiscalMode($this->store, ['sandbox' => ['fail_attempts' => 9]]);
        $pending = $this->issuer()->issue($this->document(key: 'still-failing'));

        $this->adminJson('POST', "/pos/fiscal-receipts/{$pending->receiptUuid}/retry", $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN)), ['reason' => 'Tekshirish'])->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('message', "Chek hali ro'yxatdan o'tmadi: Sinov provayderi javob bermadi (2-urinish).");
    }

    public function test_it_needs_devices_view(): void
    {
        $receipt = $this->makeReceipt('failed');

        $this->adminJson('GET', '/pos/fiscal-receipts', $this->signIn($this->makeMember(PlatformRole::FINANCE)))->assertForbidden();
        $this->adminJson('GET', "/pos/fiscal-receipts/{$receipt->uuid}", $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertForbidden();
        $this->adminJson('GET', '/pos/fiscal-receipts')->assertUnauthorized();
    }
}
