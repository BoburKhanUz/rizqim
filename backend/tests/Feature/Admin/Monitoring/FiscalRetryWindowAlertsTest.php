<?php

namespace Tests\Feature\Admin\Monitoring;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalService;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\Checks\PartitionsCheck;
use App\Support\Partitions\PartitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\MonitoringFixtures;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * M3: the fiscal retry window in the alerts. A receipt the sweep failed because it outlived the window is a sale the
 * tax office never saw (subject `expired`); a receipt still pending beyond it means the sweep is not running
 * (`pending_expired`); a missing protective trigger reported by PartitionManager (`trigger_missing`) is critical.
 */
class FiscalRetryWindowAlertsTest extends TestCase
{
    use MonitoringFixtures;
    use OrderLifecycleFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        AlertRule::ensureAll();
        config(['fiscal.retry.max_age_days' => 30]);
    }

    private function run_(): void
    {
        app(AlertEvaluator::class)->run();
    }

    /** @return \Illuminate\Support\Collection<string, Alert> */
    private function open(AlertKind $kind)
    {
        return Alert::query()->where('kind', $kind->value)->unresolved()->get()->keyBy('subject_key');
    }

    public function test_a_receipt_still_pending_beyond_the_retry_window_is_critical_until_it_is_dealt_with(): void
    {
        $this->onlyRule(AlertKind::FISCAL_FAILURES, ['threshold' => 1, 'window_minutes' => 60]);
        $store = $this->makeStore('Eski kassa');
        // Created in the past (moving created_at later would move the row between partitions: not how receipts live)
        $receipt = $this->failedReceipt($store, ['status' => FiscalReceiptStatus::PENDING->value, 'error' => null, 'created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)]);
        // A recent pending one is the normal retry path, not an alert
        $this->failedReceipt($store, ['status' => FiscalReceiptStatus::PENDING->value, 'error' => null]);

        $this->run_();

        $alert = $this->open(AlertKind::FISCAL_FAILURES)->get('pending_expired');
        $this->assertNotNull($alert);
        $this->assertSame('critical', $alert->severity);
        $this->assertStringStartsWith("1 ta savdo 30 kundan ortiq fiskal cheksiz turibdi (soliqda ko'rinmaydi)", $alert->title);
        $this->assertArrayNotHasKey('global', $this->open(AlertKind::FISCAL_FAILURES)->all());

        // The sweep fails it: no longer pending, now an expired failure
        app(FiscalService::class)->sweep();
        $this->assertSame(FiscalReceiptStatus::FAILED->value, DB::table('fiscal_receipts')->where('id', $receipt->id)->value('status'));
        $this->run_();
        $open = $this->open(AlertKind::FISCAL_FAILURES);
        $this->assertArrayNotHasKey('pending_expired', $open->all());
        $this->assertSame('critical', $open['expired']->severity);
        $this->assertStringStartsWith("1 ta savdoning fiskal cheki 30 kun ichida soliq tizimida ro'yxatdan o'tmadi", $open['expired']->title);
        $this->assertSame('Oxirgi 60 daqiqada 1 ta fiskal chek yiqildi (shundan 1 tasi 30 kundan ortiq kutib qolgan)', $open['global']->title);
        $this->assertSame(1, $open['global']->details['expired']);
    }

    public function test_an_expired_failure_is_told_apart_from_an_ordinary_one_and_both_resolve_with_the_window(): void
    {
        $this->onlyRule(AlertKind::FISCAL_FAILURES, ['threshold' => 1, 'window_minutes' => 60]);
        $store = $this->makeStore('Ikki xil');
        $this->failedReceipt($store);

        $this->run_();
        $open = $this->open(AlertKind::FISCAL_FAILURES);
        $this->assertSame(['global'], $open->keys()->all());
        $this->assertSame('Oxirgi 60 daqiqada 1 ta fiskal chek yiqildi', $open['global']->title);

        $this->failedReceipt($store, ['created_at' => now()->subDays(40)]);
        $this->run_();
        $this->assertEqualsCanonicalizing(['global', 'expired'], $this->open(AlertKind::FISCAL_FAILURES)->keys()->all());

        // Out of the rule's window: both resolve
        $this->travel(2)->hours();
        $this->run_();
        $this->assertSame([], $this->open(AlertKind::FISCAL_FAILURES)->keys()->all());
    }

    public function test_a_partition_problem_code_the_check_does_not_know_is_shown_with_its_message_and_trigger_missing_is_critical(): void
    {
        $this->onlyRule(AlertKind::PARTITIONS);
        $problems = [
            ['table' => 'fiscal_receipts', 'code' => 'trigger_missing', 'message' => "fiscal_receipts: fiscal_receipt_keys_insert triggeri yo'q: fiskal cheklar takrorlanishdan himoyasiz"],
            ['table' => null, 'code' => 'something_new', 'message' => 'Yangi muammo matni'],
        ];
        $this->app->bind(PartitionsCheck::class, fn () => new class(app(PartitionManager::class), $problems) extends PartitionsCheck
        {
            public function __construct(PartitionManager $partitions, private readonly array $extra)
            {
                parent::__construct($partitions);
            }

            protected function status(): ?array
            {
                $status = parent::status();
                $status['problems'] = array_merge($status['problems'], $this->extra);

                return $status;
            }
        });

        $this->run_();

        $open = $this->open(AlertKind::PARTITIONS);
        $this->assertSame('critical', $open['trigger_missing:fiscal_receipts']->severity);
        $this->assertStringContainsString('fiskal cheklar takrorlanishdan himoyasiz', $open['trigger_missing:fiscal_receipts']->title);
        $this->assertSame('warning', $open['something_new:global']->severity);
        $this->assertSame('Yangi muammo matni', $open['something_new:global']->title);

        $this->app->offsetUnset(PartitionsCheck::class);
        $this->run_();
        $this->assertSame([], $this->open(AlertKind::PARTITIONS)->keys()->all());
    }
}
