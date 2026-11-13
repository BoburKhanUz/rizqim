<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Vouchers\VoucherCode;
use App\Domain\Pos\Vouchers\VoucherExport;
use App\Domain\Pos\Vouchers\VoucherPresenter;
use App\Domain\Pos\Vouchers\VoucherReport;
use App\Domain\Pos\Vouchers\VoucherService;
use App\Domain\Pos\Vouchers\VoucherStatus;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P6-43: the owner's gift certificates (partner web "Sertifikatlar", docs/API.md "Sertifikat va vaucher"): the list
 * with filters, one certificate with its history, block and unblock (vouchers.manage, audited), the period report
 * (sold, redeemed, outstanding liability) and the Excel export. Reading: vouchers.manage or reports.view. A
 * branch-bound caller sees the certificates sold in their branch; another store's: 404. The buyer's phone is masked
 * except in one certificate's own view for vouchers.manage.
 */
#[Group('Owner: vouchers', "Sovg'a sertifikatlari: ro'yxat, tarix, bloklash, hisobot va Excel (P6-43).")]
class VouchersController extends ManagementController
{
    public const MAX_DAYS = 366;

    /**
     * Gift certificates
     *
     * Newest first, paginated (`per_page` up to 100). Filters: `status` (active, used, blocked, expired — `expired`
     * includes the active ones past their date), `q` (code, buyer name or phone digits), `branch_id` (where sold),
     * `from`/`to` (sold on these dates).
     */
    #[QueryParameter('status', description: 'active, used, blocked or expired.', type: 'string', required: false)]
    #[QueryParameter('q', description: 'Code, buyer name or phone digits.', type: 'string', required: false)]
    #[QueryParameter('branch_id', description: 'Where it was sold.', type: 'int', required: false)]
    #[QueryParameter('from', description: 'Sold on or after (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Sold on or before (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('per_page', description: 'Up to 100 (default 30).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{vouchers: list<' . VoucherPresenter::VOUCHER . '>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['per_page' => 'nullable|integer|min:1|max:100']);
        $page = $this->filtered($request)->orderByDesc('id')->paginate((int) $request->input('per_page', 30));
        $presenter = app(VoucherPresenter::class);

        return $this->success([
            'vouchers' => collect($page->items())->map(fn (Voucher $voucher) => $presenter->voucher($voucher))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Gift certificate report
     *
     * For business days `from`..`to` (default today; at most 366 days): sold (the advances, by tender), redeemed on
     * bills, refunded back onto certificates, the period's net change of the liability; as of today the outstanding
     * liability (what certificates can still pay), and what is blocked or expired unused. `branch_id`: one branch.
     */
    #[QueryParameter('branch_id', description: 'One branch (a branch-bound caller: their own).', type: 'int', required: false)]
    #[QueryParameter('from', description: 'First business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . VoucherReport::REPORT . '}')]
    public function report(Request $request): JsonResponse
    {
        $branch = $this->branchFilter($request, $this->readPermission($request));
        [$from, $to] = $this->range($request, $branch);

        return $this->success(app(VoucherReport::class)->build($this->storeId($request), $branch?->id, $from, $to));
    }

    /**
     * Gift certificates (Excel)
     *
     * The filtered list (as GET /owner/pos/vouchers, at most 5 000 rows; buyer phones masked) on the "Sertifikatlar"
     * sheet and the period report on "Hisobot". Each export is recorded in the audit log.
     */
    #[QueryParameter('status', description: 'active, used, blocked or expired.', type: 'string', required: false)]
    #[QueryParameter('q', description: 'Code, buyer name or phone digits.', type: 'string', required: false)]
    #[QueryParameter('branch_id', description: 'Where it was sold.', type: 'int', required: false)]
    #[QueryParameter('from', description: 'First business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last business day (YYYY-MM-DD).', type: 'string', required: false)]
    public function export(Request $request): BinaryFileResponse
    {
        $branch = $this->branchFilter($request, $this->readPermission($request));
        [$from, $to] = $this->range($request, $branch);
        $storeId = $this->storeId($request);
        $vouchers = $this->filtered($request)->orderByDesc('id')->limit(5000)->get();
        $report = app(VoucherReport::class)->build($storeId, $branch?->id, $from, $to);

        $path = tempnam(sys_get_temp_dir(), 'rizqim-vouchers-');
        if ($path === false) {
            abort(500, "Faylni tayyorlab bo'lmadi.");
        }
        app(VoucherExport::class)->write($vouchers, $report, Branch::where('store_id', $storeId)->pluck('name', 'id')->all(), $path);

        app(AuditLogger::class)->record('voucher.exported', null, [], [], array_filter([
            'rows' => $vouchers->count(),
            'from' => $from,
            'to' => $to,
            'branch_id' => $branch?->id,
            'status' => $request->input('status'),
        ], fn ($value) => $value !== null), ['actor' => $request->user()]);

        return response()->download($path, "sertifikatlar-{$from}-{$to}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    /**
     * One gift certificate, with its history
     *
     * Every entry (sale, redemptions with their bills, refunds back onto it, blocks), newest first (up to 200). The
     * buyer's phone is shown in full to vouchers.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{voucher: ' . VoucherPresenter::VOUCHER . ', entries: list<' . VoucherPresenter::ENTRY . '>}}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $voucher = $this->voucher($request, $uuid, $this->readPermission($request));
        $presenter = app(VoucherPresenter::class);
        $full = $this->permissions->allows($request->user(), Permission::VOUCHERS_MANAGE, $voucher);

        return $this->success([
            'voucher' => $presenter->voucher($voucher, $full),
            'entries' => VoucherEntry::where('voucher_id', $voucher->id)->with('user:id,name')->orderByDesc('id')->limit(200)->get()
                ->map(fn (VoucherEntry $entry) => $presenter->entry($entry))->values()->all(),
        ]);
    }

    /**
     * Block a gift certificate
     *
     * It pays nothing until unblocked (lost, stolen, sold by mistake); `reason` required. 409 `voucher_blocked` when it
     * already is. Needs vouchers.manage; audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . VoucherPresenter::VOUCHER . '}')]
    public function block(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:300']);
        $voucher = $this->voucher($request, $uuid, Permission::VOUCHERS_MANAGE);
        $voucher = app(VoucherService::class)->block($voucher, trim((string) $data['reason']), $request->user());

        return $this->success(app(VoucherPresenter::class)->voucher($voucher, true), 'Sertifikat bloklandi.');
    }

    /**
     * Unblock a gift certificate
     *
     * It pays again what is left on it (optional `reason`). 409 `voucher_not_blocked`. Needs vouchers.manage; audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . VoucherPresenter::VOUCHER . '}')]
    public function unblock(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:300']);
        $voucher = $this->voucher($request, $uuid, Permission::VOUCHERS_MANAGE);
        $voucher = app(VoucherService::class)->unblock($voucher, filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null, $request->user());

        return $this->success(app(VoucherPresenter::class)->voucher($voucher, true), 'Sertifikat blokdan chiqarildi.');
    }

    /**
     * @return Builder<Voucher>
     */
    private function filtered(Request $request): Builder
    {
        $request->validate([
            'status' => ['nullable', 'string', Rule::in(VoucherStatus::values())],
            'q' => 'nullable|string|max:100',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $branch = $this->branchFilter($request, $this->readPermission($request));
        $today = now()->setTimezone(\App\Support\StoreTimezone::of($this->currentStore($request)))->toDateString();

        $query = Voucher::where('store_id', $this->storeId($request))
            ->when($branch !== null, fn (Builder $query) => $query->where('branch_id', $branch?->id))
            ->when($request->filled('from'), fn (Builder $query) => $query->where('valid_from', '>=', (string) $request->input('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->where('valid_from', '<=', (string) $request->input('to')));

        $status = VoucherStatus::tryFrom((string) $request->input('status'));
        if ($status === VoucherStatus::EXPIRED) {
            $query->where(fn (Builder $query) => $query->where('status', VoucherStatus::EXPIRED->value)
                ->orWhere(fn (Builder $query) => $query->where('status', VoucherStatus::ACTIVE->value)->where('valid_until', '<', $today)));
        } elseif ($status === VoucherStatus::ACTIVE) {
            $query->where('status', VoucherStatus::ACTIVE->value)->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today));
        } elseif ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $code = VoucherCode::normalize($q);
            $digits = \App\Domain\Pos\Booking\UzPhone::searchDigits($q);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)) . '%';
            $query->where(function (Builder $query) use ($code, $digits, $like, $q) {
                $query->whereRaw('lower(buyer_name) like ?', [$like])
                    ->orWhere('code', 'like', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], strtoupper($q)) . '%');
                if ($code !== null) {
                    $query->orWhere('code', $code);
                }
                if ($digits !== null) {
                    $query->orWhere('buyer_phone', 'like', "%{$digits}%");
                }
            });
        }

        return $query;
    }

    private function voucher(Request $request, string $uuid, Permission $permission): Voucher
    {
        $voucher = Str::isUuid($uuid) ? Voucher::where('store_id', $this->storeId($request))->where('uuid', $uuid)->first() : null;
        if ($voucher === null) {
            throw new HttpResponseException($this->error('Sertifikat topilmadi.', ['reason' => 'voucher_not_found'], 404));
        }
        $this->authorizeFor($request, $permission, $voucher);

        return $voucher;
    }

    private function readPermission(Request $request): Permission
    {
        return $this->permissions->allows($request->user(), Permission::VOUCHERS_MANAGE) ? Permission::VOUCHERS_MANAGE : Permission::REPORTS_VIEW;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request, ?Branch $branch): array
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $today = $branch !== null ? \App\Domain\Pos\Shifts\BusinessDay::of($branch)
            : now()->setTimezone(\App\Support\StoreTimezone::of($this->currentStore($request)))->toDateString();
        $from = (string) ($request->input('from') ?: $request->input('to') ?: $today);
        $to = (string) ($request->input('to') ?: max($from, $today));
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => "Oxirgi kun birinchi kundan oldin bo'lmasin."]);
        }
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Davr ' . self::MAX_DAYS . " kundan oshmasin."]);
        }

        return [$from, $to];
    }
}
