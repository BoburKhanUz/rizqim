<?php

namespace Tests\Feature\AdminPos;

use App\Enums\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4): the control centre's device row shows what the signatures of a till's
 * offline operations were worth in the last week, the number of its key and the store's mode. Never the key.
 */
class AdminPosDeviceSignaturesTest extends TestCase
{
    use RefreshDatabase;
    use AdminPosFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    public function test_a_device_row_counts_the_last_weeks_signature_statuses(): void
    {
        $till = $this->device(['name' => 'Kassa 1']);
        $till->forceFill(['sync_key' => base64_encode(random_bytes(32)), 'sync_key_id' => 2])->save();
        $other = $this->device(['name' => 'Kassa 2']);
        foreach (['ok', 'ok', 'ok', 'missing', 'bad', 'chain_gap', 'chain_gap', null] as $status) {
            $this->syncOp($till, 'applied', ['sig_status' => $status, 'reason' => null, 'error' => null]);
        }
        // Older than the week, and another till's
        $this->syncOp($till, 'applied', ['sig_status' => 'bad', 'received_at' => now()->subDays(8)]);
        $this->syncOp($other, 'applied', ['sig_status' => 'missing']);
        $this->store->forceFill(['pos_sync_enforce_signatures' => true])->save();

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $row = $this->adminJson('GET', "/pos/devices/{$till->uuid}", $token)->assertOk()->json('data.sync');

        $this->assertSame(['days' => 7, 'ok' => 3, 'missing' => 1, 'bad' => 1, 'chain_gap' => 2, 'key_id' => 2, 'enforce' => true], $row['signatures']);

        $response = $this->adminJson('GET', '/pos/devices', $token)->assertOk();
        $rows = collect($response->json('data.data'))->keyBy('name');
        $this->assertSame(['days' => 7, 'ok' => 3, 'missing' => 1, 'bad' => 1, 'chain_gap' => 2, 'key_id' => 2, 'enforce' => true], $rows['Kassa 1']['sync']['signatures']);
        $this->assertSame(['days' => 7, 'ok' => 0, 'missing' => 1, 'bad' => 0, 'chain_gap' => 0, 'key_id' => null, 'enforce' => true], $rows['Kassa 2']['sync']['signatures']);
        $this->assertStringNotContainsString('sync_key', $response->getContent());
        $this->assertStringNotContainsString('sync_chain', $response->getContent());
    }
}
