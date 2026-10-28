<?php

namespace Tests\Feature\AdminCustomers;

use App\Enums\AdminPermission;
use App\Enums\ComplaintSupportStatus;
use App\Enums\PlatformRole;
use App\Jobs\SendTelegramRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\SupportCannedReply;
use App\Services\Admin\Attention\AttentionCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P4-31: Shikoyatlar qutisi: the list with its status chips and filters, the card, taking a complaint, replying to the
 * customer through the bot, resolving and reopening, tayyor javoblar, the attention source; who may read and act.
 */
class AdminComplaintInboxTest extends TestCase
{
    use RefreshDatabase;
    use AdminCustomersFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        Queue::fake([SendTelegramRequest::class]);
        config(['services.telegram.bot_token' => '123456:TEST-bot-token', 'support.complaint_waiting_hours' => 4]);
    }

    public function test_the_inbox_lists_open_complaints_oldest_first_with_counts(): void
    {
        $old = $this->complaintOf($this->customer, null, ['status' => 'in_review'], 10);
        $fresh = $this->complaintOf($this->customer, null, [], 1);
        $storeAnswered = $this->complaintOf($this->customer, null, ['status' => 'owner_replied', 'reply' => 'Uzr, qayta yuboramiz'], 8);
        $resolved = $this->complaintOf($this->customer, null, ['status' => 'closed'], 20);
        $resolved->forceFill(['support_status' => ComplaintSupportStatus::RESOLVED])->save();

        $token = $this->signIn($this->makeMember(PlatformRole::SUPPORT));
        $page = $this->adminJson('GET', '/complaints', $token)->assertOk();
        $this->assertSame([$old->uuid, $storeAnswered->uuid, $fresh->uuid], collect($page->json('data.data'))->pluck('uuid')->all());
        $page->assertJsonPath('data.counts.new', 3)
            ->assertJsonPath('data.counts.resolved', 1)
            ->assertJsonPath('data.counts.open', 3)
            ->assertJsonPath('data.counts.waiting', 1)
            ->assertJsonPath('data.waiting_hours', 4)
            ->assertJsonPath('data.data.0.waiting', true)
            ->assertJsonPath('data.data.0.escalated', true)
            ->assertJsonPath('data.data.0.type_label', 'Sifat muammosi')
            ->assertJsonPath('data.data.1.waiting', false)
            ->assertJsonPath('data.data.1.store_replied', true)
            ->assertJsonPath('data.data.2.waiting', false)
            ->assertJsonPath('data.types.ORDER_NOT_RECEIVED', 'Buyurtma kelmadi')
            ->assertJsonPath('data.statuses.in_progress', "Ko'rib chiqilmoqda");

        $this->assertSame([$old->uuid], collect($this->adminJson('GET', '/complaints?waiting=1', $token)->json('data.data'))->pluck('uuid')->all());
        $this->assertSame([$resolved->uuid], collect($this->adminJson('GET', '/complaints?status=resolved', $token)->json('data.data'))->pluck('uuid')->all());
        $this->assertSame([$fresh->uuid, $storeAnswered->uuid, $old->uuid, $resolved->uuid], collect($this->adminJson('GET', '/complaints?status=all', $token)->json('data.data'))->pluck('uuid')->all());
        $code = $fresh->order->short_code;
        $this->assertSame([$fresh->uuid], collect($this->adminJson('GET', '/complaints?status=all&q=' . urlencode('#' . $code), $token)->json('data.data'))->pluck('uuid')->all());
        $this->adminJson('GET', '/complaints?status=closed', $token)->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_who_may_read_and_act(): void
    {
        $complaint = $this->complaintOf($this->customer);
        $viewer = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $this->adminJson('GET', '/complaints', $viewer)->assertOk();
        $this->adminJson('GET', "/complaints/{$complaint->uuid}", $viewer)->assertOk()->assertJsonPath('data.actions.manage', false)->assertJsonPath('data.actions.reply', false);
        $this->adminJson('GET', '/support/canned-replies', $viewer)->assertOk();
        $this->adminJson('GET', '/support/suspicious', $viewer)->assertOk();
        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $viewer, ['support_status' => 'in_progress'])->assertForbidden();
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $viewer, ['text' => 'Salom'])->assertForbidden();
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/resolve", $viewer, ['note' => 'Hal qilindi'])->assertForbidden();
        $this->adminJson('POST', '/support/canned-replies', $viewer, ['title' => 'A', 'body' => 'B'])->assertForbidden();

        $finance = $this->signIn($this->makeMember(PlatformRole::FINANCE));
        $this->adminJson('GET', '/complaints', $finance)->assertForbidden();
        $this->adminJson('GET', "/complaints/{$complaint->uuid}", $finance)->assertForbidden();
        $this->adminJson('GET', '/support/suspicious', $finance)->assertForbidden();
    }

    public function test_taking_a_complaint_and_giving_it_to_someone(): void
    {
        $complaint = $this->complaintOf($this->customer);
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $colleague = $this->makeMember(PlatformRole::SUPPORT);
        $finance = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->signIn($support);

        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $token, ['support_status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.support_status', 'in_progress')
            ->assertJsonPath('data.assignee.id', $support->id);
        $entry = AuditLog::where('action', 'admin.support.complaint_updated')->sole();
        $this->assertSame(['support_status' => 'new', 'assigned_to' => null], $entry->before);
        $this->assertSame(['support_status' => 'in_progress', 'assigned_to' => $support->id], $entry->after);
        $this->assertSame(Complaint::class, $entry->model_type);

        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $token, ['assigned_to' => $colleague->id])->assertOk()->assertJsonPath('data.assignee.id', $colleague->id);
        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $token, ['assigned_to' => $finance->id])->assertStatus(422)->assertJsonPath('code', 'assignee_invalid');
        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $token, ['assigned_to' => $this->customer->id])->assertStatus(422)->assertJsonPath('code', 'assignee_invalid');
        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $token, ['support_status' => 'resolved'])->assertStatus(422)->assertJsonPath('code', 'use_resolve');
        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $token, ['assigned_to' => null])->assertOk()->assertJsonPath('data.assignee', null);
        // Nothing changed: nothing recorded
        $count = AuditLog::where('action', 'admin.support.complaint_updated')->count();
        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $token, ['assigned_to' => null])->assertOk();
        $this->assertSame($count, AuditLog::where('action', 'admin.support.complaint_updated')->count());

        $list = $this->adminJson('GET', '/complaints?assigned=none', $token)->json('data.data');
        $this->assertSame([$complaint->uuid], collect($list)->pluck('uuid')->all());
        $this->assertContains($colleague->id, collect($this->adminJson('GET', '/complaints', $token)->json('data.assignees'))->pluck('id')->all());
        $this->assertNotContains($finance->id, collect($this->adminJson('GET', '/complaints', $token)->json('data.assignees'))->pluck('id')->all());
    }

    public function test_reply_goes_to_the_customer_through_the_bot(): void
    {
        $this->customer->forceFill(['telegram_id' => '777123'])->save();
        $complaint = $this->complaintOf($this->customer);
        $canned = SupportCannedReply::create(['title' => 'Uzr', 'body' => 'Uzr so\'raymiz, pul qaytariladi.']);
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->signIn($support);

        $card = $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => "Uzr so'raymiz, *pul* qaytariladi.", 'canned_reply_id' => $canned->id])
            ->assertOk()
            ->assertJsonPath('data.support_status', 'answered')
            ->assertJsonPath('data.assignee.id', $support->id)
            ->assertJsonPath('data.replies.0.body', "Uzr so'raymiz, *pul* qaytariladi.")
            ->assertJsonPath('data.replies.0.author.id', $support->id);
        $this->assertNotNull($card->json('data.answered_at'));

        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => (string) $job->params['chat_id'] === '777123'
            && str_contains($job->params['text'], "Uzr so'raymiz, *pul* qaytariladi.")
            && str_contains($job->params['text'], '#' . $complaint->order->short_code)
            && !isset($job->params['parse_mode'])
            && ($job->notification['type'] ?? null) === 'admin_complaint_reply');
        $entry = AuditLog::where('action', 'admin.support.complaint_replied')->sole();
        $this->assertSame($canned->id, $entry->payload_json['canned_reply_id']);
        $this->assertSame(['support_status' => 'new'], $entry->before);
        $this->assertSame(1, ComplaintReply::count());

        // Editing the tayyor javob later never changes what was sent
        $canned->update(['body' => 'Boshqa matn']);
        $this->assertSame("Uzr so'raymiz, *pul* qaytariladi.", ComplaintReply::sole()->body);
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => ''])->assertStatus(422)->assertJsonValidationErrors('text');
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors('text');
    }

    public function test_reply_without_telegram_or_bot_saves_nothing(): void
    {
        $complaint = $this->complaintOf($this->makeCustomer(0, ['telegram_id' => 'temp_abc']));
        $token = $this->signIn($this->makeMember(PlatformRole::SUPPORT));

        $this->adminJson('GET', "/complaints/{$complaint->uuid}", $token)->assertOk()->assertJsonPath('data.actions.reply', false)->assertJsonPath('data.customer.telegram_linked', false);
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => 'Salom'])->assertStatus(422)->assertJsonPath('code', 'no_recipient');

        config(['services.telegram.bot_token' => null]);
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => 'Salom'])->assertStatus(503)->assertJsonPath('code', 'telegram_not_configured');
        $this->assertSame(0, ComplaintReply::count());
        $this->assertSame(ComplaintSupportStatus::NEW, $complaint->fresh()->support_status);
        Queue::assertNothingPushed();
        $this->assertSame(0, AuditLog::where('action', 'admin.support.complaint_replied')->count());
    }

    public function test_resolve_closes_the_store_flow_with_the_note_and_reopen_brings_it_back(): void
    {
        $complaint = $this->complaintOf($this->customer, null, ['status' => 'in_review', 'reply' => 'Biz aybdor emasmiz']);
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->signIn($support);

        $this->adminJson('POST', "/complaints/{$complaint->uuid}/resolve", $token, ['note' => 'ab'])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/resolve", $token, ['note' => "Pul qaytarildi, ovqatxona ogohlantirildi"])
            ->assertOk()
            ->assertJsonPath('data.support_status', 'resolved')
            ->assertJsonPath('data.store_flow', 'closed')
            ->assertJsonPath('data.store_reply', 'Biz aybdor emasmiz')
            ->assertJsonPath('data.resolution.note', 'Pul qaytarildi, ovqatxona ogohlantirildi')
            ->assertJsonPath('data.resolution.by.id', $support->id)
            ->assertJsonPath('data.actions.resolve', false)
            ->assertJsonPath('data.actions.reply', false)
            ->assertJsonPath('data.actions.reopen', true);
        // The audit entry is written when the request ends: the next read shows it in the history
        $this->adminJson('GET', "/complaints/{$complaint->uuid}", $token)->assertJsonPath('data.history.0.action', 'admin.support.complaint_resolved');

        $complaint->refresh();
        $this->assertSame('closed', $complaint->status);
        $this->assertSame('Pul qaytarildi, ovqatxona ogohlantirildi', $complaint->resolution_comment);
        $this->assertSame($support->id, (int) $complaint->resolved_by);
        $entry = AuditLog::where('action', 'admin.support.complaint_resolved')->sole();
        $this->assertSame(['support_status' => 'new', 'status' => 'in_review'], $entry->before);
        $this->assertSame(['support_status' => 'resolved', 'status' => 'closed'], $entry->after);
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/resolve", $token, ['note' => 'Yana bir bor'])->assertStatus(409)->assertJsonPath('code', 'already_resolved');
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => 'Salom'])->assertStatus(409)->assertJsonPath('code', 'already_resolved');

        // The customer sees the resolution in their own complaint list
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/complaints')->assertOk()
            ->assertJsonPath('data.0.resolution_comment', 'Pul qaytarildi, ovqatxona ogohlantirildi')
            ->assertJsonMissingPath('data.0.assigned_to');

        $this->adminJson('PATCH', "/complaints/{$complaint->uuid}", $token, ['support_status' => 'in_progress'])
            ->assertOk()->assertJsonPath('data.support_status', 'in_progress')->assertJsonPath('data.store_flow', 'in_review')->assertJsonPath('data.resolved_at', null);
    }

    public function test_canned_replies_crud(): void
    {
        $token = $this->signIn($support = $this->makeMember(PlatformRole::SUPPORT));

        $created = $this->adminJson('POST', '/support/canned-replies', $token, ['title' => ' Kechikish ', 'body' => "Buyurtmangiz kechikkani uchun uzr so'raymiz.", 'sort_order' => 2])
            ->assertCreated()->assertJsonPath('data.title', 'Kechikish')->assertJsonPath('data.updated_by.id', $support->id);
        $id = $created->json('data.id');
        $this->adminJson('POST', '/support/canned-replies', $token, ['title' => 'Birinchi', 'body' => 'Matn', 'sort_order' => 1])->assertCreated();
        $this->assertSame(['Birinchi', 'Kechikish'], collect($this->adminJson('GET', '/support/canned-replies', $token)->json('data'))->pluck('title')->all());

        $this->adminJson('PATCH', "/support/canned-replies/{$id}", $token, ['body' => 'Yangi matn'])->assertOk()->assertJsonPath('data.body', 'Yangi matn');
        $entry = AuditLog::where('action', 'admin.support.canned_reply_updated')->sole();
        $this->assertSame(['body' => 'Yangi matn'], $entry->after);
        $this->adminJson('POST', '/support/canned-replies', $token, ['title' => '', 'body' => 'x'])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->adminJson('POST', '/support/canned-replies', $token, ['title' => 'x', 'body' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors('body');

        $this->adminJson('DELETE', "/support/canned-replies/{$id}", $token)->assertOk();
        $this->assertNull(SupportCannedReply::find($id));
        $this->assertSame(1, AuditLog::where('action', 'admin.support.canned_reply_deleted')->count());
        $this->assertSame(2, AuditLog::where('action', 'admin.support.canned_reply_created')->count());
        $this->adminJson('DELETE', "/support/canned-replies/{$id}", $token)->assertNotFound();
    }

    public function test_the_card_masks_the_customer_phone_without_pii_view(): void
    {
        $complaint = $this->complaintOf($this->customer);
        $this->adminJson('GET', "/complaints/{$complaint->uuid}", $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertOk()
            ->assertJsonPath('data.customer.phone', fn ($phone) => str_contains((string) $phone, '***'))
            ->assertJsonPath('data.customer.href', '/users/' . $this->customer->id)
            ->assertJsonPath('data.order.href', '/orders/' . $complaint->order->uuid);
        $this->assertSame(0, AuditLog::where('action', 'admin.pii.revealed')->count());

        $this->adminJson('GET', "/complaints/{$complaint->uuid}", $this->signIn($this->makeMember()))->assertOk()
            ->assertJsonPath('data.customer.phone', fn ($phone) => !str_contains((string) $phone, '***'));
        $this->assertSame(1, AuditLog::where('action', 'admin.pii.revealed')->count());
        $this->adminJson('GET', '/complaints/00000000-0000-4000-8000-000000000000', $this->signIn($this->makeMember()))->assertNotFound();
    }

    public function test_waiting_complaints_reach_bugun_for_support_view(): void
    {
        $waiting = $this->complaintOf($this->customer, null, ['status' => 'in_review'], 6);
        $this->complaintOf($this->customer, null, [], 1);
        $this->complaintOf($this->customer, null, ['status' => 'owner_replied'], 9);

        $support = $this->makeMember(PlatformRole::SUPPORT);
        $groups = collect(app(AttentionCollector::class)->collect($support, 5));
        $group = $groups->firstWhere('kind', 'complaint_waiting');
        $this->assertNotNull($group);
        $this->assertSame(1, $group['count']);
        $this->assertSame('/customers?tab=complaints&complaint=' . $waiting->uuid, $group['items'][0]['href']);
        $this->assertSame('warning', $group['items'][0]['severity']);

        $sales = $this->makeMember(PlatformRole::SALES);
        $this->assertNull(collect(app(AttentionCollector::class)->collect($sales, 5))->firstWhere('kind', 'complaint_waiting'));
        $this->assertTrue(app(\App\Services\Admin\AdminPermissionService::class)->has($support, AdminPermission::SUPPORT_VIEW));
    }
}
