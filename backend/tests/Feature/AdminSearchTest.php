<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Support\PiiMask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-11: GET /admin/search: stores, orders, users and team members, at most 5 each, only the types the member may see;
 * phones masked unless admin.pii.view, and every reveal audited.
 */
class AdminSearchTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill(['name' => 'Choyxona Navro\'z', 'tin' => '305123456', 'phone' => '+998901112233'])->save();
        $this->customer->forceFill(['name' => 'Navro\'za Karimova', 'phone' => '+998935556677'])->save();
    }

    public function test_hits_by_type_with_admin_app_paths(): void
    {
        $superadmin = $this->makeMember(attributes: ['name' => 'Navruz Admin']);
        $order = $this->makeOrder(OrderStatus::NEW);
        $token = $this->signIn($superadmin);

        $hits = collect($this->adminJson('GET', '/search?q=navr', $token)->assertOk()->json('data'))->groupBy('type');
        $this->assertSame('/stores/' . $this->store->id, $hits['store'][0]['href']);
        $this->assertStringContainsString('STIR 305123456', $hits['store'][0]['subtitle']);
        $this->assertSame('/users/' . $this->customer->id, $hits['user'][0]['href']);
        $this->assertSame('/team/' . $superadmin->id, $hits['member'][0]['href']);

        $this->adminJson('GET', '/search?q=3051234', $token)->assertJsonPath('data.0.type', 'store');
        $this->adminJson('GET', '/search?q=93555', $token)->assertJsonPath('data.0.href', '/users/' . $this->customer->id);

        $byCode = $this->adminJson('GET', '/search?q=' . urlencode('#' . $order->short_code), $token)->json('data');
        $this->assertSame('/orders/' . $order->uuid, collect($byCode)->firstWhere('type', 'order')['href']);
        $byUuid = $this->adminJson('GET', '/search?q=' . substr((string) $order->uuid, 0, 8), $token)->json('data');
        $this->assertSame((string) $order->uuid, collect($byUuid)->firstWhere('type', 'order')['id']);

        $this->adminJson('GET', '/search?q=n', $token)->assertStatus(422);
    }

    public function test_at_most_five_per_type(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->makeStore("Navro'z {$i}");
        }
        $token = $this->signIn($this->makeMember());

        $stores = collect($this->adminJson('GET', '/search?q=navro', $token)->json('data'))->where('type', 'store');
        $this->assertCount(5, $stores);
    }

    public function test_types_follow_permissions_and_phones_are_masked_without_pii_view(): void
    {
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $sales = $this->makeMember(PlatformRole::SALES);

        $supportHits = collect($this->adminJson('GET', '/search?q=navr', $this->signIn($support))->json('data'));
        $this->assertEqualsCanonicalizing(['store', 'user'], $supportHits->pluck('type')->unique()->values()->all());
        $user = $supportHits->firstWhere('type', 'user');
        $this->assertStringContainsString('+998 93 *** ** 77', $user['subtitle']);
        $this->assertStringNotContainsString('556', $user['subtitle']);

        $salesHits = collect($this->adminJson('GET', '/search?q=navr', $this->signIn($sales))->json('data'));
        $this->assertSame(['store'], $salesHits->pluck('type')->unique()->values()->all());
        $this->assertStringContainsString('+998 90 *** ** 33', $salesHits->first()['subtitle']);

        $this->assertSame(0, AuditLog::where('action', 'admin.pii.revealed')->count());
    }

    public function test_pii_view_reveals_the_phone_and_the_reveal_is_audited(): void
    {
        $superadmin = $this->makeMember();
        $token = $this->signIn($superadmin);

        $user = collect($this->adminJson('GET', '/search?q=navr', $token)->json('data'))->firstWhere('type', 'user');
        $this->assertStringContainsString('+998 93 555 66 77', $user['subtitle']);

        $entry = AuditLog::where('action', 'admin.pii.revealed')->sole();
        $this->assertSame($superadmin->id, $entry->user_id);
        $this->assertContains(['type' => 'user', 'id' => $this->customer->id, 'field' => 'phone'], $entry->payload_json['revealed']);
        $this->assertSame('admin.search', $entry->payload_json['route']);
    }

    public function test_the_mask_helper(): void
    {
        $this->assertSame('+998 90 *** ** 67', PiiMask::phone('+998901234567'));
        $this->assertSame('+998 90 *** ** 67', PiiMask::phone('998 90 123 45 67'));
        $this->assertSame('90 *** ** 67', PiiMask::phone('901234567'));
        $this->assertSame('8600 **** **** 1234', PiiMask::card('8600 1111 2222 1234'));
        $this->assertNull(PiiMask::phone(null));
        $this->assertSame('+998 90 123 45 67', PiiMask::formatPhone('+998901234567'));
    }
}
