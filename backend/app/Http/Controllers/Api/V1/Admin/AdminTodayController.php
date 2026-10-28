<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Attention\AttentionCollector;
use App\Services\Admin\Today\PlatformStats;
use App\Services\Admin\Today\TodaySummary;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-13: Bugun, the control center's home page (admin.today.view): today's numbers from the aggregate tables
 * (App\Services\Admin\Today) and "Diqqat talab qiladi" from every section's attention sources. Read-only.
 */
#[Group('Admin')]
class AdminTodayController extends AdminController
{
    private const KPI_MONEY = 'array{value: string, yesterday_same_time: string, yesterday: string, last_week_same_time: string, last_week: string, vs_yesterday: array{direction: string, percent: string|null}, vs_last_week: array{direction: string, percent: string|null}}';

    private const KPI_COUNT = 'array{value: int, yesterday_same_time: int, yesterday: int, last_week_same_time: int, last_week: int, vs_yesterday: array{direction: string, percent: string|null}, vs_last_week: array{direction: string, percent: string|null}}';

    public function __construct(
        private readonly TodaySummary $summary,
        private readonly AttentionCollector $attention,
    ) {}

    /**
     * Today's numbers
     *
     * Asia/Tashkent day so far. `kpis`: `gmv`, `orders`, `commission`, `subscription_revenue`, `canceled_orders`, each
     * with `value` (today until now), `yesterday_same_time` and `last_week_same_time` (the same day of last week, up
     * to the same time of day), `yesterday` and `last_week` (whole days) and `vs_yesterday` / `vs_last_week`
     * (`direction`: up, down, same; `percent` of the earlier value, null when it was 0). Money as decimal strings.
     * GMV, orders and commission count marketplace orders only (channels telegram and mobile, not canceled, rejected
     * or refunded, by creation time); subscription revenue: invoices paid today. `channels`: every channel today
     * (POS too, `marketplace: false`). `regions`: orders and GMV today and active stores per region (`region_id`
     * null: not set). `stats_computed_at`: the aggregate job's last run for today; from `live_from` on the numbers
     * are counted live.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{date: string, timezone: string, generated_at: string, stats_computed_at: string|null, live_from: string, kpis: array{gmv: ' . self::KPI_MONEY . ', orders: ' . self::KPI_COUNT . ', commission: ' . self::KPI_MONEY . ', subscription_revenue: ' . self::KPI_MONEY . ', canceled_orders: ' . self::KPI_COUNT . '}, channels: list<array{channel: string, label: string, marketplace: bool, orders: int, gmv: string}>, regions: list<array{region_id: int|null, name: string, orders: int, gmv: string, active_stores: int}>}}')]
    public function index(): JsonResponse
    {
        return $this->success($this->summary->build());
    }

    /**
     * Needs attention
     *
     * One group per problem kind the member may see (each section's sources, each with its own permission),
     * critical first: `kind`, `label`, `count` (all of them), `severity` (critical, warning, info) and at most
     * `limit` rows (1–20, default 5), each with `title`, `subtitle`, `href` (the admin app path of its card),
     * `since` (when the problem started) and `store_id`. Kinds with nothing to show are left out: an empty list
     * means "Hammasi joyida".
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{groups: list<array{kind: string, label: string, count: int, severity: string, items: list<array{kind: string, severity: string, title: string, subtitle: string|null, href: string, since: string|null, store_id: int|null}>}>, generated_at: string}}')]
    public function attention(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ], UzValidation::messages());

        return $this->success([
            'groups' => $this->attention->collect($this->member($request), (int) ($data['limit'] ?? 5)),
            'generated_at' => PlatformStats::now()->toIso8601String(),
        ]);
    }
}
