<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Pos\PosOverview;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-24: the numbers on the tabs of POS va obunalar (admin.subscriptions.manage, admin.finance.view or
 * admin.devices.view; each group needs its own permission and is null without it).
 */
#[Group('Admin')]
class AdminPosOverviewController extends AdminController
{
    public function __construct(
        private readonly PosOverview $overview,
    ) {}

    /**
     * POS va obunalar: numbers
     *
     * `subscriptions` (current subscriptions per status, `trial_ending`: trials ending in 3 days), `invoices` (issued,
     * overdue, paid, void counts and the `outstanding` sum of the unpaid ones), `devices` (active devices: online,
     * offline, never seen, outdated, per type; revoked), `shifts` (the last 30 days: closed with a cash difference,
     * force-closed, open), `fiscal` (failed and pending receipts and `by_driver`: per fiscal provider) and
     * `sync_errors` (offline operations nobody resolved). A group the member may not see is null.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{subscriptions: array{counts: array<string, int>, trial_ending: int, trial_ending_days: int}|null, invoices: array{issued: int, overdue: int, paid: int, void: int, outstanding: string}|null, devices: array{total: int, online: int, offline: int, never: int, revoked: int, outdated: int, by_type: array<string, int>}|null, shifts: array{difference: int, force_closed: int, open: int, all: int}|null, fiscal: array{failed: int, pending: int, by_driver: list<array{driver: string, failed: int, pending: int, last_error: string|null}>}|null, sync_errors: array{rejected: int, conflict: int, open: int}|null}}')]
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->overview->for($this->member($request)));
    }
}
