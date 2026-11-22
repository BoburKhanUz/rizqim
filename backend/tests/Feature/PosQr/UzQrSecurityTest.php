<?php

namespace Tests\Feature\PosQr;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\QrPayments\UzQr\UzQrOperatorProtocol as Api;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Events\Broadcast\FiscalReceiptUpdatedBroadcast;
use App\Models\FiscalReceipt;
use App\Models\OrderBill;
use App\Models\PosQrPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review 12 (Wave P UzQR operator driver) — security. The first tests are the review's findings (each failed on
 * a96d70b8); test_sound_* are what the review checked and found to hold, kept as regression tests.
 */
class UzQrSecurityTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;
    use UzQrOperatorFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->openShift();
        $this->setUpOperator();
    }

    /**
     * MEDIUM: the operator call follows redirects (Guzzle default: up to 5, http and https allowed, only
     * Authorization/Cookie stripped cross-origin). An operator answer of 307 Location: http://other-host/ makes the
     * server re-send the till's X-Api-Key header (and, for 307/308, the JSON body with the STIR) in clear text to
     * any host: the https-only guard in UzQrOperatorGateway::baseUrl() is bypassed.
     */
    public function test_the_tills_key_never_follows_a_redirect_to_another_host_or_to_http(): void
    {
        $this->setTillKey()->assertOk();
        Http::fake(function (Request $request) {
            if (str_starts_with($request->url(), $this->base() . Api::PATH_CREATE)) {
                return Http::response('', 307, ['Location' => 'http://attacker.example.test/steal']);
            }

            return Http::response(['success' => true, 'data' => ['invoice_id' => 'x', 'qr' => 'y']]);
        });

        [, $bill] = $this->billed();
        $this->qr($bill, 'uzqr');

        $leaked = Http::recorded(fn (Request $request) => !str_starts_with($request->url(), 'https://uzqr-test.example.test/')
            && ($request->header(Api::AUTH_HEADER)[0] ?? null) === $this->tillKey);
        $this->assertCount(0, $leaked, 'the till key was re-sent to ' . ($leaked->first()[0] ?? null)?->url());
    }

    /**
     * LOW: fiscal_receipts.qr_url is sent to the operator (with the till's key, as the customer's receipt
     * link) without any check: not https, not the tax committee's receipt host, an internal address — all pass.
     */
    public function test_only_an_https_tax_receipt_link_is_sent_to_uzqr(): void
    {
        $qr = $this->paidQr();
        $this->fakeOperator([Api::PATH_RECEIPT => ['success' => true]]);

        event(new FiscalReceiptUpdatedBroadcast($this->issued($qr, 'http://169.254.169.254/latest/meta-data/iam/')));

        $this->assertSame(0, $this->countSent(Api::PATH_RECEIPT), 'a non-https internal URL went to the operator as the receipt link');
    }

    public function test_a_redirect_answer_is_a_refusal_and_offers_the_other_methods(): void
    {
        $this->setTillKey()->assertOk();
        $this->fakeOperator([Api::PATH_CREATE => Http::response('', 302, ['Location' => $this->base() . '/elsewhere'])]);
        [, $bill] = $this->billed();

        $this->qr($bill, 'uzqr')->assertStatus(409)->assertJsonPath('data.reason', 'provider_unavailable');
        $this->assertSame(1, Http::recorded()->count(), 'a redirect was followed');
        $this->assertSame(0, PosQrPayment::count());
    }

    public function test_receipt_links_of_another_host_or_with_credentials_are_refused_and_marked_failed(): void
    {
        $qr = $this->paidQr();
        $this->fakeOperator([Api::PATH_RECEIPT => ['success' => true]]);

        foreach (['https://ofd.soliq.uz.attacker.test/check?t=1', 'https://user:pw@ofd.soliq.uz/check?t=1', 'https://attacker.test/ofd.soliq.uz', 'ftp://ofd.soliq.uz/check'] as $link) {
            event(new FiscalReceiptUpdatedBroadcast($this->issued($qr, $link)));
        }
        $this->assertSame(0, $this->countSent(Api::PATH_RECEIPT));
        $this->assertSame('failed', $qr->refresh()->provider_payload['receipt_link']['status']);

        // The tax committee's own https link goes
        event(new FiscalReceiptUpdatedBroadcast($this->issued($qr, 'https://ofd.soliq.uz/check?t=EP000000000001&r=100&c=20261122120000&s=123456789012')));
        $this->assertSame(1, $this->countSent(Api::PATH_RECEIPT));
        $this->assertSame('sent', $qr->refresh()->provider_payload['receipt_link']['status']);
    }

    /**
     * LOW: a revoked till's key could never be wiped (the endpoint answered 404). It can be removed now (never set).
     */
    public function test_a_revoked_tills_key_can_be_removed_but_not_set(): void
    {
        $this->setTillKey()->assertOk();
        $device = $this->till['device'];
        $device->forceFill(['revoked_at' => now()])->save();

        $this->setTillKey($device, 'k-' . Str::random(20))->assertStatus(404);
        $this->assertSame($this->tillKey, $device->fresh()->uzqr_api_key);
        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$device->uuid}", ['remove_api_key' => true])->assertOk()->assertJsonPath('data.has_api_key', false);
        $this->assertNull($device->fresh()->uzqr_api_key);
        $this->assertDatabaseHas('audit_logs', ['action' => 'devices.uzqr_key_changed']);
    }

    /**
     * LOW: the key's last four characters and when it was set were in every Device serialisation.
     */
    public function test_no_serialisation_of_a_till_or_a_request_holds_the_key_or_its_traces(): void
    {
        $qr = $this->paidQr();
        $device = $this->till['device']->fresh();
        $this->assertNotNull($device->uzqr_api_key_last4);
        foreach (['uzqr_api_key', 'uzqr_api_key_last4', 'uzqr_api_key_set_at'] as $field) {
            $this->assertArrayNotHasKey($field, $device->toArray());
        }
        $this->assertSame($this->tillKey, $qr->uzqr_api_key);
        $this->assertArrayNotHasKey('uzqr_api_key', $qr->toArray());
        $this->assertStringNotContainsString($this->tillKey, (string) \Illuminate\Support\Facades\DB::table('pos_qr_payments')->where('id', $qr->id)->value('uzqr_api_key'));
    }

    // ---- sound ----

    public function test_sound_a_terminal_token_or_pin_session_never_reads_or_sets_a_key(): void
    {
        $this->setTillKey()->assertOk();
        $device = $this->till['device'];

        // The till's own device token (auth:sanctum accepts user tokens only)
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withBearer($this->till['token'])->getJson('/api/v1/owner/fiscal/uzqr')->assertStatus(401);
        $this->withBearer($this->till['token'])->putJson("/api/v1/owner/fiscal/uzqr/tills/{$device->uuid}", ['remove_api_key' => true])->assertStatus(401);

        // The owner's own PIN session on the till
        $this->givePin($this->owner, '7342');
        $ownerPin = $this->pinSession($this->till, $this->owner);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withBearer($ownerPin)->getJson('/api/v1/owner/fiscal/uzqr')->assertStatus(403);
        $this->withBearer($ownerPin)->putJson("/api/v1/owner/fiscal/uzqr/tills/{$device->uuid}", ['api_key' => 'attacker-key-123456'])->assertStatus(403);
        $this->assertSame($this->tillKey, $device->fresh()->uzqr_api_key);

        // Nothing the till reads holds the key (a new PIN session on the till replaced the cashier's)
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
        $this->fakeOperator([Api::PATH_CREATE => $this->createdAnswer(), Api::PATH_STATUS => $this->statusAnswer('pending')]);
        $boot = $this->pos('GET', '/bootstrap')->assertOk()->getContent();
        [, $bill] = $this->billed();
        $created = $this->qr($bill, 'uzqr')->assertCreated();
        $status = $this->pos('GET', '/qr/' . $created->json('data.qr.uuid'))->getContent();
        foreach ([$boot, $created->getContent(), $status] as $body) {
            $this->assertStringNotContainsString($this->tillKey, $body);
        }
    }

    public function test_sound_device_kind_state_and_branch_scope(): void
    {
        // A KDS terminal of the store is not a till
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS)['device'];
        $this->setTillKey($kds)->assertStatus(404);
        $this->assertNull($kds->fresh()->uzqr_api_key);

        // A revoked till
        $revoked = $this->pairTerminal($this->owner, $this->branch)['device'];
        $revoked->forceFill(['revoked_at' => now()])->save();
        $this->setTillKey($revoked)->assertStatus(404);

        // A store-wide manager role cannot; a branch-bound user with settings.manage on another branch cannot reach this branch's till
        $otherBranch = $this->makeBranch($this->store, 'Second');
        $otherTill = $this->pairTerminal($this->owner, $otherBranch)['device'];
        $this->owner('GET', '/owner/fiscal/uzqr?branch_id=' . $otherBranch->id, [], $this->manager)->assertStatus(403);
        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$otherTill->uuid}", ['api_key' => 'k-' . Str::random(20)], $this->manager)->assertStatus(403);
        $this->assertNull($otherTill->fresh()->uzqr_api_key);

        // Another store's till uuid: 404, and the other store's owner never sees ours
        $store2 = $this->makeStore('Other');
        $owner2 = $this->makeStaff(UserRole::OWNER, null, $store2);
        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$this->till['device']->uuid}", ['api_key' => 'k-' . Str::random(20)], $owner2)->assertStatus(404);
        $this->assertStringNotContainsString((string) $this->till['device']->uuid, $this->owner('GET', '/owner/fiscal/uzqr', [], $owner2)->getContent());
    }

    public function test_sound_a_key_with_a_header_breaking_character_is_refused(): void
    {
        foreach (["abcdefgh\r\nX-Evil: 1", "abcdefgh\nx", 'abcdéfghij', ['abcdefghij'], 12345678901] as $bad) {
            $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$this->till['device']->uuid}", ['api_key' => $bad])->assertStatus(422);
        }
        $this->assertNull($this->till['device']->fresh()->uzqr_api_key);
    }

    private function paidQr(): PosQrPayment
    {
        $this->setTillKey()->assertOk();
        $this->fakeOperator([Api::PATH_CREATE => $this->createdAnswer(), Api::PATH_STATUS => $this->paidAnswer()]);
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'uzqr')->assertCreated()->json('data.qr.uuid'));
        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.status', 'paid');

        return $qr->refresh();
    }

    private function issued(PosQrPayment $qr, string $link): FiscalReceipt
    {
        return \App\Domain\Fiscal\FiscalReceiptLock::outsideIssuerForTests(fn () => FiscalReceipt::forceCreate([
            'uuid' => (string) Str::uuid(),
            'store_id' => $qr->store_id,
            'branch_id' => $qr->branch_id,
            'device_id' => $qr->device_id,
            'type' => FiscalReceiptType::SALE,
            'payment_form' => 'card',
            'status' => FiscalReceiptStatus::ISSUED,
            'driver' => 'sandbox',
            'idempotency_key' => 'test:' . Str::uuid(),
            'subject_type' => OrderBill::FISCAL_SUBJECT,
            'subject_id' => $qr->bill_id,
            'total' => $qr->amount,
            'lines' => [],
            'payments' => [],
            'qr_url' => $link,
            'issued_at' => now(),
        ]));
    }
}
