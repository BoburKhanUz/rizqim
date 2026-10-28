<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\PosShift;
use App\Services\Admin\Pos\PosPage;
use App\Services\Admin\Pos\PosShifts;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-24 Smenalar (admin.devices.view): the till shifts of every store, read-only: cash differences and shifts a
 * manager closed by force.
 */
#[Group('Admin')]
class AdminPosShiftController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly PosShifts $shifts,
    ) {}

    /**
     * Shifts (all stores)
     *
     * Newest first, pages (`page`, `per_page` 1-100, default 30). Filters: `kind` (difference: closed with a cash
     * difference, counted minus expected, so a negative one is missing cash; force_closed; open), `store_id`, `from` /
     * `to` (business dates, YYYY-MM-DD). `counts` per kind under the other filters.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . PosShifts::ROW . '>, ' . PosPage::SHAPE . ', counts: array{difference: int, force_closed: int, open: int, all: int}}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::in(['difference', 'force_closed', 'open'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $page = $this->shifts->withRelations($this->shifts->query($filters))
            ->orderByDesc('opened_at')->orderByDesc('id')->paginate(PosPage::perPage($filters['per_page'] ?? null));

        return $this->success(PosPage::of($page, collect($page->items())->map(fn (PosShift $shift) => $this->shifts->row($shift))->all(), [
            'counts' => $this->shifts->counts($filters),
        ]));
    }

    /**
     * A shift
     *
     * The row, the banknotes counted, the Z report as kept at closing (`summary`), who forced the close and the cash
     * movements (in and out of the drawer).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(404, 'No such shift (not_found)', type: self::ERROR)]
    public function show(string $uuid): JsonResponse
    {
        $shift = $this->shifts->withRelations(PosShift::query()->where('uuid', strtolower($uuid)))->first();

        return $shift === null
            ? $this->refuse('Smena topilmadi.', 'not_found', 404)
            : $this->success($this->shifts->detail($shift));
    }
}
