<?php

namespace Tests\Feature\AdminMarketing;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\PromoCode;
use App\Services\Promo\InvalidPromoCodeException;
use App\Services\Promo\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-31: Marketing -> Promo-kodlar: permissions, the order flow's own rules, usage statistics, the lock on a used code,
 * switching off, deleting, reasons and audit.
 */
class AdminMarketingPromoCodeTest extends TestCase
{
    use RefreshDatabase;
    use AdminMarketingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
    }

    private function useCode(PromoCode $code, OrderStatus $status, string $discount, string $total, ?int $userId = null): void
    {
        $this->makeOrder($status, ['promo_code_id' => $code->id, 'user_id' => $userId ?? $this->customer->id, 'discount' => $discount, 'total_price' => $total]);
    }

    public function test_permissions_viewer_reads_but_cannot_act_and_finance_sees_nothing(): void
    {
        $code = $this->makeCode();
        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $finance = $this->signIn($this->makeMember(PlatformRole::FINANCE));

        $this->adminJson('GET', '/marketing/promo-codes', $viewer)->assertOk()->assertJsonPath('data.data.0.id', $code->id);
        $this->adminJson('GET', "/marketing/promo-codes/{$code->id}", $viewer)->assertOk();

        $body = ['code' => 'YANGI', 'type' => 'percentage', 'value' => 10, 'reason' => 'Sinov'];
        $this->adminJson('POST', '/marketing/promo-codes', $viewer, $body)->assertForbidden();
        $this->adminJson('PATCH', "/marketing/promo-codes/{$code->id}", $viewer, ['usage_limit' => 5, 'reason' => 'Sinov'])->assertForbidden();
        $this->adminJson('POST', "/marketing/promo-codes/{$code->id}/deactivate", $viewer, ['reason' => 'Sinov'])->assertForbidden();
        $this->adminJson('DELETE', "/marketing/promo-codes/{$code->id}", $viewer, ['reason' => 'Sinov'])->assertForbidden();
        $this->assertTrue($code->refresh()->is_active);

        $this->adminJson('GET', '/marketing/promo-codes', $finance)->assertForbidden();
    }

    public function test_create_normalizes_the_code_audits_and_matches_what_the_order_flow_gives(): void
    {
        $member = $this->makeMember();
        $token = $this->signIn($member);

        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'yangi10', 'type' => 'percentage', 'value' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $response = $this->adminJson('POST', '/marketing/promo-codes', $token, [
            'code' => 'yangi 10', 'type' => 'percentage', 'value' => '10', 'min_order_amount' => '50000',
            'max_discount_amount' => '8000', 'usage_limit' => 100, 'per_user_limit' => 1, 'reason' => 'Bahor aksiyasi',
        ]);
        // A space is not a code character
        $response->assertStatus(422)->assertJsonValidationErrors('code');

        $response = $this->adminJson('POST', '/marketing/promo-codes', $token, [
            'code' => 'yangi10', 'type' => 'percentage', 'value' => '10', 'min_order_amount' => '50000',
            'max_discount_amount' => '8000', 'usage_limit' => 100, 'per_user_limit' => 1, 'reason' => 'Bahor aksiyasi',
        ])->assertCreated();

        $response->assertJsonPath('data.code', 'YANGI10')->assertJsonPath('data.value', '10.00')
            ->assertJsonPath('data.min_order_amount', '50000.00')->assertJsonPath('data.max_discount_amount', '8000.00')
            ->assertJsonPath('data.status', 'active')->assertJsonPath('data.locked', false)
            ->assertJsonPath('data.stats.uses', 0)->assertJsonPath('data.stats.gmv', '0.00');

        $audit = AuditLog::where('action', 'admin.marketing.promo_codes.created')->sole();
        $this->assertSame($member->id, (int) $audit->user_id);
        $this->assertSame('Bahor aksiyasi', $audit->payload_json['reason']);
        // The code itself stays readable in the journal (the generic redactor would hide a key called "code")
        $this->assertSame('YANGI10', $audit->after['promo']);

        // The order flow reads the same row: 10 % of 100 000 is capped at 8 000; below the minimum it refuses
        $service = app(PromoCodeService::class);
        $promo = $service->find('yangi10');
        $this->assertSame('8000.00', $service->discountFor($promo, $this->store->id, '100000', $this->customer->id)->toString());
        $this->expectException(InvalidPromoCodeException::class);
        $service->discountFor($promo, $this->store->id, '40000', $this->customer->id);
    }

    public function test_fixed_code_and_value_rules(): void
    {
        $token = $this->signIn($this->makeMember());
        $base = ['code' => 'FIX5000', 'type' => 'fixed', 'value' => 5000, 'reason' => 'Sinov'];

        $created = $this->adminJson('POST', '/marketing/promo-codes', $token, $base + ['max_discount_amount' => 1000])->assertCreated();
        // A cap means nothing for a fixed code (the order flow ignores it): it is not kept
        $created->assertJsonPath('data.max_discount_amount', null)->assertJsonPath('data.value', '5000.00');
        $promo = app(PromoCodeService::class)->find('fix5000');
        $this->assertSame('5000.00', app(PromoCodeService::class)->discountFor($promo, $this->store->id, '30000', null)->toString());

        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'FIX2', 'type' => 'fixed', 'value' => '5000.50', 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_value');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'PCT', 'type' => 'percentage', 'value' => 101, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_value');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'PCT', 'type' => 'percentage', 'value' => 0, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'PCT', 'type' => 'percentage', 'value' => 12.5, 'reason' => 'Sinov'])
            ->assertCreated()->assertJsonPath('data.value', '12.50');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'AB', 'type' => 'percentage', 'value' => 5, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'BADTYPE', 'type' => 'gift', 'value' => 5, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('type');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'LIM', 'type' => 'fixed', 'value' => 1000, 'usage_limit' => 0, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('usage_limit');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'PAST', 'type' => 'fixed', 'value' => 1000, 'expires_at' => now()->subDay()->toIso8601String(), 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('expires_at');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'STORE', 'type' => 'fixed', 'value' => 1000, 'store_id' => 99999999, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('store_id');
        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'STORE', 'type' => 'fixed', 'value' => 1000, 'store_id' => $this->store->id, 'expires_at' => now()->addDays(3)->toIso8601String(), 'reason' => 'Sinov'])
            ->assertCreated()->assertJsonPath('data.store.id', $this->store->id);
    }

    public function test_the_code_must_be_unique_ignoring_case(): void
    {
        $this->makeCode(['code' => 'BAHOR']);
        $token = $this->signIn($this->makeMember());

        $this->adminJson('POST', '/marketing/promo-codes', $token, ['code' => 'bahor', 'type' => 'fixed', 'value' => 1000, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $other = $this->makeCode(['code' => 'YOZ']);
        $this->adminJson('PATCH', "/marketing/promo-codes/{$other->id}", $token, ['code' => 'Bahor', 'reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('code');
        // Its own code again is fine
        $this->adminJson('PATCH', "/marketing/promo-codes/{$other->id}", $token, ['code' => 'yoz', 'reason' => 'Sinov'])->assertOk();
    }

    public function test_statistics_count_the_orders_the_limits_count(): void
    {
        $code = $this->makeCode(['usage_limit' => 3]);
        $second = $this->otherCustomer();
        $this->useCode($code, OrderStatus::DELIVERED, '5000', '45000');
        $this->useCode($code, OrderStatus::NEW, '5000', '45000', $second->id);
        $this->useCode($code, OrderStatus::NEW, '5000', '50000', $second->id);
        // Cancelled and refunded orders have given the code back
        $this->useCode($code, OrderStatus::CANCELED, '7000', '30000');
        $this->useCode($code, OrderStatus::REFUNDED, '7000', '30000');
        $unused = $this->makeCode();
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $rows = collect($this->adminJson('GET', '/marketing/promo-codes', $token)->assertOk()->json('data.data'))->keyBy('id');
        $this->assertSame(['uses' => 3, 'unique_customers' => 2, 'discount_total' => '15000.00', 'gmv' => '140000.00'], $rows[$code->id]['stats']);
        $this->assertSame('exhausted', $rows[$code->id]['status']);
        $this->assertTrue($rows[$code->id]['locked']);
        $this->assertSame(['uses' => 0, 'unique_customers' => 0, 'discount_total' => '0.00', 'gmv' => '0.00'], $rows[$unused->id]['stats']);
        $this->assertFalse($rows[$unused->id]['locked']);

        $card = $this->adminJson('GET', "/marketing/promo-codes/{$code->id}", $token)->assertOk();
        $card->assertJsonPath('data.stats.uses', 3)->assertJsonCount(5, 'data.recent_orders')->assertJsonPath('data.recent_orders.0.status', 'refunded');
        $this->assertNotEmpty($card->json('data.recent_orders.0.uuid'));
        $this->adminJson('GET', '/marketing/promo-codes/99999', $token)->assertNotFound();
    }

    public function test_filters_by_status_search_and_store(): void
    {
        $active = $this->makeCode(['code' => 'AKTIV']);
        $off = $this->makeCode(['code' => 'OCHIQ', 'is_active' => false]);
        $expired = $this->makeCode(['code' => 'ESKI', 'expires_at' => now()->subDay()]);
        $full = $this->makeCode(['code' => 'TUGAGAN', 'usage_limit' => 1]);
        $this->useCode($full, OrderStatus::NEW, '1000', '9000');
        $mine = $this->makeCode(['code' => 'MENIKI', 'store_id' => $this->store->id]);
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $ids = fn (string $query) => collect($this->adminJson('GET', '/marketing/promo-codes' . $query, $token)->assertOk()->json('data.data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$active->id, $mine->id], $ids('?status=active'));
        $this->assertSame([$off->id], $ids('?status=inactive'));
        $this->assertSame([$expired->id], $ids('?status=expired'));
        $this->assertSame([$full->id], $ids('?status=exhausted'));
        $this->assertSame([$active->id], $ids('?q=akt'));
        $this->assertSame([$mine->id], $ids('?store_id=' . $this->store->id));
        $this->adminJson('GET', '/marketing/promo-codes?status=what', $token)->assertStatus(422);

        $this->assertSame('MENIKI', $this->adminJson('GET', '/marketing/promo-codes?sort=code&q=meni', $token)->json('data.data.0.code'));
        $this->assertSame(5, $this->adminJson('GET', '/marketing/promo-codes?per_page=2', $token)->json('data.total'));
        $this->assertSame(3, $this->adminJson('GET', '/marketing/promo-codes?per_page=2', $token)->json('data.last_page'));
    }

    public function test_a_used_code_keeps_its_value_but_its_limits_stay_editable(): void
    {
        $code = $this->makeCode(['code' => 'ISHLATILGAN', 'value' => '10.00']);
        $this->useCode($code, OrderStatus::DELIVERED, '5000', '45000');
        $token = $this->signIn($this->makeMember());

        foreach ([['value' => 20], ['type' => 'fixed'], ['code' => 'BOSHQA'], ['store_id' => $this->store->id], ['max_discount_amount' => 5000]] as $change) {
            $response = $this->adminJson('PATCH', "/marketing/promo-codes/{$code->id}", $token, $change + ['reason' => 'Sinov'])
                ->assertStatus(422)->assertJsonPath('code', 'promo_code_used');
            $this->assertSame(array_keys($change), $response->json('data.fields'));
        }
        $code->refresh();
        $this->assertSame('ISHLATILGAN', $code->code);
        $this->assertEquals(10.0, $code->value);

        // The same value again is no change; the limits, the minimum, the expiry and on/off are free
        $this->adminJson('PATCH', "/marketing/promo-codes/{$code->id}", $token, ['value' => '10.00', 'code' => 'ishlatilgan', 'type' => 'percentage', 'usage_limit' => 50, 'per_user_limit' => 2, 'min_order_amount' => 20000, 'expires_at' => now()->addMonth()->toIso8601String(), 'reason' => 'Limitni oshirish'])
            ->assertOk()->assertJsonPath('data.usage_limit', 50)->assertJsonPath('data.min_order_amount', '20000.00')->assertJsonPath('data.locked', true);

        $audit = AuditLog::where('action', 'admin.marketing.promo_codes.updated')->sole();
        $this->assertNull($audit->before['usage_limit']);
        $this->assertSame(50, $audit->after['usage_limit']);
        $this->assertSame('Limitni oshirish', $audit->payload_json['reason']);
    }

    public function test_an_unused_code_can_change_its_value(): void
    {
        $code = $this->makeCode(['code' => 'YANGI', 'value' => '10.00']);
        $token = $this->signIn($this->makeMember());

        $this->adminJson('PATCH', "/marketing/promo-codes/{$code->id}", $token, ['value' => 15])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('PATCH', "/marketing/promo-codes/{$code->id}", $token, ['value' => 150, 'reason' => 'Sinov'])->assertStatus(422)->assertJsonPath('code', 'invalid_value');
        $this->adminJson('PATCH', "/marketing/promo-codes/{$code->id}", $token, ['value' => 15, 'type' => 'fixed', 'reason' => 'Sinov'])->assertOk()->assertJsonPath('data.type', 'fixed')->assertJsonPath('data.value', '15.00');
        $this->adminJson('PATCH', '/marketing/promo-codes/99999', $token, ['value' => 15, 'reason' => 'Sinov'])->assertNotFound();
    }

    public function test_switching_a_code_off_stops_the_order_flow_at_once(): void
    {
        $code = $this->makeCode(['code' => 'TEZKOR']);
        $this->useCode($code, OrderStatus::DELIVERED, '5000', '45000');
        $token = $this->signIn($this->makeMember());
        $service = app(PromoCodeService::class);

        $this->adminJson('POST', "/marketing/promo-codes/{$code->id}/deactivate", $token)->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('POST', "/marketing/promo-codes/{$code->id}/deactivate", $token, ['reason' => 'Suiiste\'mol'])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.status', 'inactive');

        $this->expectedRefusal(fn () => $service->discountFor($service->find('tezkor'), $this->store->id, '100000', null));
        $audit = AuditLog::where('action', 'admin.marketing.promo_codes.deactivated')->sole();
        $this->assertTrue($audit->before['is_active']);
        $this->assertFalse($audit->after['is_active']);

        $this->adminJson('POST', "/marketing/promo-codes/{$code->id}/activate", $token, ['reason' => 'Xato edi'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertNotNull($service->discountFor($service->find('tezkor'), $this->store->id, '100000', null));

        // Already on: nothing to audit
        $count = AuditLog::where('action', 'admin.marketing.promo_codes.activated')->count();
        $this->adminJson('POST', "/marketing/promo-codes/{$code->id}/activate", $token, ['reason' => 'Yana'])->assertOk();
        $this->assertSame($count, AuditLog::where('action', 'admin.marketing.promo_codes.activated')->count());
    }

    public function test_delete_needs_a_step_up_and_only_an_unused_code_goes(): void
    {
        $unused = $this->makeCode();
        $used = $this->makeCode();
        $this->useCode($used, OrderStatus::CANCELED, '1000', '9000');
        $member = $this->makeMember();
        $token = $this->signIn($member);

        $this->adminJson('DELETE', "/marketing/promo-codes/{$unused->id}", $token, ['reason' => 'Keraksiz'])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($member, $token);
        $this->adminJson('DELETE', "/marketing/promo-codes/{$unused->id}", $token)->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('DELETE', "/marketing/promo-codes/{$used->id}", $token, ['reason' => 'Keraksiz'])->assertStatus(409)->assertJsonPath('code', 'promo_code_used');
        $this->assertModelExists($used);

        $this->adminJson('DELETE', "/marketing/promo-codes/{$unused->id}", $token, ['reason' => 'Keraksiz'])->assertOk();
        $this->assertModelMissing($unused);
        $audit = AuditLog::where('action', 'admin.marketing.promo_codes.deleted')->sole();
        $this->assertSame($unused->code, $audit->before['promo']);
    }

    /**
     * Review C#5: checkout locks the promo_codes row (PromoCodeService::find(..., true)) while it places an order with
     * it; editing a locked field or deleting the code takes the same row lock before it asks whether any order used
     * it, so an order placed meanwhile is either seen (refused) or waits for the edit to commit.
     */
    public function test_edit_and_delete_lock_the_code_before_they_look_for_its_orders(): void
    {
        $code = $this->makeCode(['code' => 'POYGA', 'value' => '10.00']);
        $codes = app(\App\Services\Admin\Marketing\PromoCodes::class);

        $queries = [];
        $base = \Illuminate\Support\Facades\DB::transactionLevel();
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries, $base) {
            $queries[] = [strtolower($query->sql), \Illuminate\Support\Facades\DB::transactionLevel() > $base];
        });

        foreach ([fn () => $codes->update($code->fresh(), ['value' => '15']), fn () => $codes->delete($code->fresh())] as $call) {
            $queries = [];
            $call();

            $lock = array_key_first(array_filter($queries, fn (array $query) => str_contains($query[0], 'from "promo_codes"') && str_contains($query[0], 'for update')));
            $orders = array_key_first(array_filter($queries, fn (array $query) => str_contains($query[0], 'from "orders"') && str_contains($query[0], 'promo_code_id')));
            $this->assertNotNull($lock, 'the promo_codes row is not locked');
            $this->assertNotNull($orders);
            $this->assertLessThan($orders, $lock, 'the row is locked only after the orders were counted');
            $this->assertTrue($queries[$lock][1] && $queries[$orders][1], 'not inside one transaction');
        }
        $this->assertModelMissing($code);
    }

    private function expectedRefusal(callable $call): void
    {
        try {
            $call();
        } catch (InvalidPromoCodeException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('The order flow still accepted the code.');
    }
}
