<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Domain\Pos\Banquet\Payments\BanquetPaymentSettings;
use App\Domain\Pos\Banquet\Payments\BanquetReport;
use App\Domain\Pos\Banquet\Payments\BanquetReportExport;
use App\Enums\Permission;
use App\Services\Audit\AuditLogger;
use App\Support\UzValidation;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P6-53: the owner's side of banquet payments (partner web "Tadbirlar"): the payment rules (schedule template,
 * cancellation rule table, reminders; banquets.manage, audited) and the "Tadbirlar" report with its Excel export
 * (banquets.manage or reports.view; the customer's phone masked for a report-only reader). A branch-bound caller
 * sees their branch's events.
 */
#[Group('Owner: banquet payments', "Tadbir to'lov qoidalari va \"Tadbirlar\" hisoboti (P6-53).")]
class BanquetPaymentsController extends ManagementController
{
    public const MAX_DAYS = 366;

    /**
     * Banquet payment rules
     *
     * The schedule template a new event's schedule is planned from (`percent` of the total, `days_before` the event;
     * null: at booking; the final row takes the rest), the cancellation rule table (`min_days` → `retained_percent` of
     * what was paid) and the reminder days. Defaults when the store saved none (`version` 0).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPaymentSettings::SHAPE . '}')]
    public function settings(Request $request): JsonResponse
    {
        $this->authorizeFor($request, Permission::BANQUETS_MANAGE, null);

        return $this->success(app(BanquetPaymentSettings::class)->of($this->storeId($request)));
    }

    /**
     * Save banquet payment rules
     *
     * Any of: `schedule_template` (1–8 rows; the last a `final` row with `percent` null; at most one deposit; percents
     * up to 100 in all), `cancellation_rules` (1–10 rows, distinct `min_days`, one of them 0), `reminder_days` (days
     * before a due date, 0–60), `overdue_reminders`. `version` (from GET) refuses a stale save. Changes apply to new
     * schedules and cancellations from now on. Needs banquets.manage and the `banquet` module; audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPaymentSettings::SHAPE . '}')]
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedule_template' => 'sometimes|array|min:1|max:8',
            'schedule_template.*.kind' => ['required', 'string', Rule::in(BanquetPaymentKind::values())],
            'schedule_template.*.percent' => 'nullable|numeric|min:0.01|max:100',
            'schedule_template.*.days_before' => 'nullable|integer|min:0|max:730',
            'cancellation_rules' => 'sometimes|array|min:1|max:10',
            'cancellation_rules.*.min_days' => 'required|integer|min:0|max:730',
            'cancellation_rules.*.retained_percent' => 'required|numeric|min:0|max:100',
            'reminder_days' => 'sometimes|array|max:6',
            'reminder_days.*' => 'integer|min:0|max:60',
            'overdue_reminders' => 'sometimes|boolean',
            'version' => 'nullable|integer|min:0',
        ], UzValidation::messages());
        $this->authorizeFor($request, Permission::BANQUETS_MANAGE, null);

        return $this->success(app(BanquetPaymentSettings::class)->update($this->currentStore($request), $data, $request->user()), "Sozlamalar saqlandi.");
    }

    /**
     * "Tadbirlar" report
     *
     * The events dated `from`..`to` (default this month; at most 366 days), one branch with `branch_id`: per event and
     * in total — guests, total, paid (advances and bank transfers), outstanding, overdue, retained and refunded parts of
     * cancelled events, bank transfers still to give back. The customer's phone is masked without banquets.manage.
     */
    #[QueryParameter('branch_id', description: 'One branch (a branch-bound caller: their own).', type: 'int', required: false)]
    #[QueryParameter('from', description: 'First event date (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last event date (YYYY-MM-DD).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetReport::REPORT . '}')]
    public function report(Request $request): JsonResponse
    {
        $branch = $this->branchFilter($request, $this->readPermission($request));
        [$from, $to] = $this->range($request);

        return $this->success(app(BanquetReport::class)->build($this->storeId($request), $branch?->id, $from, $to, $this->masked($request)));
    }

    /**
     * "Tadbirlar" report (Excel)
     *
     * As GET /owner/pos/banquets/report: the events on the "Tadbirlar" sheet, the totals on "Jami". Recorded in the
     * audit log.
     */
    #[QueryParameter('branch_id', description: 'One branch (a branch-bound caller: their own).', type: 'int', required: false)]
    #[QueryParameter('from', description: 'First event date (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last event date (YYYY-MM-DD).', type: 'string', required: false)]
    public function export(Request $request): BinaryFileResponse
    {
        $branch = $this->branchFilter($request, $this->readPermission($request));
        [$from, $to] = $this->range($request);
        $mask = $this->masked($request);
        $report = app(BanquetReport::class)->build($this->storeId($request), $branch?->id, $from, $to, $mask);
        $path = tempnam(sys_get_temp_dir(), 'rizqim-banquets-');
        if ($path === false) {
            abort(500, "Faylni tayyorlab bo'lmadi.");
        }
        app(BanquetReportExport::class)->write($report, $path);
        app(AuditLogger::class)->record('banquet.report_exported', null, [], [], array_filter([
            'rows' => count($report['rows']),
            'from' => $from,
            'to' => $to,
            'branch_id' => $branch?->id,
            'phones_masked' => $mask ?: null,
        ], fn ($value) => $value !== null), ['actor' => $request->user()]);

        return response()->download($path, "tadbirlar-{$from}-{$to}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    private function readPermission(Request $request): Permission
    {
        return $this->permissions->allows($request->user(), Permission::BANQUETS_MANAGE) ? Permission::BANQUETS_MANAGE : Permission::REPORTS_VIEW;
    }

    private function masked(Request $request): bool
    {
        return !$this->permissions->allows($request->user(), Permission::BANQUETS_MANAGE);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $today = CarbonImmutable::now(\App\Support\StoreTimezone::of($this->currentStore($request)));
        $from = (string) ($request->input('from') ?: $today->startOfMonth()->toDateString());
        $to = (string) ($request->input('to') ?: CarbonImmutable::parse($from)->endOfMonth()->toDateString());
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => "Oxirgi kun birinchi kundan oldin bo'lmasin."]);
        }
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Davr ' . self::MAX_DAYS . " kundan oshmasin."]);
        }

        return [$from, $to];
    }
}
