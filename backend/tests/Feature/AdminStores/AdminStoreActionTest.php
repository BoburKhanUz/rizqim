<?php

namespace Tests\Feature\AdminStores;

use App\Enums\AdminPermission;
use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Jobs\SendTelegramRequest;
use App\Models\AuditLog;
use App\Models\StoreStatusLog;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P4-22: actions on a store from its 360° card: stores.manage, a step-up and a reason each, audited with before and
 * after; status changes in the status history.
 */
class AdminStoreActionTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([SendTelegramRequest::class]);
    }

    public function test_suspend_and_activate_with_step_up_reason_history_and_audit(): void
    {
        $store = $this->makeStore();
        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->signIn($sales);

        $this->adminJson('POST', "/stores/{$store->id}/suspend", $token, ['reason' => 'Gigiyena tekshiruvi'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($sales, $token);
        $this->adminJson('POST', "/stores/{$store->id}/suspend", $token)->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->adminJson('POST', "/stores/{$store->id}/suspend", $token, ['reason' => 'Gigiyena tekshiruvi'])
            ->assertOk()
            ->assertJsonPath('data.profile.status', 'suspended')
            ->assertJsonPath('data.profile.is_active', false)
            ->assertJsonPath('data.actions.suspend', false)
            ->assertJsonPath('data.actions.activate', true)
            ->assertJsonPath('data.status_history.0.to_status', 'suspended')
            ->assertJsonPath('data.status_history.0.comment', 'Gigiyena tekshiruvi');

        $store->refresh();
        $this->assertSame(StoreStatus::SUSPENDED, $store->status);
        $this->assertFalse((bool) $store->is_active);
        $audit = AuditLog::where('action', 'admin.stores.suspended')->sole();
        $this->assertSame(['status' => 'active', 'is_active' => true], $audit->before);
        $this->assertSame(['status' => 'suspended', 'is_active' => false], $audit->after);
        $this->assertSame('Gigiyena tekshiruvi', $audit->payload_json['reason']);
        $this->assertSame($sales->id, (int) $audit->user_id);
        $this->assertSame($store->id, (int) $audit->store_id);

        $this->adminJson('POST', "/stores/{$store->id}/suspend", $token, ['reason' => 'Yana bir bor'])->assertStatus(409)->assertJsonPath('code', 'already_suspended');

        $this->adminJson('POST', "/stores/{$store->id}/activate", $token, ['reason' => 'Tekshiruv tugadi'])
            ->assertOk()->assertJsonPath('data.profile.status', 'active')->assertJsonPath('data.profile.is_active', true);
        $this->assertSame(2, StoreStatusLog::where('store_id', $store->id)->count());
        $this->adminJson('POST', "/stores/{$store->id}/activate", $token, ['reason' => 'Yana bir bor'])->assertStatus(409)->assertJsonPath('code', 'already_active');
    }

    public function test_activate_leaves_finance_statuses_and_open_applications_alone(): void
    {
        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->confirmedSession($sales);

        $blocked = $this->makeStore(['status' => StoreStatus::PAYMENT_REQUIRED->value]);
        $this->adminJson('POST', "/stores/{$blocked->id}/activate", $token, ['reason' => 'Ochib qo\'yamiz'])
            ->assertStatus(422)->assertJsonPath('code', 'finance_status');

        $onboarding = $this->makeStore(['status' => StoreStatus::PENDING->value, 'is_active' => false]);
        $this->makeApplication(['store_id' => $onboarding->id], \App\Enums\StoreApplicationStage::MENU);
        $this->adminJson('POST', "/stores/{$onboarding->id}/activate", $token, ['reason' => 'Ochib qo\'yamiz'])
            ->assertStatus(422)->assertJsonPath('code', 'use_pipeline');
        $this->assertSame(StoreStatus::PENDING, $onboarding->fresh()->status);
    }

    public function test_credit_limit_applies_the_ledgers_block_rule(): void
    {
        $store = $this->makeStore();
        $store->forceFill(['debt_balance' => '150000.00'])->save();
        $finance = $this->makeMember(PlatformRole::SALES);
        $token = $this->confirmedSession($finance);

        // Default limit 100 000: lowering it under the debt blocks the store
        $this->adminJson('PUT', "/stores/{$store->id}/credit-limit", $token, ['credit_limit' => '-5', 'reason' => 'Qarz katta'])
            ->assertStatus(422)->assertJsonValidationErrors('credit_limit');
        $this->adminJson('PUT', "/stores/{$store->id}/credit-limit", $token, ['credit_limit' => 0, 'reason' => 'Qarz katta'])
            ->assertOk()->assertJsonPath('data.profile.status', 'payment_required')->assertJsonPath('data.finance.credit_limit', '0.00');
        $this->assertSame('0.00', \App\Support\Money::of($store->fresh()->credit_limit)->abs()->toString());

        $this->adminJson('PUT', "/stores/{$store->id}/credit-limit", $token, ['credit_limit' => '200000.50', 'reason' => 'Ishonchli hamkor'])
            ->assertOk()->assertJsonPath('data.profile.status', 'active')->assertJsonPath('data.finance.credit_limit', '200000.50');
        $this->assertSame('-200000.50', (string) $store->fresh()->credit_limit);

        $audit = AuditLog::where('action', 'admin.stores.credit_limit_changed')->orderBy('id')->get();
        $this->assertCount(2, $audit);
        $this->assertSame(['credit_limit' => '100000.00', 'status' => 'active'], $audit[0]->before);
        $this->assertSame(['credit_limit' => '0.00', 'status' => 'payment_required'], $audit[0]->after);
        $this->assertSame(['payment_required', 'active'], StoreStatusLog::where('store_id', $store->id)->orderBy('id')->pluck('to_status')->all());
    }

    public function test_display_flags(): void
    {
        $store = $this->makeStore();
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SALES));

        $this->adminJson('PUT', "/stores/{$store->id}/display", $token, ['is_featured' => true, 'reason' => 'Aksiya haftasi'])
            ->assertOk()->assertJsonPath('data.profile.is_featured', true)->assertJsonPath('data.profile.is_premium', false);
        $this->assertSame(['is_featured' => true], AuditLog::where('action', 'admin.stores.display_changed')->sole()->after);
    }

    /**
     * Review F3-1: the link binds whoever opens it to the owner's account, so it never reaches the member that asked
     * for it unless the member holds stores.impersonate.
     */
    public function test_a_linked_owner_is_never_relinked(): void
    {
        config(['services.telegram.bot_username' => 'rizqim_test_bot']);
        $store = $this->makeStore();
        $owner = $this->makeOwner($store, '+998901112233', '777001');
        $owner->forceFill(['telegram_invite_token' => 'old-token'])->save();
        $sms = $this->mock(SmsServiceInterface::class);
        $sms->shouldNotReceive('send');
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SALES));

        $this->adminJson('GET', "/stores/{$store->id}", $token)->assertOk()->assertJsonPath('data.actions.telegram_link', false)->assertJsonPath('data.actions.message_owner', true);
        $this->adminJson('POST', "/stores/{$store->id}/telegram-link", $token, ['reason' => 'Egasi havolani yo\'qotdi'])
            ->assertStatus(409)->assertJsonPath('code', 'owner_already_linked')->assertJsonMissingPath('data.invite_link');

        $owner->refresh();
        $this->assertSame('777001', $owner->telegram_id);
        $this->assertSame('old-token', $owner->telegram_invite_token);
        $this->assertSame(0, AuditLog::where('action', 'admin.stores.telegram_link_regenerated')->count());

        // Not even a superadmin takes over a linked owner
        $this->adminJson('POST', "/stores/{$store->id}/telegram-link", $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN)), ['reason' => 'Egasi havolani yo\'qotdi'])
            ->assertStatus(409)->assertJsonPath('code', 'owner_already_linked');
        $this->assertSame('old-token', $owner->fresh()->telegram_invite_token);
    }

    public function test_the_link_for_an_unlinked_owner_goes_to_their_vouched_phone_not_the_response(): void
    {
        config(['services.telegram.bot_username' => 'rizqim_test_bot']);
        $store = $this->makeStore();
        $owner = $this->makeOwner($store, '+998901112233');
        $owner->forceFill(['phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_VOUCHED])->save();
        $oldToken = $owner->telegram_invite_token;
        $sent = [];
        $this->mock(SmsServiceInterface::class)->shouldReceive('send')->once()->andReturnUsing(function (string $phone, string $message) use (&$sent) {
            $sent = [$phone, $message];

            return true;
        });
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SALES));

        $response = $this->adminJson('POST', "/stores/{$store->id}/telegram-link", $token, ['reason' => 'Egasi havolani yo\'qotdi'])
            ->assertOk()->assertJsonPath('data.owner_linked', false)->assertJsonPath('data.delivered_via', 'sms')->assertJsonPath('data.invite_link', null);

        $owner->refresh();
        $this->assertNotSame($oldToken, $owner->telegram_invite_token);
        $this->assertSame('+998901112233', $sent[0]);
        $this->assertStringContainsString('https://t.me/rizqim_test_bot?start=invite_' . $owner->telegram_invite_token, $sent[1]);
        $this->assertStringNotContainsString((string) $owner->telegram_invite_token, $response->getContent());
        // The link is a credential: never in the audit log
        $audit = AuditLog::where('action', 'admin.stores.telegram_link_regenerated')->sole();
        $this->assertStringNotContainsString((string) $owner->telegram_invite_token, json_encode([$audit->before, $audit->after, $audit->payload_json]));
        $this->assertSame('sms', $audit->payload_json['delivered_via']);
    }

    public function test_without_a_deliverable_phone_only_stores_impersonate_sees_the_link(): void
    {
        config(['services.telegram.bot_username' => 'rizqim_test_bot']);
        $ownerless = $this->makeStore();
        $store = $this->makeStore();
        $owner = $this->makeOwner($store);
        $oldToken = $owner->telegram_invite_token;
        $this->mock(SmsServiceInterface::class)->shouldNotReceive('send');
        $sales = $this->confirmedSession($this->makeMember(PlatformRole::SALES));

        $this->adminJson('POST', "/stores/{$ownerless->id}/telegram-link", $sales, ['reason' => 'Egasi yo\'q'])
            ->assertStatus(422)->assertJsonPath('code', 'no_owner');

        // No phone, no SMS, no impersonate: refused and the old link is left alone
        $this->adminJson('POST', "/stores/{$store->id}/telegram-link", $sales, ['reason' => 'Egasi havolani yo\'qotdi'])
            ->assertStatus(422)->assertJsonPath('code', 'link_not_deliverable')->assertJsonMissingPath('data.invite_link');
        $this->assertSame($oldToken, $owner->fresh()->telegram_invite_token);

        $super = $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN));
        $link = $this->adminJson('POST', "/stores/{$store->id}/telegram-link", $super, ['reason' => 'Egasi havolani yo\'qotdi'])
            ->assertOk()->assertJsonPath('data.delivered_via', null)->json('data.invite_link');
        $owner->refresh();
        $this->assertNotSame($oldToken, $owner->telegram_invite_token);
        $this->assertSame('https://t.me/rizqim_test_bot?start=invite_' . $owner->telegram_invite_token, $link);
        $audit = AuditLog::where('action', 'admin.stores.telegram_link_regenerated')->sole();
        $this->assertStringNotContainsString((string) $owner->telegram_invite_token, json_encode([$audit->before, $audit->after, $audit->payload_json]));
    }

    public function test_a_failed_sms_falls_back_to_stores_impersonate(): void
    {
        $store = $this->makeStore();
        $owner = $this->makeOwner($store, '+998901112244');
        $owner->forceFill(['phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_VOUCHED])->save();
        $oldToken = $owner->telegram_invite_token;
        $this->mock(SmsServiceInterface::class)->shouldReceive('send')->twice()->andReturn(false);

        $this->adminJson('POST', "/stores/{$store->id}/telegram-link", $this->confirmedSession($this->makeMember(PlatformRole::SALES)), ['reason' => 'Egasi havolani yo\'qotdi'])
            ->assertStatus(422)->assertJsonPath('code', 'link_not_deliverable');
        $this->assertSame($oldToken, $owner->fresh()->telegram_invite_token);

        $this->adminJson('POST', "/stores/{$store->id}/telegram-link", $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN)), ['reason' => 'Egasi havolani yo\'qotdi'])
            ->assertOk()->assertJsonPath('data.delivered_via', null)->assertJsonStructure(['data' => ['invite_link']]);
    }

    public function test_stores_impersonate_is_a_privileged_permission(): void
    {
        $this->assertContains(AdminPermission::STORES_IMPERSONATE, AdminPermissionService::PRIVILEGED);
        foreach ([PlatformRole::SALES, PlatformRole::OPERATOR, PlatformRole::SUPPORT, PlatformRole::FINANCE, PlatformRole::VIEWER] as $role) {
            $this->assertNotContains(AdminPermission::STORES_IMPERSONATE, app(AdminPermissionService::class)->forRole($role));
        }
    }

    public function test_message_the_owner_through_the_queued_bot(): void
    {
        config(['services.telegram.bot_token' => '1:token']);
        $store = $this->makeStore();
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SALES));

        $this->makeOwner($store);
        $this->adminJson('POST', "/stores/{$store->id}/message", $token, ['text' => 'Salom', 'reason' => 'Shartnoma'])
            ->assertStatus(422)->assertJsonPath('code', 'no_recipient');

        $linked = $this->makeStore();
        $this->makeOwner($linked, null, '777001');
        $this->adminJson('POST', "/stores/{$linked->id}/message", $token, ['text' => "Ertaga *texnik* ishlar", 'reason' => 'Ogohlantirish'])
            ->assertOk()->assertJsonPath('data.recipients', 1);
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '777001'
            && str_contains($job->params['text'], "Ertaga *texnik* ishlar")
            && !isset($job->params['parse_mode']));
        $this->assertSame('Ertaga *texnik* ishlar', AuditLog::where('action', 'admin.stores.owner_messaged')->sole()->payload_json['text']);
    }

    public function test_members_without_stores_manage_cannot_act(): void
    {
        $store = $this->makeStore();
        foreach ([PlatformRole::SUPPORT, PlatformRole::OPERATOR, PlatformRole::FINANCE, PlatformRole::VIEWER] as $role) {
            $token = $this->confirmedSession($this->makeMember($role));
            $this->adminJson('POST', "/stores/{$store->id}/suspend", $token, ['reason' => 'Sinov uchun'])->assertForbidden()->assertJsonPath('code', 'forbidden');
            $this->adminJson('GET', "/stores/{$store->id}", $token)->assertOk()->assertJsonPath('data.actions.manage', false);
        }
        $this->assertSame(StoreStatus::ACTIVE, $store->fresh()->status);
        $this->adminJson('POST', '/stores/999999/suspend', $this->confirmedSession($this->makeMember(PlatformRole::SALES)), ['reason' => 'Sinov uchun'])->assertNotFound();
    }
}
