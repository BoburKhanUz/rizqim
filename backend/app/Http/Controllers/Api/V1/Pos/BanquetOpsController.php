<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Banquet\Ops\BanquetContracts;
use App\Domain\Pos\Banquet\Ops\BanquetEventDay;
use App\Domain\Pos\Banquet\Ops\BanquetOpsExport;
use App\Domain\Pos\Banquet\Ops\BanquetOpsSettings;
use App\Domain\Pos\Banquet\Ops\BanquetProduction;
use App\Domain\Pos\Banquet\Ops\BanquetSettlementService;
use App\Domain\Pos\Banquet\Ops\BanquetWarnings;
use App\Domain\Pos\Banquet\Ops\OshCalculator;
use App\Domain\Pos\Billing\BillPresenter;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\BanquetEvent;
use App\Models\Branch;
use App\Models\Store;
use App\Services\Audit\AuditLogger;
use App\Support\StoreTimezone;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P6-54: the kitchen's production sheet, the osh calculator and the shopping list, the contract, the event day (the
 * tab, the guests who came, the staff) and the final settlement of a banquet event, for the till and the web panel
 * (docs/API.md "Tadbirlar: ishlab chiqarish va yakuniy hisob"). A terminal PIN session or a staff sign-in in the
 * caller's store, narrowed to the branch they act in; another store's or branch's event: 404. The kitchen's papers
 * carry no customer data; the contract (the customer's name and phone) needs banquets.manage. The production papers,
 * the contract and the start of the event day are new flows (`pos.module:banquet`); the settlement is a payment and
 * never asks the module.
 */
#[Group('POS: banquet operations', "Tadbir: ishlab chiqarish varag'i, osh kalkulyatori, bozorlik, shartnoma, tadbir kuni va yakuniy hisob (P6-54).")]
class BanquetOpsController extends MenuStaffController
{
    public const MAX_SHOPPING_DAYS = 62;

    /**
     * Production sheet of an event
     *
     * The dishes of the event's package × the planned guests (max of actual, guaranteed, minimum, plus the store's
     * reserve %), in portions and kg by kitchen station, the osh calculator's result (kazans and ingredients) and the
     * event's warnings. No customer data. banquets.manage, pos.take_payment, reports.view or kitchen.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetProduction::SHEET . '}')]
    public function production(Request $request, string $uuid): JsonResponse
    {
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE, Permission::POS_TAKE_PAYMENT, Permission::REPORTS_VIEW, Permission::KITCHEN_MANAGE]);

        return $this->success(app(BanquetProduction::class)->sheet($event));
    }

    /**
     * Production sheet as Excel
     */
    public function productionExport(Request $request, string $uuid): BinaryFileResponse
    {
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE, Permission::POS_TAKE_PAYMENT, Permission::REPORTS_VIEW, Permission::KITCHEN_MANAGE]);
        $sheet = app(BanquetProduction::class)->sheet($event);
        $path = $this->tempPath();
        app(BanquetOpsExport::class)->production($sheet, $path);
        app(AuditLogger::class)->record('banquet.production_exported', $event, [], [], [], ['actor' => $request->user()]);

        return response()->download($path, "ishlab-chiqarish-{$sheet['event']['event_date']}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /**
     * Shopping list (bozorlik) of a date range
     *
     * The branch's events on `from`..`to` (the store's dates, at most 62 days; default today) that hold their halls
     * (hold, confirmed, in progress): their dishes summed by product and unit, the osh ingredients summed, each event's
     * planned guests. No customer data. banquets.manage, pos.take_payment, reports.view or kitchen.manage.
     */
    #[QueryParameter('branch_id', 'The branch (default: the one the caller works in).', type: 'int')]
    #[QueryParameter('from', 'First day, Y-m-d (store time zone).', type: 'string')]
    #[QueryParameter('to', 'Last day, Y-m-d, inclusive.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetProduction::SHOPPING . '}')]
    public function shopping(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
        ]);
        [$branch, $from, $to] = $this->shoppingRange($request);

        return $this->success(app(BanquetProduction::class)->shoppingList($branch, $from, $to));
    }

    /**
     * Shopping list as Excel
     */
    #[QueryParameter('branch_id', 'The branch (default: the one the caller works in).', type: 'int')]
    #[QueryParameter('from', 'First day, Y-m-d (store time zone).', type: 'string')]
    #[QueryParameter('to', 'Last day, Y-m-d, inclusive.', type: 'string')]
    public function shoppingExport(Request $request): BinaryFileResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
        ]);
        [$branch, $from, $to] = $this->shoppingRange($request);
        $list = app(BanquetProduction::class)->shoppingList($branch, $from, $to);
        $path = $this->tempPath();
        app(BanquetOpsExport::class)->shopping($list, $path);
        app(AuditLogger::class)->record('banquet.shopping_exported', $branch, [], [], ['from' => $from, 'to' => $to], ['actor' => $request->user()]);

        return response()->download($path, "bozorlik-{$from}-{$to}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /**
     * Osh calculator
     *
     * For `persons` guests (1–5000): kg of osh, the ingredients by the store's ratios and the kazans. banquets.manage,
     * pos.take_payment, reports.view or kitchen.manage.
     */
    #[QueryParameter('persons', 'How many guests (1–5000).', type: 'int', required: true)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . OshCalculator::RESULT . '}')]
    public function osh(Request $request): JsonResponse
    {
        $data = $request->validate(['persons' => 'required|integer|min:1|max:5000']);
        $storeId = $this->storeId($request);
        $this->anyPermission($request, [Permission::BANQUETS_MANAGE, Permission::POS_TAKE_PAYMENT, Permission::REPORTS_VIEW, Permission::KITCHEN_MANAGE], null);

        return $this->success(OshCalculator::calculate((int) $data['persons'], app(BanquetOpsSettings::class)->of($storeId)['osh']));
    }

    /**
     * Event warnings
     *
     * Guests over the store's thresholds, a time outside the window: warnings only, never a refusal.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{warnings: list<' . BanquetWarnings::WARNING . '>}}')]
    public function warnings(Request $request, string $uuid): JsonResponse
    {
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE, Permission::POS_TAKE_PAYMENT, Permission::REPORTS_VIEW]);

        return $this->success(['warnings' => app(BanquetWarnings::class)->of($event)]);
    }

    /**
     * Contract (shartnoma)
     *
     * The store's contract template filled from the event: `text` (and `lines`) to print, the `fields` used, the
     * warnings. `contract_no` is null until POST .../contract/number. banquets.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetContracts::DOCUMENT . '}')]
    public function contract(Request $request, string $uuid): JsonResponse
    {
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE]);

        return $this->success(app(BanquetContracts::class)->document($event));
    }

    /**
     * Give the contract its number
     *
     * "<year>-<seq>" per store and year, once (the same number again on a retry). Audited. banquets.manage.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Recommended: a new UUID per action, the same one when retrying it.', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetContracts::DOCUMENT . '}')]
    public function numberContract(Request $request, string $uuid): JsonResponse
    {
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE]);
        $contracts = app(BanquetContracts::class);
        $event = $contracts->number($event, $request->user());

        return $this->success($contracts->document($event), 'Shartnoma raqami berildi.');
    }

    /**
     * Start the event day
     *
     * A confirmed event opens its tab: a hall order bound to the event (`order_uuid`; drinks and extra dishes are its
     * ordinary rounds) and becomes `in_progress`. Optional `waiter_id`, `version`. Again: the same tab. 409
     * `invalid_transition` (not confirmed), `stale_version`. banquets.manage or pos.take_payment.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Recommended: a new UUID per action, the same one when retrying it.', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{event: array{uuid: string, status: string, version: int, order_uuid: string|null, started_at: string|null}}}')]
    public function start(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'version' => 'nullable|integer|min:1',
            'waiter_id' => 'nullable|integer|min:1|max:999999999999999999',
        ]);
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE, Permission::POS_TAKE_PAYMENT]);
        $user = $request->user();
        $event = app(BanquetEventDay::class)->start($event, $user, $this->permissions->terminalOf($user)?->id, isset($data['version']) ? (int) $data['version'] : null, isset($data['waiter_id']) ? (int) $data['waiter_id'] : null);

        return $this->success(['event' => self::eventState($event)], 'Tadbir boshlandi.');
    }

    /**
     * Set the guests who came
     *
     * `guests_actual` (0–100000), optional `version`. The final bill uses max(actual, guaranteed, minimum). Audited.
     * banquets.manage.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Recommended: a new UUID per action, the same one when retrying it.', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{event: array{uuid: string, status: string, version: int, order_uuid: string|null, started_at: string|null}, settlement: ' . BanquetSettlementService::PREVIEW . '}}')]
    public function guests(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'guests_actual' => 'required|integer|min:0|max:100000',
            'version' => 'nullable|integer|min:1',
        ]);
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE]);
        $event = app(BanquetEventDay::class)->setGuestsActual($event, (int) $data['guests_actual'], $request->user(), isset($data['version']) ? (int) $data['version'] : null);

        return $this->success(['event' => self::eventState($event), 'settlement' => app(BanquetSettlementService::class)->preview($event)], 'Mehmonlar soni saqlandi.');
    }

    /**
     * Staff assigned to the event
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{staff: list<array{id: int, name: string|null}>}}')]
    public function staff(Request $request, string $uuid): JsonResponse
    {
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE, Permission::POS_TAKE_PAYMENT]);

        return $this->success(['staff' => self::staffList($event)]);
    }

    /**
     * Assign the event's staff
     *
     * `user_ids`: the waiters who serve the event (staff of the store, up to 100; an empty list clears it). A settled
     * event counts once for each of them in the staff pay rules' event rate (kind `banquet`). Audited. banquets.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{staff: list<array{id: int, name: string|null}>}}')]
    public function updateStaff(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => 'present|array|max:100',
            'user_ids.*' => 'integer|min:1|max:999999999999999999',
        ]);
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE]);
        $event = app(BanquetEventDay::class)->setStaff($event, array_values(array_map('intval', $data['user_ids'])), $request->user());

        return $this->success(['staff' => self::staffList($event)], 'Xodimlar saqlandi.');
    }

    /**
     * Final settlement (preview)
     *
     * What the final bill is now: the billed guests, the package, hall and extras lines, the event-day order, the
     * total, the advances paid (and offset), what is left to pay or to give back; with the settlement once made.
     * banquets.manage or pos.take_payment.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetSettlementService::PREVIEW . '}')]
    public function settlement(Request $request, string $uuid): JsonResponse
    {
        $event = $this->event($request, $uuid, [Permission::BANQUETS_MANAGE, Permission::POS_TAKE_PAYMENT]);

        return $this->success(app(BanquetSettlementService::class)->preview($event));
    }

    /**
     * Settle the event
     *
     * At the till: the package, hall rent and extras join the event's order as its `banquet` charge, the order is billed
     * as one bill and the paid advances are offset on it (prepayment, in the open shift: `shift_uuid` or the
     * terminal's). The answer carries the `bill`: pay what is left on POST /pos/bills/{uuid}/payments with any tender;
     * once it is covered ONE sale receipt is issued for the full total (the advances as its prepayment part), the order
     * closes and the event becomes `settled`. Advances over the total are refunded against their advance receipts (cash
     * from the drawer needs pos.refund or a manager's `approval_token`; else `excess_status` stays `pending` — POST
     * .../settlement/refund-excess). Retrying before money is on the bill recomputes it. 409 `invalid_transition`,
     * `order_closed`, `shift_not_open`; 422 `mxik_missing`. pos.take_payment.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per settlement, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{settlement: ' . BanquetSettlementService::PREVIEW . ', bill: ' . BillPresenter::BILL . '}}')]
    public function settle(Request $request, string $uuid): JsonResponse
    {
        $this->requireKey($request);
        $data = $request->validate([
            'shift_uuid' => 'nullable|uuid',
            'version' => 'nullable|integer|min:1',
            'approval_token' => 'nullable|string|max:255',
        ]);
        $event = $this->event($request, $uuid, [Permission::POS_TAKE_PAYMENT]);
        $user = $request->user();
        $service = app(BanquetSettlementService::class);
        $result = $service->settle($event, $user, $data['shift_uuid'] ?? null, $this->permissions->terminalOf($user), isset($data['version']) ? (int) $data['version'] : null, $data['approval_token'] ?? null);

        return $this->success([
            'settlement' => $service->preview($event),
            'bill' => app(BillPresenter::class)->bill($result['bill']),
        ], 'Yakuniy hisob tayyor.');
    }

    /**
     * Refund the advances over the total
     *
     * After the final bill is covered: what the advances hold beyond it goes back against their advance receipts,
     * newest first (cash from the open shift's drawer: pos.refund or a manager's `approval_token`). pos.take_payment.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per refund, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetSettlementService::PREVIEW . '}')]
    public function refundExcess(Request $request, string $uuid): JsonResponse
    {
        $this->requireKey($request);
        $data = $request->validate(['approval_token' => 'nullable|string|max:255']);
        $event = $this->event($request, $uuid, [Permission::POS_TAKE_PAYMENT]);
        $user = $request->user();
        $service = app(BanquetSettlementService::class);
        $service->refundExcess($event, $user, $data['approval_token'] ?? null, $this->permissions->terminalOf($user)?->id);

        return $this->success($service->preview($event), 'Ortiqcha zaklad qaytarildi.');
    }

    /**
     * @return array{uuid: string, status: string, version: int, order_uuid: string|null, started_at: string|null}
     */
    private static function eventState(BanquetEvent $event): array
    {
        $event->refresh();

        return [
            'uuid' => (string) $event->uuid,
            'status' => $event->status->value,
            'version' => (int) $event->version,
            'order_uuid' => $event->order()->value('uuid'),
            'started_at' => $event->started_at?->toJSON(),
        ];
    }

    /**
     * @return list<array{id: int, name: string|null}>
     */
    private static function staffList(BanquetEvent $event): array
    {
        $ids = BanquetEventDay::staffIds($event);

        return \App\Models\User::whereIn('id', $ids === [] ? [0] : $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn (\App\Models\User $user) => ['id' => (int) $user->id, 'name' => $user->name])->values()->all();
    }

    /**
     * The caller's event: their store and the branch they act in (another one: 404), with one of the permissions.
     *
     * @param  list<Permission>  $permissions
     */
    private function event(Request $request, string $uuid, array $permissions): BanquetEvent
    {
        $acting = $this->permissions->actingBranchId($request->user());
        $event = BanquetEvent::where('store_id', $this->storeId($request))
            ->where('uuid', $uuid)
            ->when($acting !== null, fn ($query) => $query->where('branch_id', $acting))
            ->first();
        if ($event === null) {
            throw new HttpResponseException($this->error('Tadbir topilmadi.', [], 404));
        }
        $this->anyPermission($request, $permissions, $event);

        return $event;
    }

    /**
     * @param  list<Permission>  $permissions
     */
    private function anyPermission(Request $request, array $permissions, mixed $subject): void
    {
        foreach ($permissions as $permission) {
            if ($this->permissions->allows($request->user(), $permission, $subject)) {
                return;
            }
        }

        throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
    }

    /**
     * @return array{0: Branch, 1: string, 2: string}
     */
    private function shoppingRange(Request $request): array
    {
        $user = $request->user();
        $branch = null;
        foreach ([Permission::BANQUETS_MANAGE, Permission::POS_TAKE_PAYMENT, Permission::REPORTS_VIEW, Permission::KITCHEN_MANAGE] as $permission) {
            if ($this->permissions->allows($user, $permission)) {
                $branch = $this->branch($request, $permission);
                break;
            }
        }
        if ($branch === null) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }
        $tz = StoreTimezone::of(Store::find($branch->store_id));
        $from = (string) ($request->input('from') ?: Carbon::now($tz)->toDateString());
        $to = (string) ($request->input('to') ?: $from);
        if ($to < $from || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > self::MAX_SHOPPING_DAYS - 1) {
            throw new HttpResponseException($this->error("Davr noto'g'ri: ko'pi bilan " . self::MAX_SHOPPING_DAYS . ' kun.', ['errors' => ['to' => ["Davr noto'g'ri."]]], 422));
        }

        return [$branch, $from, $to];
    }

    private function requireKey(Request $request): void
    {
        $key = $request->headers->get('Idempotency-Key');
        if ($key === null || trim($key) === '') {
            throw new HttpResponseException($this->error("Idempotency-Key sarlavhasi majburiy: pul bilan bog'liq so'rov qayta yuborilganda ikki marta yozilmasligi uchun.", ['reason' => 'idempotency_key_required'], 428));
        }
    }

    private function tempPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rizqim-banquet-');
        if ($path === false) {
            abort(500, "Faylni tayyorlab bo'lmadi.");
        }

        return $path;
    }
}
