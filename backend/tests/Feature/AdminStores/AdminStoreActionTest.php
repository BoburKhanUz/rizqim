<?php

namespace Tests\Feature\AdminStores;

use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Jobs\SendTelegramRequest;
use App\Models\AuditLog;
use App\Models\StoreStatusLog;
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

    public function test_display_flags_and_the_owner_telegram_link(): void
    {
        config(['services.telegram.bot_username' => 'rizqim_test_bot']);
        $store = $this->makeStore();
        $owner = $this->makeOwner($store);
        $oldToken = $owner->telegram_invite_token;
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SALES));

        $this->adminJson('PUT', "/stores/{$store->id}/display", $token, ['is_featured' => true, 'reason' => 'Aksiya haftasi'])
            ->assertOk()->assertJsonPath('data.profile.is_featured', true)->assertJsonPath('data.profile.is_premium', false);
        $this->assertSame(['is_featured' => true], AuditLog::where('action', 'admin.stores.display_changed')->sole()->after);

        $link = $this->adminJson('POST', "/stores/{$store->id}/telegram-link", $token, ['reason' => 'Egasi havolani yo\'qotdi'])
            ->assertOk()->assertJsonPath('data.owner_linked', false)->json('data.invite_link');
        $owner->refresh();
        $this->assertNotSame($oldToken, $owner->telegram_invite_token);
        $this->assertSame('https://t.me/rizqim_test_bot?start=invite_' . $owner->telegram_invite_token, $link);
        // The link is a credential: never in the audit log
        $audit = AuditLog::where('action', 'admin.stores.telegram_link_regenerated')->sole();
        $this->assertStringNotContainsString((string) $owner->telegram_invite_token, json_encode([$audit->before, $audit->after, $audit->payload_json]));

        $ownerless = $this->makeStore();
        $this->adminJson('POST', "/stores/{$ownerless->id}/telegram-link", $token, ['reason' => 'Egasi yo\'q'])
            ->assertStatus(422)->assertJsonPath('code', 'no_owner');
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
