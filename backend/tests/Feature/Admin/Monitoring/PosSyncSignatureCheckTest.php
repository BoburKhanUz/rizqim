<?php

namespace Tests\Feature\Admin\Monitoring;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\PosSyncOperation;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Feature\ManagementFixtures;
use Tests\Feature\MonitoringFixtures;
use Tests\TestCase;

/**
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4): RIZQIM's alert about the signed offline queue. A till that sent a badly
 * signed operation, or one after a lost line (`chain_gap`), in the rule's window has one open alert; unsigned
 * operations of old apps (`missing`) and good ones raise nothing; the alert resolves when the window has passed.
 */
class PosSyncSignatureCheckTest extends TestCase
{
    use ManagementFixtures;
    use MonitoringFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->setUpManagement();
        AlertRule::ensureAll();
    }

    private function op(Device $device, ?string $sigStatus, array $attributes = []): PosSyncOperation
    {
        static $seq = 0;

        return PosSyncOperation::forceCreate($attributes + [
            'store_id' => $device->store_id, 'branch_id' => $device->branch_id, 'device_id' => $device->id,
            'op_id' => (string) Str::uuid(), 'seq' => ++$seq, 'type' => 'order.served', 'payload' => [],
            'status' => 'applied', 'received_at' => now()->subMinutes(5), 'sig_status' => $sigStatus,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Alert>
     */
    private function open()
    {
        return Alert::query()->where('kind', AlertKind::POS_SYNC_SIGNATURE->value)->unresolved()->orderBy('id')->get();
    }

    public function test_a_till_with_a_bad_signature_or_a_chain_gap_opens_one_alert_and_it_resolves_with_the_window(): void
    {
        $rule = $this->onlyRule(AlertKind::POS_SYNC_SIGNATURE);
        $this->assertSame([1, 1440], [$rule->threshold, $rule->window_minutes]);
        $till = $this->terminal($this->store, ['name' => 'Kassa 1']);
        $quiet = $this->terminal($this->store, ['name' => 'Kassa 2']);
        $this->op($till, 'ok');
        $this->op($quiet, 'missing');
        $this->op($quiet, 'ok');
        $this->op($quiet, null);

        app(AlertEvaluator::class)->run();
        $this->assertCount(0, $this->open());

        $this->op($till, 'bad');
        $this->op($till, 'bad');
        $this->op($till, 'chain_gap');
        app(AlertEvaluator::class)->run();
        app(AlertEvaluator::class)->run();

        $alert = $this->open()->sole();
        $this->assertSame([$till->uuid, 'warning', $this->store->id], [$alert->subject_key, $alert->severity, $alert->store_id]);
        $this->assertStringContainsString('Kassa 1', $alert->title);
        $this->assertStringContainsString($this->store->name, $alert->title);
        $this->assertSame([2, 1, false], [$alert->details['bad'], $alert->details['chain_gap'], $alert->details['enforce']]);
        // Names and numbers only: nothing of the key or the macs
        $this->assertStringNotContainsString('sync_key', (string) json_encode($alert->getAttributes()));

        // A day later nothing new came: the problem is over
        $this->travel(25)->hours();
        app(AlertEvaluator::class)->run();
        $this->assertCount(0, $this->open());
    }

    public function test_the_threshold_counts_the_problem_operations_of_one_till(): void
    {
        $this->onlyRule(AlertKind::POS_SYNC_SIGNATURE, ['threshold' => 3]);
        $till = $this->terminal($this->store);
        $this->op($till, 'bad');
        $this->op($till, 'chain_gap');
        app(AlertEvaluator::class)->run();
        $this->assertCount(0, $this->open());

        $this->op($till, 'bad');
        app(AlertEvaluator::class)->run();
        $this->assertCount(1, $this->open());
    }
}
