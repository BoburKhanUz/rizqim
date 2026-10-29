<?php

namespace Tests\Feature\AdminStores;

use App\Enums\DeviceType;
use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Jobs\SendTelegramRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\StoreStatusLog;
use App\Models\User;
use App\Services\Admin\Today\PlatformStats;
use App\Services\Auth\StaffPhoneLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P4-22: the onboarding pipeline: applications, stages with checklists (ticked or detected), converting to a store
 * through StoreProvisioner, activation at `faol` (step-up), rejection; permissions and audit.
 */
class AdminStorePipelineTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([SendTelegramRequest::class]);
    }

    private function convertPayload(int $districtId, array $overrides = []): array
    {
        return array_merge([
            'branch_district_id' => $districtId,
            'branch_address' => 'Chilonzor 9-kvartal',
            'branch_lat' => 41.285,
            'branch_lng' => 69.204,
            'reason' => 'Hujjatlar tekshirildi',
        ], $overrides);
    }

    public function test_the_team_adds_lists_and_edits_applications(): void
    {
        $sales = $this->makeMember(PlatformRole::SALES);
        $other = $this->makeMember(PlatformRole::SALES);
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->signIn($sales);

        $created = $this->adminJson('POST', '/store-applications', $token, [
            'name' => 'Choyxona Navro\'z',
            'format' => 'teahouse',
            'owner_name' => 'Bobur',
            'phone' => '+998 90 765 43 21',
        ])->assertCreated();
        $uuid = $created->json('data.uuid');
        $this->assertSame('admin', $created->json('data.source'));
        $this->assertSame('application', $created->json('data.stage'));
        // The sales role sees phones masked (no pii.view)
        $this->assertStringContainsString('***', (string) $created->json('data.phone'));

        $this->adminJson('PATCH', "/store-applications/{$uuid}", $token, ['assignee_id' => $support->id])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->adminJson('PATCH', "/store-applications/{$uuid}", $token, ['assignee_id' => $other->id, 'due_on' => '2026-12-01'])
            ->assertOk()->assertJsonPath('data.assignee.id', $other->id)->assertJsonPath('data.due_on', '2026-12-01');

        $this->makeApplication(['due_on' => PlatformStats::now()->subDays(2)->format('Y-m-d')]);
        $list = $this->adminJson('GET', '/store-applications?stage=open', $token)->assertOk();
        $this->assertSame(2, $list->json('data.total'));
        $this->assertSame(2, $list->json('data.stage_counts.application'));
        $this->assertSame(1, $list->json('data.stage_counts.overdue'));
        // The overdue one first
        $this->assertTrue($list->json('data.data.0.overdue'));
        $this->assertSame(1, $this->adminJson('GET', '/store-applications?assignee_id=' . $other->id, $token)->json('data.total'));
        $this->assertSame(1, $this->adminJson('GET', '/store-applications?q=' . urlencode('907654321'), $token)->json('data.total'));

        $this->assertTrue(AuditLog::where('action', 'admin.stores.applications.created')->exists());
        $update = AuditLog::where('action', 'admin.stores.applications.updated')->sole();
        $this->assertSame($other->id, $update->after['assignee_id']);

        // Support may look, not change
        $supportToken = $this->signIn($support);
        $this->adminJson('GET', "/store-applications/{$uuid}", $supportToken)->assertOk();
        $this->adminJson('PATCH', "/store-applications/{$uuid}", $supportToken, ['name' => 'X'])->assertForbidden();
    }

    public function test_forward_moves_need_the_checklist_and_the_store(): void
    {
        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->signIn($sales);
        $application = $this->makeApplication();
        $uuid = $application->uuid;

        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'review'])
            ->assertStatus(422)->assertJsonPath('code', 'checklist_incomplete')
            ->assertJsonPath('data.stage', 'application')->assertJsonPath('data.missing', ['contacted']);

        $this->adminJson('POST', "/store-applications/{$uuid}/checklist", $token, ['item' => 'nope', 'done' => true])
            ->assertStatus(422)->assertJsonPath('code', 'unknown_item');
        $this->adminJson('POST', "/store-applications/{$uuid}/checklist", $token, ['item' => 'contacted', 'done' => true])->assertOk();
        $moved = $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'review', 'note' => 'Qo\'ng\'iroq qilindi'])->assertOk();
        $this->assertSame('review', $moved->json('data.stage'));
        // The stage's own deadline: today + 2 days
        $this->assertSame(PlatformStats::now()->addDays(2)->format('Y-m-d'), $moved->json('data.due_on'));

        foreach (['tin_checked', 'legal_name', 'bank_account'] as $item) {
            $this->adminJson('POST', "/store-applications/{$uuid}/checklist", $token, ['item' => $item, 'done' => true])->assertOk();
        }
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'menu'])
            ->assertStatus(422)->assertJsonPath('code', 'store_required');
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'rejected'])
            ->assertStatus(422)->assertJsonPath('code', 'use_reject');

        $audit = AuditLog::where('action', 'admin.stores.applications.stage_changed')->sole();
        $this->assertSame(['stage' => 'application'], $audit->before);
        $this->assertSame(['stage' => 'review'], $audit->after);
        $this->assertSame($application->id, (int) $audit->model_id);
    }

    public function test_convert_creates_the_store_through_the_wizards_path_hidden_until_active(): void
    {
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.bot_username' => 'rizqim_test_bot']);
        User::forceCreate(['name' => 'Eski superadmin', 'role' => UserRole::SUPER_ADMIN->value, 'telegram_id' => '9001']);
        $region = $this->region();
        $district = $this->district($region);
        $sales = $this->makeMember(PlatformRole::SALES);
        $application = $this->makeApplication(['region_id' => $region->id, 'phone' => '+998901112233', 'tin' => '305123456'], \App\Enums\StoreApplicationStage::REVIEW);
        $uuid = $application->uuid;

        $token = $this->signIn($sales);
        // A step-up first
        $this->adminJson('POST', "/store-applications/{$uuid}/convert", $token, $this->convertPayload($district->id))
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($sales, $token);
        $this->adminJson('POST', "/store-applications/{$uuid}/convert", $token, $this->convertPayload($district->id, ['reason' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $response = $this->adminJson('POST', "/store-applications/{$uuid}/convert", $token, $this->convertPayload($district->id))->assertCreated();
        $store = Store::findOrFail($response->json('data.store_id'));
        $this->assertStringStartsWith('https://t.me/rizqim_test_bot?start=invite_', $response->json('data.invite_link'));

        $this->assertSame(StoreStatus::PENDING, $store->status);
        $this->assertFalse((bool) $store->is_active);
        $this->assertSame($application->name, $store->name);
        $this->assertSame('305123456', $store->tin);
        // No flat commission rate given: the column's default (Billing v2: the channel rates)
        // The wizard's payment settings, the platform's merchant account
        $this->assertSame('platform_account', (string) $store->payment_account_type);
        $this->assertTrue($store->payment_settings['cash']['enabled']);
        $owner = User::where('store_id', $store->id)->where('role', UserRole::OWNER->value)->sole();
        $this->assertSame('+998901112233', StaffPhoneLoginService::loginPhone($owner));
        $this->assertNotNull($owner->telegram_invite_token);
        $branch = Branch::where('store_id', $store->id)->sole();
        $this->assertSame($region->id, (int) $branch->region_id);
        $this->assertSame('Asosiy Filial', $branch->name);
        // The superadmins hear about it, as from the wizard
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '9001'
            && str_contains($job->params['text'], 'ariza #' . $application->id));

        $this->assertSame($store->id, (int) $application->fresh()->store_id);
        $audit = AuditLog::where('action', 'admin.stores.applications.converted')->sole();
        $this->assertSame($store->id, (int) $audit->store_id);
        $this->assertStringNotContainsString('invite_', json_encode([$audit->before, $audit->after, $audit->payload_json]));

        $this->adminJson('POST', "/store-applications/{$uuid}/convert", $token, $this->convertPayload($district->id))
            ->assertStatus(409)->assertJsonPath('code', 'already_converted');

        // Another application with the same owner phone: the phone is taken by the new owner
        $second = $this->makeApplication(['region_id' => $region->id, 'phone' => '+998901112233']);
        $this->adminJson('POST', "/store-applications/{$second->uuid}/convert", $token, $this->convertPayload($district->id))
            ->assertStatus(409)->assertJsonPath('code', 'owner_phone_taken');
        $this->assertSame(1, Store::count());
    }

    public function test_detected_items_and_the_move_to_active_which_activates_the_store(): void
    {
        $region = $this->region();
        $district = $this->district($region);
        $sales = $this->makeMember(PlatformRole::SALES);
        $application = $this->makeApplication(['region_id' => $region->id, 'checklist' => [
            'contacted' => ['done_at' => now()->toIso8601String(), 'by' => null],
            'tin_checked' => ['done_at' => now()->toIso8601String(), 'by' => null],
            'legal_name' => ['done_at' => now()->toIso8601String(), 'by' => null],
            'bank_account' => ['done_at' => now()->toIso8601String(), 'by' => null],
        ]], \App\Enums\StoreApplicationStage::REVIEW);
        $uuid = $application->uuid;
        $token = $this->confirmedSession($sales);
        $storeId = $this->adminJson('POST', "/store-applications/{$uuid}/convert", $token, $this->convertPayload($district->id))->json('data.store_id');

        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'menu'])->assertOk();
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'devices'])
            ->assertStatus(422)->assertJsonPath('data.missing', ['menu_entered', 'prices']);

        // A product makes "Menyu kiritildi" detected
        $category = \App\Models\Category::forceCreate(['name' => 'Taomlar', 'slug' => 'taomlar-' . uniqid()]);
        DB::table('products')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'store_id' => $storeId, 'category_id' => $category->id, 'name' => 'Osh', 'slug' => 'osh-' . uniqid(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $card = $this->adminJson('GET', "/store-applications/{$uuid}", $token)->assertOk();
        $menu = collect($card->json('data.checklist'))->firstWhere('stage', 'menu');
        $entered = collect($menu['items'])->firstWhere('key', 'menu_entered');
        $this->assertTrue($entered['done']);
        $this->assertTrue($entered['detected']);
        $this->assertFalse($entered['ticked']);

        $this->adminJson('POST', "/store-applications/{$uuid}/checklist", $token, ['item' => 'prices', 'done' => true])->assertOk();
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'devices'])->assertOk();
        Device::forceCreate(['store_id' => $storeId, 'type' => DeviceType::POS->value, 'name' => 'Kassa 1', 'last_seen_at' => now()]);
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'training'])->assertOk();

        foreach (['staff_trained', 'owner_panel', 'first_order', 'owner_confirmed'] as $item) {
            $this->adminJson('POST', "/store-applications/{$uuid}/checklist", $token, ['item' => $item, 'done' => true])->assertOk();
        }
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'trial'])->assertOk();

        // Activation: a reason and a fresh step-up
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->travel(11)->minutes();
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'active', 'reason' => 'Hammasi tayyor'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($sales, $token);
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'active', 'reason' => 'Hammasi tayyor'])
            ->assertOk()->assertJsonPath('data.stage', 'active')->assertJsonPath('data.due_on', null);

        $store = Store::findOrFail($storeId);
        $this->assertSame(StoreStatus::ACTIVE, $store->status);
        $this->assertTrue((bool) $store->is_active);
        $log = StoreStatusLog::where('store_id', $storeId)->sole();
        $this->assertSame('pending', $log->from_status);
        $this->assertSame('active', $log->to_status);
        $this->assertSame($sales->id, (int) $log->admin_id);
        $this->assertStringContainsString('Hammasi tayyor', (string) $log->comment);
        $this->assertNotNull($application->fresh()->activated_at);

        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'trial'])
            ->assertStatus(422)->assertJsonPath('code', 'already_active');
        $this->adminJson('POST', "/store-applications/{$uuid}/reject", $token, ['reason' => 'Kech qoldi'])
            ->assertStatus(422)->assertJsonPath('code', 'already_active');
    }

    public function test_reject_hides_a_converted_store_and_reopens_only_at_the_start(): void
    {
        $region = $this->region();
        $district = $this->district($region);
        $sales = $this->makeMember(PlatformRole::SALES);
        $application = $this->makeApplication(['region_id' => $region->id]);
        $uuid = $application->uuid;
        $token = $this->confirmedSession($sales);
        $storeId = $this->adminJson('POST', "/store-applications/{$uuid}/convert", $token, $this->convertPayload($district->id))->json('data.store_id');

        $this->adminJson('POST', "/store-applications/{$uuid}/reject", $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('POST', "/store-applications/{$uuid}/reject", $token, ['reason' => 'Hujjatlar soxta'])
            ->assertOk()->assertJsonPath('data.stage', 'rejected')->assertJsonPath('data.rejected_reason', 'Hujjatlar soxta');
        $this->assertSame(StoreStatus::REJECTED, Store::findOrFail($storeId)->status);
        $this->assertSame('rejected', StoreStatusLog::where('store_id', $storeId)->sole()->to_status);

        $this->adminJson('POST', "/store-applications/{$uuid}/reject", $token, ['reason' => 'Yana'])->assertStatus(409);
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'menu'])
            ->assertStatus(422)->assertJsonPath('code', 'reopen_only');
        $this->adminJson('POST', "/store-applications/{$uuid}/stage", $token, ['stage' => 'review'])
            ->assertOk()->assertJsonPath('data.rejected_reason', null);
    }

    public function test_overdue_applications_reach_bugun(): void
    {
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $late = $this->makeApplication(['due_on' => PlatformStats::now()->subDays(3)->format('Y-m-d'), 'assignee_id' => $operator->id], \App\Enums\StoreApplicationStage::REVIEW);
        $this->makeApplication(['due_on' => PlatformStats::now()->subDays(3)->format('Y-m-d')], \App\Enums\StoreApplicationStage::REJECTED);

        $groups = collect($this->adminJson('GET', '/today/attention', $this->signIn($operator))->json('data.groups'));
        $overdue = $groups->firstWhere('kind', 'store_application_overdue');
        $this->assertSame(1, $overdue['count']);
        $this->assertSame('/stores/applications/' . $late->uuid, $overdue['items'][0]['href']);
        $this->assertStringContainsString('3 kun', $overdue['items'][0]['subtitle']);
        // Assigned, so not "new"
        $this->assertNull($groups->firstWhere('kind', 'store_application_new'));
    }

    public function test_viewer_and_operator_read_but_do_not_change(): void
    {
        $application = $this->makeApplication();
        foreach ([PlatformRole::VIEWER, PlatformRole::OPERATOR] as $role) {
            $token = $this->confirmedSession($this->makeMember($role));
            $this->adminJson('GET', '/store-applications', $token)->assertOk();
            $this->adminJson('POST', "/store-applications/{$application->uuid}/checklist", $token, ['item' => 'contacted', 'done' => true])->assertForbidden();
            $this->adminJson('POST', "/store-applications/{$application->uuid}/reject", $token, ['reason' => 'Test sabab'])->assertForbidden();
        }
        $this->assertSame('application', StoreApplication::sole()->stage->value);
    }
}
