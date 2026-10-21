<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Services\Auth\PermissionService;
use App\Services\Order\OwnerAnalyticsService;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Owner analytics', "Ega va menejer uchun savdo ko'rsatkichlari (serverda hisoblangan).")]
class OwnerAnalyticsController extends Controller
{
    use ApiResponse;

    /**
     * Sales figures of the owner's store (a branch manager's branch) for a period, computed on the server:
     * revenue, order counts, average check, by payment method and order type, best sellers, couriers, average
     * kitchen and delivery times, and a daily series for charts.
     *
     * `period` is today|week|month|year (the last 1/7/30/365 calendar days of the store's time zone, P2-33) or custom with `from` and `to`
     * (YYYY-MM-DD, at most 93 days). Orders count by the day they were created; `revenue` is the total of the delivered
     * ones. All money is a number in UZS (two decimals at most). Cached for 60 s per store/branch and period.
     */
    #[ApiDocResponse(200, type: "array{success: true, message: string, data: array{period: array{key: 'today'|'week'|'month'|'year'|'custom', from: string, to: string, days: int, timezone: string, basis: 'created_at'}, summary: array{orders_count: int, delivered_count: int, canceled_count: int, refunded_count: int, active_count: int, revenue: float, average_check: float}, by_status: array<string, int>, by_payment_method: list<array{method: string, orders: int, revenue: float}>, by_order_type: list<array{type: string, orders: int, revenue: float}>, by_channel: list<array{channel: string, orders: int, revenue: float}>, top_products: list<array{name: string, quantity: int|float, revenue: float}>, couriers: list<array{id: int, name: string, delivered_count: int}>, times: array{average_prep_seconds: int|null, average_delivery_seconds: int|null}, daily: list<array{date: string, orders: int, delivered: int, revenue: float}>, generated_at: string}}")]
    public function index(Request $request, OwnerAnalyticsService $analytics, PermissionService $permissions): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->store_id) {
            return $this->error('Ruxsat etilmagan.', [], 403);
        }
        // reports.view is checked by the route (with the store's overrides); the role check stays there too

        $maxDays = (int) config('orders.reports.max_custom_days', 93);
        $data = $request->validate([
            'period' => 'nullable|string|in:today,week,month,year,custom',
            'from' => 'nullable|required_if:period,custom|date_format:Y-m-d',
            'to' => [
                'nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $maxDays) {
                    $from = $request->input('from');
                    if (!is_string($from) || !is_string($value)
                        || CarbonImmutable::hasFormat($from, 'Y-m-d') === false || CarbonImmutable::hasFormat($value, 'Y-m-d') === false) {
                        return;
                    }
                    if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($value)) + 1 > $maxDays) {
                        $fail("Davr ko'pi bilan {$maxDays} kun bo'lishi mumkin.");
                    }
                },
            ],
        ], [
            'from.required_if' => 'Davrning boshlanish sanasini kiriting.',
            'to.required_if' => 'Davrning tugash sanasini kiriting.',
            'from.date_format' => "Boshlanish sanasi noto'g'ri (YYYY-MM-DD).",
            'to.date_format' => "Tugash sanasi noto'g'ri (YYYY-MM-DD).",
            'to.after_or_equal' => "Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.",
        ]);

        // Dates without a period mean a custom one
        $period = $data['period'] ?? (isset($data['from']) || isset($data['to']) ? 'custom' : 'today');
        if ($period === 'custom' && (!isset($data['from']) || !isset($data['to']))) {
            return response()->json([
                'success' => false,
                'message' => 'Davrning boshlanish va tugash sanalarini kiriting.',
                'data' => [],
                'errors' => ['from' => ['Davrning boshlanish va tugash sanalarini kiriting.']],
            ], 422);
        }

        // A branch manager sees their branch only; the owner (and a store-wide manager) the whole store
        // P2-33: a PIN session on a branch terminal sees the terminal's branch
        $branchId = $permissions->actingBranchId($user);

        // P2-33: days of the store's time zone, as GET /owner/reports/sales, so both screens show the same figures
        $timezone = \App\Support\StoreTimezone::of(\App\Models\Store::find((int) $user->store_id));

        return $this->success($analytics->forPeriod((int) $user->store_id, $branchId, $period, $data['from'] ?? null, $data['to'] ?? null, $timezone));
    }
}
