<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Sync\PosSyncService;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AdminFixtures;
use Tests\TestCase;

/**
 * Wave M5 S4-b (docs/scale/M5_DESIGN.md §5): the control centre's import of a till's rescue file
 * (POST /admin/pos/sync-import) — any store, admin.stores.manage with a step-up and a reason — and what an import
 * does when the till's own sync or another device's operation is in its way.
 */
class PosSyncImportAdminTest extends TestCase
{
    use AdminFixtures;
    use BuildsExportFiles;
    use PosSyncFixtures;
    use RefreshDatabase;
    use SignsOperations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     */
    private function file(array $operations): string
    {
        $uuid = (string) $this->till['device']->uuid;

        return self::exportFile($uuid, $this->till['sync_key']['kid'], (string) base64_decode($this->till['sync_key']['key'], true), $this->signed($operations, $this->till['sync_key'], $uuid));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function adminImport(?string $token, string $file, array $data = ['reason' => "Kassa buzildi, egasi so'radi"]): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return ($token !== null ? $this->withToken($token) : $this)->post('/api/v1/admin/pos/sync-import', $data + [
            'file' => UploadedFile::fake()->createWithContent('rizqim-navbat.rzq', $file),
        ], ['Accept' => 'application/json']);
    }

    private function ownerImport(string $file, ?User $user = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user ?? $this->owner, 'sanctum')->post('/api/v1/owner/pos/sync/import', [
            'file' => UploadedFile::fake()->createWithContent('rizqim-navbat.rzq', $file),
        ], ['Accept' => 'application/json']);
    }

    public function test_the_control_centre_imports_a_file_of_any_store_with_a_reason_and_a_step_up(): void
    {
        $sale = $this->walkInSale();
        $file = $this->file($sale['ops']);
        $member = $this->makeMember(PlatformRole::SUPERADMIN);

        // Signed in but not stepped up, no reason, nobody, a role that only reads
        $this->adminImport($this->signIn($member), $file)->assertStatus(403);
        $token = $this->confirmedSession($member);
        $this->adminImport($token, $file, [])->assertStatus(422);
        $this->adminImport(null, $file)->assertStatus(401);
        $reader = $this->makeMember(PlatformRole::OPERATOR);
        $this->adminImport($this->confirmedSession($reader), $file)->assertStatus(403);
        // The store's owner token is nobody here
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner, 'sanctum')->post('/api/v1/admin/pos/sync-import', ['reason' => 'sinov uchun', 'file' => UploadedFile::fake()->createWithContent('q.rzq', $file)], ['Accept' => 'application/json'])->assertStatus(403);
        $this->assertSame(0, PosSyncOperation::count());

        $response = $this->adminImport($this->confirmedSession($member), $file)->assertOk()
            ->assertJsonPath('data.complete', true)
            ->assertJsonPath('data.totals.applied', 4)
            ->assertJsonPath('data.file.device', (string) $this->till['device']->uuid)
            ->assertJsonCount(4, 'data.results');
        $this->assertSame(['ok', 'ok', 'ok', 'ok'], array_column($response->json('data.results'), 'sig_status'));

        // Applied in the till's own store
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertSame([$this->store->id, $this->till['device']->id], [(int) $order->store_id, (int) PosSyncOperation::where('op_id', $sale['ops'][0]['op_id'])->value('device_id')]);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());

        // The store's journal and the control centre's
        $store = AuditLog::where('action', 'pos.sync_imported')->sole();
        $this->assertSame([$member->id, $this->store->id, $this->till['device']->id, 4], [$store->user_id, $store->store_id, (int) $store->model_id, $store->payload_json['totals']['applied']]);
        $admin = AuditLog::where('action', 'admin.pos.sync_imported')->sole();
        $this->assertSame([$member->id, $this->store->id, "Kassa buzildi, egasi so'radi", 4], [$admin->user_id, $admin->store_id, $admin->payload_json['reason'], $admin->payload_json['totals']['applied']]);
        $this->assertStringNotContainsString($this->till['sync_key']['key'], (string) json_encode([$store->payload_json, $admin->payload_json]) . $response->getContent());

        // A refused file records nothing
        $this->adminImport($this->confirmedSession($member), str_replace('41000', '14000', $file))->assertStatus(422)->assertJsonPath('code', 'file_mac_invalid')->assertJsonPath('data.reason', 'file_mac_invalid');
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.sync_imported')->count());
    }

    public function test_while_the_till_itself_is_syncing_nothing_is_applied_and_the_file_is_uploaded_again(): void
    {
        $sale = $this->walkInSale();
        $file = $this->file($sale['ops']);
        // The till's batch holds its lock (PosSyncService waits 10 s for it: the test does not)
        $lock = Cache::lock(PosSyncService::lockName($this->till['device']), 120);
        $this->assertTrue($lock->get());
        Cache::partialMock()->shouldReceive('lock')->andReturnUsing(fn (string $name) => new class {
            public function block(int $seconds): bool
            {
                return false;
            }

            public function release(): bool
            {
                return true;
            }
        });

        $this->ownerImport($file)->assertOk()
            ->assertJsonPath('data.complete', false)
            ->assertJsonPath('data.stopped_reason', 'sync_in_progress')
            ->assertJsonPath('data.totals.pending', 4)
            ->assertJsonCount(0, 'data.results');
        $this->assertSame(0, PosSyncOperation::count());
    }

    public function test_an_operation_id_that_belongs_to_another_device_is_skipped_and_the_rest_is_applied(): void
    {
        // Another store's till already sent an operation with this id
        $foreign = $this->makeStore('Boshqa oshxona');
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreign);
        $foreignTill = $this->pairTerminal($foreignOwner, $this->makeBranch($foreign, 'Filial'));
        $taken = (string) Str::uuid();
        $this->withBearer($foreignTill['token'])->json('POST', '/api/v1/pos/sync', ['operations' => [
            ['op_id' => $taken, 'seq' => 1, 'type' => 'order.served', 'occurred_at' => now()->toJSON(), 'staff_id' => $foreignOwner->id, 'payload' => ['order' => (string) Str::uuid()]],
        ]])->assertOk();

        $sale = $this->walkInSale();
        $stray = ['op_id' => $taken] + $this->op('order.served', ['order' => $sale['order']]);
        // In the middle of the sale: open, bill, the stray one, pay, close
        $stray['seq'] = 3;
        $sale['ops'][2]['seq'] = 4;
        $sale['ops'][3]['seq'] = 5;
        $file = $this->file([$sale['ops'][0], $sale['ops'][1], $stray, $sale['ops'][2], $sale['ops'][3]]);

        $response = $this->ownerImport($file)->assertOk()->assertJsonPath('data.complete', true)->assertJsonCount(4, 'data.results')->assertJsonCount(1, 'data.skipped');

        $response->assertJsonPath('data.skipped.0.reason', 'op_id_conflict')->assertJsonPath('data.skipped.0.op_id', $taken)->assertJsonPath('data.skipped.0.line', 4);
        $this->assertSame(['applied', 'applied', 'applied', 'applied'], array_column($response->json('data.results'), 'status'));
        $this->assertSame(1, Payment::where('order_id', Order::where('external_id', $sale['order'])->value('id'))->count());
        // Nothing of the other store is shown or touched
        $this->assertStringNotContainsString((string) $foreignTill['device']->uuid, $response->getContent());
        $this->assertSame(1, PosSyncOperation::where('device_id', $foreignTill['device']->id)->count());
    }
}
