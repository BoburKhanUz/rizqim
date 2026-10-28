<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Fiscal\FiscalService;
use App\Http\Requests\Admin\AdminRules;
use App\Models\FiscalReceipt;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Pos\PosFiscal;
use App\Services\Admin\Pos\PosPage;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-24 Fiskal cheklar: the failed and pending fiscal receipts of every store (admin.devices.view). Retrying one:
 * admin.stores.manage, a step-up and a `reason`; it goes through FiscalService::retryNow, the same code as the owner's
 * "Qayta yuborish".
 */
#[Group('Admin')]
class AdminPosFiscalController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly PosFiscal $fiscal,
        private readonly FiscalService $service,
    ) {}

    /**
     * Fiscal receipts (all stores)
     *
     * Newest first, pages (`page`, `per_page` 1-100, default 30). Filters: `status` (open: failed or pending, the
     * default; failed; pending; issued; all), `driver` (the fiscal provider asked: sandbox, null, ..., external — a
     * receipt Payme/Click registered), `store_id`, `from` / `to` (dates in Asia/Tashkent), `q` (uuid start or receipt
     * number). `summary`: open failed and pending receipts in all and per provider (`by_driver`, with the latest error).
     * `error` is the provider's reason, cut to 300 characters.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . PosFiscal::ROW . '>, ' . PosPage::SHAPE . ', summary: array{failed: int, pending: int, by_driver: list<array{driver: string, failed: int, pending: int, last_error: string|null}>}}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'failed', 'pending', 'issued', 'all'])],
            'driver' => 'nullable|string|max:32',
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'q' => 'nullable|string|min:2|max:64',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $page = $this->fiscal->withRelations($this->fiscal->query($filters))->orderByDesc('id')->paginate(PosPage::perPage($filters['per_page'] ?? null));

        return $this->success(PosPage::of($page, collect($page->items())->map(fn (FiscalReceipt $receipt) => $this->fiscal->row($receipt))->all(), [
            'summary' => $this->fiscal->summary(),
        ]));
    }

    /**
     * A fiscal receipt
     *
     * The row, the whole error, the lines and payments as they were sent and the fiscal sign once registered. The
     * provider's raw answer is not shown.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(404, 'No such receipt (not_found)', type: self::ERROR)]
    public function show(string $uuid): JsonResponse
    {
        $receipt = FiscalReceipt::where('uuid', strtolower($uuid))->first();

        return $receipt === null ? $this->notFound() : $this->success($this->fiscal->detail($receipt));
    }

    /**
     * Retry a receipt now
     *
     * `reason`. A pending or failed receipt is sent to the provider at once (a failed one becomes pending first). The
     * answer is the receipt afterwards; it stays failed or pending when the provider still refuses (the message says
     * why). An issued receipt, or one recorded from Payme/Click: 422 `already_issued`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Issued already (already_issued)', type: self::ERROR)]
    public function retry(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $receipt = FiscalReceipt::where('uuid', strtolower($uuid))->first();
        if ($receipt === null) {
            return $this->notFound();
        }
        if (!$this->fiscal->retryable($receipt)) {
            return $this->refuse("Chek allaqachon ro'yxatdan o'tgan.", 'already_issued', 422);
        }

        $before = ['status' => $receipt->status->value, 'attempts' => (int) $receipt->attempts];
        $receipt = $this->service->retryNow($receipt);
        $after = ['status' => $receipt->status->value, 'attempts' => (int) $receipt->attempts];

        AdminAudit::for($request)->subject($receipt)->store((int) $receipt->store_id)->changes($before, $after)
            ->meta(array_filter(['driver' => $receipt->driver, 'error' => $receipt->error !== null ? mb_substr((string) $receipt->error, 0, 200) : null]));

        return $this->success($this->fiscal->detail($receipt->fresh() ?? $receipt), $receipt->status->value === 'issued'
            ? "Chek ro'yxatdan o'tdi."
            : "Chek hali ro'yxatdan o'tmadi: " . ($receipt->error ?? 'provayder javob bermadi.'));
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Chek topilmadi.', 'not_found', 404);
    }
}
