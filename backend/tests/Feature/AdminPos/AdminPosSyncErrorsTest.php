<?php

namespace Tests\Feature\AdminPos;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-24 Oflayn sinxron xatolari: rejected and conflicting offline operations of every till, and resolving them.
 */
class AdminPosSyncErrorsTest extends TestCase
{
    use RefreshDatabase;
    use AdminPosFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    public function test_the_list_has_unresolved_rejected_and_conflicting_operations_without_payloads(): void
    {
        $device = $this->device();
        $rejected = $this->syncOp($device, 'rejected', ['received_at' => now()->subHours(3)]);
        $conflict = $this->syncOp($device, 'conflict', ['type' => 'order.open', 'reason' => 'oversold', 'received_at' => now()->subHours(2)]);
        $this->syncOp($device, 'applied');
        $resolved = $this->syncOp($device, 'rejected', ['resolved_at' => now(), 'resolved_by' => $this->manager->id, 'resolution' => 'resolved', 'resolution_note' => 'Qo\'lda']);
        $foreign = $this->syncOp($this->device(['name' => 'Foreign'], $this->foreignStore), 'rejected', ['store_id' => $this->foreignStore->id, 'branch_id' => null, 'received_at' => now()->subMinutes(10)]);

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $response = $this->adminJson('GET', '/pos/sync-errors', $token)->assertOk();

        $this->assertSame([$foreign->op_id, $conflict->op_id, $rejected->op_id], array_column($response->json('data.data'), 'op_id'));
        $this->assertSame(['rejected' => 2, 'conflict' => 1, 'open' => 3], $response->json('data.counts'));
        $row = collect($response->json('data.data'))->firstWhere('op_id', $rejected->op_id);
        $this->assertSame(['rejected', 'order_cancelled', "To'lov olindi", 'Lifecycle Burger', 'Kassa 1'], [$row['status'], $row['reason'], $row['type_label'], $row['store']['name'], $row['device']['name']]);
        $this->assertArrayNotHasKey('payload', $row);

        $ids = fn (string $query) => array_column($this->adminJson('GET', '/pos/sync-errors' . $query, $token)->assertOk()->json('data.data'), 'op_id');
        $this->assertSame([$resolved->op_id], $ids('?state=resolved'));
        $this->assertCount(4, $ids('?state=all'));
        $this->assertSame([$conflict->op_id], $ids('?status=conflict'));
        $this->assertSame([$conflict->op_id], $ids('?type=order.open'));
        $this->assertSame([$foreign->op_id], $ids("?store_id={$this->foreignStore->id}"));
        $this->assertSame([$conflict->op_id, $rejected->op_id], $ids("?device={$device->uuid}"));
        $this->adminJson('GET', '/pos/sync-errors?type=bogus', $token)->assertStatus(422);
    }

    public function test_the_card_shows_the_payload_without_phones_cards_and_secrets(): void
    {
        $op = $this->syncOp($this->device());

        $data = $this->adminJson('GET', "/pos/sync-errors/{$op->op_id}", $this->signIn($this->makeMember(PlatformRole::SALES)))->assertOk()->json('data');

        $this->assertSame('50000', $data['payload']['amount']);
        $this->assertSame('***', $data['payload']['customer_phone']);
        $this->assertSame('***', $data['payload']['card']);
        $this->assertStringNotContainsString('998901234567', json_encode($data));
        $this->assertStringNotContainsString('8600', json_encode($data));

        $token = $this->signIn($this->makeMember(PlatformRole::SALES));
        $this->adminJson('GET', '/pos/sync-errors/' . \Illuminate\Support\Str::uuid(), $token)->assertNotFound()->assertJsonPath('code', 'not_found');
        $applied = $this->syncOp($this->device(['name' => 'Second']), 'applied');
        $this->adminJson('GET', "/pos/sync-errors/{$applied->op_id}", $token)->assertNotFound();
    }

    public function test_resolving_needs_stores_manage_a_step_up_and_a_reason_and_frees_the_shift(): void
    {
        $device = $this->device();
        $op = $this->syncOp($device, 'rejected');
        $shift = $this->shift(['device_id' => $device->id, 'status' => 'open', 'opened_at' => now()->subHours(5), 'closed_at' => null, 'closed_by' => null, 'expected_cash' => null, 'counted_cash' => null, 'difference' => null]);
        $this->assertSame([$op->op_id], app(\App\Domain\Pos\Sync\PosSyncErrors::class)->blockingShift($shift));

        $this->adminJson('POST', "/pos/sync-errors/{$op->op_id}/resolve", $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR)), ['resolution' => 'resolved', 'reason' => 'Hal qilindi'])->assertForbidden();

        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->signIn($sales);
        $this->adminJson('POST', "/pos/sync-errors/{$op->op_id}/resolve", $token, ['resolution' => 'resolved', 'reason' => 'Hal qilindi'])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($sales, $token);
        $this->adminJson('POST', "/pos/sync-errors/{$op->op_id}/resolve", $token, ['resolution' => 'resolved'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('POST', "/pos/sync-errors/{$op->op_id}/resolve", $token, ['resolution' => 'later', 'reason' => 'Hal qilindi'])->assertStatus(422)->assertJsonValidationErrors('resolution');

        $this->adminJson('POST', "/pos/sync-errors/{$op->op_id}/resolve", $token, ['resolution' => 'resolved', 'reason' => 'Egasi bilan gaplashildi, qo\'lda kiritildi'])->assertOk()
            ->assertJsonPath('message', 'Hal qilindi deb belgilandi.')
            ->assertJsonPath('data.resolution', 'resolved')
            ->assertJsonPath('data.resolved_by.name', $sales->name)
            ->assertJsonPath('data.resolution_note', 'Egasi bilan gaplashildi, qo\'lda kiritildi');

        $op->refresh();
        $this->assertSame([$sales->id, 'resolved'], [$op->resolved_by, $op->resolution]);
        $this->assertSame([], app(\App\Domain\Pos\Sync\PosSyncErrors::class)->blockingShift($shift));
        $this->assertEquals(['open' => 0, 'rejected' => 0, 'conflict' => 0], $this->adminJson('GET', '/pos/sync-errors', $token)->json('data.counts'));

        $audit = AuditLog::where('action', 'admin.pos.sync_error_resolved')->sole();
        $this->assertSame([$sales->id, $this->store->id, $op->id], [$audit->user_id, $audit->store_id, $audit->model_id]);
        $this->assertSame(1, AuditLog::where('action', 'pos.sync_error_resolved')->count());

        // Again: nothing changes and nothing more is recorded
        $this->adminJson('POST', "/pos/sync-errors/{$op->op_id}/resolve", $token, ['resolution' => 'dismissed', 'reason' => 'Yana'])->assertOk()->assertJsonPath('data.resolution', 'resolved');
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.sync_error_resolved')->count());
    }

    public function test_dismissing_a_conflict(): void
    {
        $conflict = $this->syncOp($this->device(), 'conflict');
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN));

        $this->adminJson('POST', "/pos/sync-errors/{$conflict->op_id}/resolve", $token, ['resolution' => 'dismissed', 'reason' => "Hech narsa qilish kerak emas"])->assertOk()
            ->assertJsonPath('message', 'Yopildi.')->assertJsonPath('data.resolution', 'dismissed');
        $this->adminJson('POST', '/pos/sync-errors/' . \Illuminate\Support\Str::uuid() . '/resolve', $token, ['resolution' => 'resolved', 'reason' => 'Yo\'q'])->assertNotFound();
        $applied = $this->syncOp($this->device(['name' => 'Second']), 'applied');
        $this->adminJson('POST', "/pos/sync-errors/{$applied->op_id}/resolve", $token, ['resolution' => 'resolved', 'reason' => 'Yo\'q'])->assertNotFound();
    }

    public function test_it_needs_devices_view(): void
    {
        $op = $this->syncOp($this->device());

        $this->adminJson('GET', '/pos/sync-errors', $this->signIn($this->makeMember(PlatformRole::FINANCE)))->assertForbidden();
        $this->adminJson('GET', "/pos/sync-errors/{$op->op_id}", $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertForbidden();
        $this->adminJson('GET', '/pos/sync-errors')->assertUnauthorized();
    }
}
