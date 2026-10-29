<?php

namespace Tests\Feature\AdminMarketing;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P4-31: Marketing -> Bannerlar va aksiyalar: permissions, the image on the public disk, the schedule, the order, the
 * preview of what the Mini App shows, step-up on delete, reasons and audit.
 */
class AdminMarketingBannerTest extends TestCase
{
    use RefreshDatabase;
    use AdminMarketingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->setUpLifecycleStore();
    }

    public function test_permissions_viewer_reads_but_cannot_act_and_finance_sees_nothing(): void
    {
        $promo = $this->makePromo();
        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $finance = $this->signIn($this->makeMember(PlatformRole::FINANCE));

        $this->adminJson('GET', '/marketing/promos', $viewer)->assertOk()->assertJsonPath('data.data.0.id', $promo->id);
        $this->adminJson('GET', '/marketing/promos/preview', $viewer)->assertOk();
        $this->adminJson('GET', '/marketing/lookup', $viewer)->assertOk();

        $this->adminForm('/marketing/promos', $viewer, ['title' => 'Yangi', 'image' => $this->png(), 'reason' => 'Sinov'])->assertForbidden();
        $this->adminForm("/marketing/promos/{$promo->id}", $viewer, ['title' => 'Boshqa', 'reason' => 'Sinov'])->assertForbidden();
        $this->adminJson('PUT', '/marketing/promos/order', $viewer, ['ids' => [$promo->id], 'reason' => 'Sinov'])->assertForbidden();
        $this->adminJson('DELETE', "/marketing/promos/{$promo->id}", $viewer, ['reason' => 'Sinov'])->assertForbidden();
        $this->assertModelExists($promo);

        $this->adminJson('GET', '/marketing/promos', $finance)->assertForbidden();
        $this->adminJson('GET', '/marketing/promos', null)->assertUnauthorized();
    }

    public function test_create_stores_the_image_on_the_public_disk_audits_and_puts_it_last(): void
    {
        $first = $this->makePromo(['sort_order' => 4]);
        $member = $this->makeMember();
        $token = $this->signIn($member);

        $this->adminForm('/marketing/promos', $token, ['title' => 'Yangi aksiya', 'image' => $this->png()])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminForm('/marketing/promos', $token, ['title' => 'Yangi aksiya', 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('image');

        $response = $this->adminForm('/marketing/promos', $token, [
            'title' => '  Yangi aksiya ',
            'image' => $this->png(),
            'store_id' => $this->store->id,
            'is_active' => '1',
            'starts_at' => '2026-12-01T09:00:00+05:00',
            'ends_at' => '2026-12-10T21:00:00+05:00',
            'reason' => 'Dekabr aksiyasi',
        ])->assertCreated();

        $response->assertJsonPath('data.title', 'Yangi aksiya')
            ->assertJsonPath('data.store.id', $this->store->id)
            ->assertJsonPath('data.sort_order', 5)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.starts_at', '2026-12-01T09:00:00+05:00')
            ->assertJsonPath('data.ends_at', '2026-12-10T21:00:00+05:00');

        $promo = Promo::findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists($promo->image);
        $this->assertStringStartsWith('promos/', $promo->image);
        // The schedule is kept in UTC
        $this->assertSame('2026-12-01 04:00:00', $promo->starts_at->utc()->format('Y-m-d H:i:s'));

        $audit = AuditLog::where('action', 'admin.marketing.promos.created')->sole();
        $this->assertSame($member->id, (int) $audit->user_id);
        $this->assertSame('Dekabr aksiyasi', $audit->payload_json['reason']);
        $this->assertSame('Yangi aksiya', $audit->after['title']);
        $this->assertSame($first->id + 1, $promo->id);
    }

    public function test_image_rules(): void
    {
        $token = $this->signIn($this->makeMember());

        $this->adminForm('/marketing/promos', $token, ['title' => 'Aksiya', 'reason' => 'Sinov', 'image' => \Illuminate\Http\UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('image');
        $this->adminForm('/marketing/promos', $token, ['title' => 'Aksiya', 'reason' => 'Sinov', 'image' => \Illuminate\Http\UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml')])
            ->assertStatus(422)->assertJsonValidationErrors('image');
        $this->adminForm('/marketing/promos', $token, ['title' => 'Aksiya', 'reason' => 'Sinov', 'image' => \Illuminate\Http\UploadedFile::fake()->image('big.png')->size(5000)])
            ->assertStatus(422)->assertJsonValidationErrors('image');
        $this->assertSame(0, Promo::count());
    }

    public function test_update_changes_fields_replaces_the_image_and_audits_before_and_after(): void
    {
        $promo = $this->makePromo(['title' => 'Eski']);
        Storage::disk('public')->put($promo->image, 'old');
        $token = $this->signIn($this->makeMember());

        $this->adminForm("/marketing/promos/{$promo->id}", $token, ['title' => 'Yangi'])->assertStatus(422)->assertJsonValidationErrors('reason');

        $response = $this->adminForm("/marketing/promos/{$promo->id}", $token, [
            'title' => 'Yangi nom',
            'image' => $this->png('new.png'),
            'is_active' => '0',
            'product_id' => $this->product->id,
            'reason' => 'Yangilash',
        ])->assertOk();

        $response->assertJsonPath('data.title', 'Yangi nom')->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'inactive')->assertJsonPath('data.product.id', $this->product->id);
        $promo->refresh();
        Storage::disk('public')->assertMissing("promos/seed-{$promo->id}.png");
        Storage::disk('public')->assertExists($promo->image);

        $audit = AuditLog::where('action', 'admin.marketing.promos.updated')->sole();
        $this->assertSame('Eski', $audit->before['title']);
        $this->assertSame('Yangi nom', $audit->after['title']);
        $this->assertTrue($audit->before['is_active']);
        $this->assertFalse($audit->after['is_active']);

        // Clearing the target and the schedule (empty values)
        $this->adminForm("/marketing/promos/{$promo->id}", $token, ['product_id' => '', 'starts_at' => '', 'reason' => 'Tozalash'])->assertOk()
            ->assertJsonPath('data.product', null)->assertJsonPath('data.starts_at', null);

        // Nothing changed: nothing audited
        $count = AuditLog::where('action', 'admin.marketing.promos.updated')->count();
        $this->adminJson('POST', "/marketing/promos/{$promo->id}", $token, ['title' => 'Yangi nom', 'reason' => 'Bir xil'])->assertOk();
        $this->assertSame($count, AuditLog::where('action', 'admin.marketing.promos.updated')->count());

        $this->adminJson('POST', '/marketing/promos/999999', $token, ['title' => 'Yo\'q', 'reason' => 'Sinov'])->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_schedule_and_target_rules(): void
    {
        $token = $this->signIn($this->makeMember());
        $promo = $this->makePromo();
        $otherStore = $this->makeStore('Boshqa');

        $this->adminForm('/marketing/promos', $token, [
            'title' => 'Aksiya', 'image' => $this->png(), 'reason' => 'Sinov',
            'starts_at' => '2026-12-10T09:00:00+05:00', 'ends_at' => '2026-12-01T09:00:00+05:00',
        ])->assertStatus(422)->assertJsonPath('code', 'invalid_schedule');

        $this->adminForm("/marketing/promos/{$promo->id}", $token, ['store_id' => $otherStore->id, 'product_id' => $this->product->id, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonPath('code', 'product_store_mismatch');

        $this->adminForm("/marketing/promos/{$promo->id}", $token, ['store_id' => 99999999, 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('store_id');
        $this->adminForm("/marketing/promos/{$promo->id}", $token, ['starts_at' => 'kecha', 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonValidationErrors('starts_at');

        // Ending before the stored start is refused on an update too
        $promo->forceFill(['starts_at' => now()->addDay()])->save();
        $this->adminForm("/marketing/promos/{$promo->id}", $token, ['ends_at' => now()->toIso8601String(), 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_schedule');
    }

    public function test_status_follows_the_schedule_and_the_preview_is_what_the_catalog_lists(): void
    {
        $live = $this->makePromo(['title' => 'Ko\'rinadi', 'sort_order' => 2]);
        $always = $this->makePromo(['title' => 'Doim', 'sort_order' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $soon = $this->makePromo(['title' => 'Tez orada', 'starts_at' => now()->addDay()]);
        $over = $this->makePromo(['title' => 'Tugagan', 'ends_at' => now()->subMinute()]);
        $off = $this->makePromo(['title' => 'O\'chiq', 'is_active' => false]);
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $rows = collect($this->adminJson('GET', '/marketing/promos', $token)->assertOk()->json('data.data'))->keyBy('id');
        $this->assertSame('live', $rows[$live->id]['status']);
        $this->assertSame('live', $rows[$always->id]['status']);
        $this->assertSame('scheduled', $rows[$soon->id]['status']);
        $this->assertSame('ended', $rows[$over->id]['status']);
        $this->assertSame('inactive', $rows[$off->id]['status']);

        $this->adminJson('GET', '/marketing/promos?status=scheduled', $token)->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $soon->id);
        $this->adminJson('GET', '/marketing/promos?status=nope', $token)->assertStatus(422);

        $preview = $this->adminJson('GET', '/marketing/promos/preview', $token)->assertOk()->json('data.data');
        $this->assertSame([$always->id, $live->id], array_column($preview, 'id'));

        // The Mini App's feed lists the same banners (switched off, not started and ended ones are not there)
        $region = \App\Models\Region::forceCreate(['name_uz' => 'Toshkent', 'is_active' => true]);
        $this->app['auth']->forgetGuards();
        $feed = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog?region_id=' . $region->id)->assertOk()->json('data.promos');
        $this->assertSame([$always->id, $live->id], array_column($feed, 'id'));
    }

    public function test_reorder_saves_the_whole_row_and_refuses_a_stale_one(): void
    {
        [$a, $b, $c] = [$this->makePromo(), $this->makePromo(), $this->makePromo()];
        $cWas = $c->sort_order;
        $token = $this->signIn($this->makeMember());

        $this->adminJson('PUT', '/marketing/promos/order', $token, ['ids' => [$c->id, $a->id, $b->id]])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('PUT', '/marketing/promos/order', $token, ['ids' => [$c->id, $a->id], 'reason' => 'Tartib'])->assertStatus(409)->assertJsonPath('code', 'order_stale');
        $this->adminJson('PUT', '/marketing/promos/order', $token, ['ids' => [$c->id, $a->id, $a->id], 'reason' => 'Tartib'])->assertStatus(409);

        $this->adminJson('PUT', '/marketing/promos/order', $token, ['ids' => [$c->id, $a->id, $b->id], 'reason' => 'Yangi tartib'])
            ->assertOk()->assertJsonPath('data.data.0.id', $c->id)->assertJsonPath('data.data.2.id', $b->id);
        $this->assertSame([1, 2, 3], [$c->refresh()->sort_order, $a->refresh()->sort_order, $b->refresh()->sort_order]);

        $audit = AuditLog::where('action', 'admin.marketing.promos.reordered')->sole();
        $this->assertSame('Yangi tartib', $audit->payload_json['reason']);
        $this->assertSame($cWas, $audit->before['order'][$c->id]);
        $this->assertSame(1, $audit->after['order'][$c->id]);
    }

    public function test_delete_needs_a_step_up_and_a_reason_and_removes_the_file(): void
    {
        $promo = $this->makePromo();
        Storage::disk('public')->put($promo->image, 'file');
        $member = $this->makeMember();
        $token = $this->signIn($member);

        $this->adminJson('DELETE', "/marketing/promos/{$promo->id}", $token, ['reason' => 'Eskirgan'])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->assertModelExists($promo);

        $this->stepUp($member, $token);
        $this->adminJson('DELETE', "/marketing/promos/{$promo->id}", $token)->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('DELETE', "/marketing/promos/{$promo->id}", $token, ['reason' => 'Eskirgan'])->assertOk()->assertJsonPath('data.id', $promo->id);

        $this->assertModelMissing($promo);
        Storage::disk('public')->assertMissing($promo->image);
        $audit = AuditLog::where('action', 'admin.marketing.promos.deleted')->sole();
        $this->assertSame($promo->title, $audit->before['title']);
        $this->adminJson('DELETE', "/marketing/promos/{$promo->id}", $token, ['reason' => 'Yana'])->assertNotFound();
    }

    public function test_image_endpoint_serves_the_stored_file(): void
    {
        $promo = $this->makePromo();
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $this->adminJson('GET', "/marketing/promos/{$promo->id}/image", $token)->assertNotFound()->assertJsonPath('code', 'no_image');

        Storage::disk('public')->put($promo->image, $this->png()->getContent());
        $response = $this->adminJson('GET', "/marketing/promos/{$promo->id}/image", $token)->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));

        $row = $this->adminJson('GET', '/marketing/promos', $token)->json('data.data.0');
        $this->assertStringStartsWith("/marketing/promos/{$promo->id}/image?v=", $row['image_preview']);
    }

    public function test_lookup_finds_stores_and_products_without_stores_view(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));

        $found = $this->adminJson('GET', '/marketing/lookup?q=lifecycle', $token)->assertOk()->json('data');
        $this->assertSame([$this->store->id], array_column($found['stores'], 'id'));
        $this->assertSame([], $found['products']);

        $found = $this->adminJson('GET', "/marketing/lookup?store_id={$this->store->id}&q=cheese", $token)->assertOk()->json('data');
        $this->assertSame([$this->product->id], array_column($found['products'], 'id'));

        $found = $this->adminJson('GET', "/marketing/lookup?ids[]={$this->store->id}&product_ids[]={$this->product->id}", $token)->json('data');
        $this->assertSame('Lifecycle Burger', $found['stores'][0]['name']);
        $this->assertSame('Cheeseburger', $found['products'][0]['name']);
    }
}
