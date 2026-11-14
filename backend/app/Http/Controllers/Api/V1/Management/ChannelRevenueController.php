<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Brands\ChannelRevenueExport;
use App\Domain\Pos\Brands\ChannelRevenueReport;
use App\Enums\OrderChannel;
use App\Enums\Permission;
use App\Models\ChannelCommission;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P6-55 (module multi_brand, docs/API.md "Kanal bo'yicha sof tushum"): the channel net revenue report per period ×
 * channel × brand (orders, gross, discounts, refunds, commission, net; ChannelRevenueReport has the rules), its Excel
 * export, and the owner's commission percent per channel (aggregators take their own; settings.manage, audited).
 * Reading: reports.view (a branch-bound caller: their branch).
 */
#[Group('Owner: channel revenue', "Kanal va brend bo'yicha sof tushum, Excel, kanal komissiyalari (P6-55).")]
class ChannelRevenueController extends ManagementController
{
    /**
     * Channel net revenue
     *
     * `rows`: one per channel × brand ({channel, channel_label, commission_percent, commission_source: owner|billing,
     * brand_id (null: the house brand), brand_name, brand_code, brand_color, orders, gross, discounts, refunds,
     * commission, net}); `by_channel`, `by_brand`, `totals`. Money as decimal strings in so'm. The period is at most
     * 366 days (store time zone days; default today).
     */
    #[QueryParameter('from', description: 'First day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('branch_id', description: 'One branch (a branch-bound caller: their own).', type: 'int', required: false)]
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'branch_id' => 'nullable|integer|min:1',
        ]);
        $branch = $this->branchFilter($request, Permission::REPORTS_VIEW);
        $store = $this->currentStore($request);
        [$from, $to] = $this->period($request, $store);

        return $this->success(app(ChannelRevenueReport::class)->build($store, $branch?->id, $from, $to));
    }

    /**
     * Channel net revenue (Excel)
     *
     * The same report as an .xlsx workbook: "Kanal va brend", "Kanallar", "Brendlar". Recorded in the audit log.
     */
    #[QueryParameter('from', description: 'First day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('branch_id', description: 'One branch (a branch-bound caller: their own).', type: 'int', required: false)]
    public function export(Request $request): BinaryFileResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'branch_id' => 'nullable|integer|min:1',
        ]);
        $branch = $this->branchFilter($request, Permission::REPORTS_VIEW);
        $store = $this->currentStore($request);
        [$from, $to] = $this->period($request, $store);
        $report = app(ChannelRevenueReport::class)->build($store, $branch?->id, $from, $to);

        $path = tempnam(sys_get_temp_dir(), 'rizqim-channels-');
        if ($path === false) {
            abort(500, "Faylni tayyorlab bo'lmadi.");
        }
        app(ChannelRevenueExport::class)->write($report, $path);
        app(AuditLogger::class)->record('report.channel_revenue_exported', null, [], [], array_filter([
            'from' => $from,
            'to' => $to,
            'branch_id' => $branch?->id,
            'rows' => count($report['rows']),
        ], fn ($value) => $value !== null), ['actor' => $request->user(), 'store_id' => (int) $store->id]);

        return response()->download($path, "kanal-tushum-{$from}-{$to}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    /**
     * Channel commissions
     *
     * The owner's commission percent per channel ({channel, percent, note}); a channel without one uses the order's
     * own RIZQIM commission (docs/BILLING.md). `channels`: the channels a rate may name.
     */
    public function commissions(Request $request): JsonResponse
    {
        return $this->success([
            'rates' => $this->rates($this->storeId($request)),
            'channels' => OrderChannel::values(),
        ]);
    }

    /**
     * Replace the channel commissions
     *
     * `rates`: [{channel, percent (0–100, two decimals), note}]; a channel left out goes back to the order's own
     * commission. Audited.
     */
    public function updateCommissions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rates' => 'present|array|max:20',
            'rates.*.channel' => ['required', 'string', 'distinct', Rule::in(OrderChannel::values())],
            'rates.*.percent' => 'required|numeric|min:0|max:100|decimal:0,2',
            'rates.*.note' => 'nullable|string|max:255',
        ]);

        $storeId = $this->storeId($request);
        $before = $this->rates($storeId);
        DB::transaction(function () use ($storeId, $data) {
            ChannelCommission::where('store_id', $storeId)->delete();
            foreach ($data['rates'] as $rate) {
                ChannelCommission::create([
                    'store_id' => $storeId,
                    'channel' => $rate['channel'],
                    'percent' => \App\Support\Money::of((string) $rate['percent'])->toString(),
                    'note' => isset($rate['note']) && trim((string) $rate['note']) !== '' ? trim((string) $rate['note']) : null,
                ]);
            }
        });
        $after = $this->rates($storeId);
        if ($before !== $after) {
            app(AuditLogger::class)->record('settings.channel_commissions_changed', null, ['rates' => $before], ['rates' => $after], [], ['actor' => $request->user(), 'store_id' => $storeId]);
        }

        return $this->success(['rates' => $after, 'channels' => OrderChannel::values()], 'Komissiyalar saqlandi.');
    }

    /**
     * @return list<array{channel: string, percent: string, note: string|null}>
     */
    private function rates(int $storeId): array
    {
        return ChannelCommission::where('store_id', $storeId)->orderBy('channel')->get()
            ->map(fn (ChannelCommission $row) => ['channel' => $row->channel->value, 'percent' => (string) $row->percent, 'note' => $row->note])
            ->values()->all();
    }

    /**
     * The period asked for (validated in the action): default today in the store's time zone.
     *
     * @return array{0: string, 1: string}
     */
    private function period(Request $request, \App\Models\Store $store): array
    {
        $today = now()->setTimezone(\App\Support\StoreTimezone::of($store))->toDateString();
        $from = (string) ($request->input('from') ?: $request->input('to') ?: $today);
        $to = (string) ($request->input('to') ?: max($from, $today));
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => "Oxirgi kun birinchi kundan oldin bo'lmasin."]);
        }
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= ChannelRevenueReport::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'Davr ' . ChannelRevenueReport::MAX_RANGE_DAYS . " kundan oshmasin."]);
        }

        return [$from, $to];
    }
}
