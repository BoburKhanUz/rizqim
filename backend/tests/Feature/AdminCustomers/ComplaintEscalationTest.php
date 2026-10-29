<?php

namespace Tests\Feature\AdminCustomers;

use App\Enums\ComplaintSupportStatus;
use App\Enums\PlatformRole;
use App\Jobs\SendTelegramRequest;
use App\Models\Complaint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Review W3 C#6 / C#7 / minor: a complaint the customer escalates after the store's reply is back with the support
 * team (not left `answered` or `resolved`); the support side's fields never reach the customer's or the store's
 * complaint API; support replies are throttled per member.
 */
class ComplaintEscalationTest extends TestCase
{
    use RefreshDatabase;
    use AdminCustomersFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        Queue::fake([SendTelegramRequest::class]);
        config(['services.telegram.bot_token' => '123456:TEST-bot-token']);
        $this->customer->forceFill(['telegram_id' => '777123'])->save();
    }

    public function test_an_escalated_complaint_is_open_again_for_support(): void
    {
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->signIn($support);

        // Support answered, then the store replied, then the customer sends it on
        $complaint = $this->complaintOf($this->customer);
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => 'Ko\'rib chiqamiz'])->assertOk();
        $complaint->update(['status' => 'owner_replied', 'reply' => 'Uzr']);
        $this->travel(5)->minutes();

        Sanctum::actingAs($this->customer->fresh(), ['*']);
        $this->postJson("/api/v1/complaints/{$complaint->id}/escalate")->assertOk();

        $complaint->refresh();
        $this->assertSame('in_review', $complaint->status);
        // It was taken by the member who answered: in progress with them
        $this->assertSame(ComplaintSupportStatus::IN_PROGRESS, $complaint->support_status);
        $this->assertSame($support->id, (int) $complaint->assigned_to);
        $this->assertTrue($complaint->support_status_changed_at->greaterThan(now()->subMinute()));
        $this->assertNotNull($complaint->answered_at, 'the history of the answer is kept');

        // Nobody had taken it: new
        $other = $this->complaintOf($this->customer, null, ['status' => 'owner_replied', 'reply' => 'Uzr']);
        $other->forceFill(['support_status' => ComplaintSupportStatus::ANSWERED->value])->save();
        $this->postJson("/api/v1/complaints/{$other->id}/escalate")->assertOk();
        $this->assertSame(ComplaintSupportStatus::NEW, $other->fresh()->support_status);

        // Resolved by support, then the store answered again and the customer escalates: reopened
        $resolved = $this->complaintOf($this->customer, null, ['status' => 'owner_replied', 'reply' => 'Yana uzr']);
        $resolved->forceFill(['support_status' => ComplaintSupportStatus::RESOLVED->value, 'resolved_at' => now()])->save();
        $this->postJson("/api/v1/complaints/{$resolved->id}/escalate")->assertOk();
        $this->assertSame(ComplaintSupportStatus::NEW, $resolved->fresh()->support_status);
        $this->assertNull($resolved->fresh()->resolved_at);

        // Already open on the support side: left as it is
        $open = $this->complaintOf($this->customer, null, ['status' => 'owner_replied', 'reply' => 'Uzr']);
        $open->forceFill(['support_status' => ComplaintSupportStatus::IN_PROGRESS->value, 'support_status_changed_at' => now()->subDay()])->save();
        $this->postJson("/api/v1/complaints/{$open->id}/escalate")->assertOk();
        $this->assertTrue($open->fresh()->support_status_changed_at->lessThan(now()->subHours(23)));
    }

    public function test_the_support_side_never_reaches_the_customer_or_the_store(): void
    {
        $complaint = $this->complaintOf($this->customer, null, ['status' => 'owner_replied', 'reply' => 'Uzr']);
        $complaint->forceFill([
            'support_status' => ComplaintSupportStatus::ANSWERED->value, 'support_status_changed_at' => now(),
            'answered_at' => now(), 'resolved_at' => now(), 'assigned_to' => $this->makeMember(PlatformRole::SUPPORT)->id,
        ])->save();
        $internal = ['support_status', 'support_status_changed_at', 'answered_at', 'resolved_at', 'assigned_to'];

        Sanctum::actingAs($this->customer->fresh(), ['*']);
        $mine = $this->getJson('/api/v1/complaints')->assertOk()->json('data.0');
        $this->assertSame($complaint->id, $mine['id']);
        $this->assertSame('owner_replied', $mine['status']);
        foreach ($internal as $field) {
            $this->assertArrayNotHasKey($field, $mine);
        }
        $escalated = $this->postJson("/api/v1/complaints/{$complaint->id}/escalate")->assertOk()->json('data');
        foreach ($internal as $field) {
            $this->assertArrayNotHasKey($field, $escalated);
        }

        Sanctum::actingAs($this->owner->fresh(), ['*']);
        $theirs = $this->getJson('/api/v1/complaints')->assertOk()->json('data.0');
        foreach ($internal as $field) {
            $this->assertArrayNotHasKey($field, $theirs);
        }
        $replied = $this->postJson("/api/v1/complaints/{$complaint->id}/reply", ['reply' => 'Yana uzr'])->assertOk()->json('data');
        foreach ($internal as $field) {
            $this->assertArrayNotHasKey($field, $replied);
        }
    }

    public function test_support_replies_are_throttled_per_member(): void
    {
        $complaint = $this->complaintOf($this->customer);
        $token = $this->signIn($this->makeMember(PlatformRole::SUPPORT));

        for ($i = 1; $i <= 20; $i++) {
            $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => "Javob {$i}"])->assertOk();
        }
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $token, ['text' => 'Yana'])->assertStatus(429);
        $this->assertSame(20, $complaint->replies()->count());

        // Another member is not held back by the first one's count
        $colleague = $this->signIn($this->makeMember(PlatformRole::SUPPORT));
        $this->adminJson('POST', "/complaints/{$complaint->uuid}/reply", $colleague, ['text' => 'Men ham'])->assertOk();
    }
}
