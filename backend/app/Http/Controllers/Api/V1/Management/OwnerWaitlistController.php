<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Waitlist\WaitlistPresenter;
use App\Domain\Pos\Waitlist\WaitlistStatus;
use App\Enums\Permission;
use App\Models\PosWaitlistEntry;
use App\Services\Auth\PermissionService;
use App\Support\UzValidation;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-24: the owner's read-only view of the hostess' waitlist: who waited, for how long and how it ended, by business
 * day. reports.view; never from a terminal PIN session; the guests' numbers are masked here.
 */
#[Group('Owner: table booking', "Stol broni sozlamalari: stol turi bo'yicha slot, bron muddati, ixtiyoriy bo'nak va kutish ro'yxati ko'rinishi (P6-24, P6-26).")]
class OwnerWaitlistController extends ManagementController
{
    public const MAX_DAYS = 31;

    public function __construct(PermissionService $permissions, private readonly WaitlistPresenter $entries)
    {
        parent::__construct($permissions);
    }

    /**
     * The waitlist, by day
     *
     * The branch's entries from `from` to `to` (business days, Y-m-d, `to` inclusive, at most 31 days, default
     * today), newest first, with the guest's number masked, and a `summary` (how many were seated, left or never
     * came, the wait quoted against the wait the seated parties had). Filter by `status`. At most 500 entries. Needs
     * reports.view; the hostess module (403 `module_disabled`).
     */
    #[QueryParameter('branch_id', description: "The branch; defaults to the caller's.", type: 'int')]
    #[QueryParameter('from', description: 'First business day, Y-m-d (default today).', type: 'string')]
    #[QueryParameter('to', description: 'Last business day, Y-m-d, inclusive.', type: 'string')]
    #[QueryParameter('status', description: 'One state: waiting, seated, canceled or no_show.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{branch_id: int, from: string, to: string, entries: list<' . WaitlistPresenter::ENTRY . '>, summary: ' . WaitlistPresenter::SUMMARY . '}}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'status' => ['nullable', 'string', Rule::in(WaitlistStatus::values())],
        ], UzValidation::messages());
        $branch = $this->branch($request, Permission::REPORTS_VIEW);
        app(PosModules::class)->assertEnabled($branch, PosModule::HOSTESS);

        $today = BusinessDay::of($branch);
        $from = $request->query('from', $today);
        $to = $request->query('to', $from);
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= self::MAX_DAYS) {
            return $this->error('Davr ' . self::MAX_DAYS . " kundan oshmasin.", ['reason' => 'range_too_long'], 422);
        }

        $all = PosWaitlistEntry::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('business_date', [$from, $to])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(500)
            ->get();
        $rows = $all->map(fn (PosWaitlistEntry $entry) => $this->entries->entry($entry, null, true))->all();
        if ($request->filled('status')) {
            $rows = array_values(array_filter($rows, fn (array $row) => $row['status'] === $request->string('status')->toString()));
        }

        return $this->success([
            'branch_id' => (int) $branch->id,
            'from' => (string) $from,
            'to' => (string) $to,
            'entries' => $rows,
            'summary' => $this->entries->summary($all),
        ]);
    }
}
