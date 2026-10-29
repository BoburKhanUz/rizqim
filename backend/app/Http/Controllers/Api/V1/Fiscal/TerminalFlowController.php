<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Fiscal\Gateways\FiscalGatewayException;
use App\Domain\Fiscal\Gateways\FiscalGatewayManager;
use App\Domain\Fiscal\Gateways\SmartKassaGateway;
use App\Domain\Fiscal\Gateways\SmartKassaRouting;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\TerminalFlow\BankTerminalPresenter;
use App\Domain\Pos\TerminalFlow\BankTerminalReconciler;
use App\Domain\Pos\TerminalFlow\CardMode;
use App\Domain\Pos\TerminalFlow\TerminalChecklist;
use App\Domain\Pos\TerminalFlow\TerminalSetup;
use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Http\Controllers\Api\V1\PosBilling\PosBankTerminalController;
use App\Models\BankTerminalReport;
use App\Models\Branch;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P6-16 (D5): the card flow of each branch for the partner web's fiscal page: how the branch takes cards (a bank
 * terminal in "faqat terminal" mode, a smart kassa, none), the installation checklist, the difference that alerts
 * the owner, which tills are smart kassas, and the bank terminal day reports compared with the Z. Setup is
 * settings.manage; the reports are read with finance.view or reports.view and entered or corrected with
 * finance.manage or pos.close_shift. Never from a terminal PIN session. Behind the `terminal_card_flow` module.
 */
#[Group('Owner: fiscal', "Fiskal sozlamalar (STIR, soliq rejimi, drayver, xizmatlar MXIK kodlari), fiskal cheklar ro'yxati va qayta yuborish (P3-11).")]
class TerminalFlowController extends ManagementController
{
    public const DEVICE = 'array{uuid: string, name: string|null, branch_id: int|null, smart_kassa: bool, fiscal_kassa_id: string|null, kassa_status: array{registered: bool, kassa_id: string|null, message: string|null}|null, last_seen_at: string|null}';

    public const BRANCH = 'array{branch: array{id: int, name: string}, module_enabled: bool, setup: ' . TerminalSetup::VIEW . ', devices: list<' . self::DEVICE . '>}';

    public const OVERVIEW = 'array{fiscal: array{enabled: bool, active_driver: string, smart_kassa_driver: string}, card_modes: list<array{value: string, label: string}>, checklist_items: list<array{key: string, label: string, hint: string}>, branches: list<' . self::BRANCH . '>}';

    /**
     * Card flow of the branches
     *
     * Every branch the caller sees (`branch_id` narrows to one): whether its format has the terminal_card_flow module
     * (`module_enabled`), its setup — `card_mode` (terminal_only: a bank terminal its TXKM switched to "faqat
     * terminal"; smart_kassa: Rizqim on an Android smart kassa, receipts through the provider's virtual kassa; none;
     * null: not chosen), the installation `checklist` (old kassa not used for sales, bank terminal mode set, 2D
     * scanner present) with who ticked each and when, `difference_threshold`, `ask_bank_total` — and its POS tills
     * with their smart kassa flag. `fiscal`: the store's fiscal mode and where a smart kassa's receipts go.
     * settings.manage.
     */
    #[QueryParameter('branch_id', description: 'Filial.', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::OVERVIEW . '}')]
    public function index(Request $request, TerminalSetup $setup, PosModules $modules): JsonResponse
    {
        $store = $this->currentStore($request);
        $request->validate(['branch_id' => UzValidation::rowId()]);
        $only = $this->branchFilter($request, Permission::SETTINGS_MANAGE);

        $branches = $only !== null ? collect([$only]) : Branch::where('store_id', $store->id)->orderBy('id')->get();
        $devices = Device::query()
            ->where('store_id', $store->id)
            ->where('type', DeviceType::POS->value)
            ->whereNull('revoked_at')
            ->whereIn('branch_id', $branches->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('branch_id');
        $settings = FiscalSettings::of($store);

        return $this->success([
            'fiscal' => [
                'enabled' => $settings->enabled(),
                'active_driver' => $settings->driver(),
                'smart_kassa_driver' => $settings->smartKassaDriver(),
            ],
            'card_modes' => array_map(fn (CardMode $mode) => ['value' => $mode->value, 'label' => $mode->label()], CardMode::cases()),
            'checklist_items' => array_map(fn (string $key, array $item) => ['key' => $key, 'label' => $item[0], 'hint' => $item[1]], array_keys(TerminalChecklist::ITEMS), array_values(TerminalChecklist::ITEMS)),
            'branches' => $branches->map(fn (Branch $branch) => [
                'branch' => ['id' => (int) $branch->id, 'name' => (string) $branch->name],
                'module_enabled' => $modules->enabled($branch, PosModule::TERMINAL_CARD_FLOW),
                'setup' => $setup->present($branch),
                'devices' => ($devices->get($branch->id) ?? collect())->map(fn (Device $device) => self::device($device, null))->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * Change a branch's card flow
     *
     * Send only what changes: `card_mode` terminal_only | smart_kassa | none (null: not chosen), `checklist`
     * {old_kassa_unused, bank_terminal_mode, scanner_2d: bool} (ticking records who and when, unticking clears it),
     * `difference_threshold` (so'm, 0–100000000: a bank terminal total further than this from the Z's card total
     * alerts the owner; 0: any difference), `ask_bank_total` (the till asks for the bank terminal's day total at the
     * Z), `shared_terminal` (the branch's tills share one bank terminal, or a till runs several shifts a day, settled
     * once per business day: one bank report covers every shift of that day — enter the day's total at the last Z).
     * Audited. settings.manage; 403 `module_disabled` without the terminal_card_flow module.
     */
    #[BodyParameter('card_mode', required: false, type: "'terminal_only'|'smart_kassa'|'none'|null")]
    #[BodyParameter('checklist', required: false, infer: false, type: 'array{old_kassa_unused?: bool, bank_terminal_mode?: bool, scanner_2d?: bool}')]
    #[BodyParameter('difference_threshold', required: false, type: 'string')]
    #[BodyParameter('ask_bank_total', required: false, type: 'bool')]
    #[BodyParameter('shared_terminal', required: false, type: 'bool')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . TerminalSetup::VIEW . '}')]
    public function update(Request $request, TerminalSetup $setup, int $branch): JsonResponse
    {
        $model = $this->branch($request, Permission::SETTINGS_MANAGE, $branch);
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'card_mode' => ['sometimes', 'nullable', 'string', Rule::in(CardMode::values())],
            'checklist' => ['sometimes', 'array'],
            'checklist.*' => ['boolean'],
            'difference_threshold' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'ask_bank_total' => ['sometimes', 'boolean'],
            'shared_terminal' => ['sometimes', 'boolean'],
        ], UzValidation::messages([
            'card_mode.in' => "Karta rejimi noto'g'ri: terminal_only, smart_kassa yoki none.",
        ]), [
            'card_mode' => 'Karta rejimi',
            'checklist' => "O'rnatish ro'yxati",
            'difference_threshold' => 'Farq chegarasi',
            'ask_bank_total' => "Z da bank terminali summasini so'rash",
            'shared_terminal' => 'Umumiy bank terminali',
        ]);
        foreach (array_keys((array) ($data['checklist'] ?? [])) as $key) {
            if (!in_array($key, TerminalChecklist::keys(), true)) {
                throw ValidationException::withMessages(["checklist.{$key}" => "Noma'lum band: {$key}."]);
            }
        }

        $input = [];
        if (array_key_exists('card_mode', $data)) {
            $input['card_mode'] = $data['card_mode'] !== null ? (string) $data['card_mode'] : null;
        }
        if (isset($data['checklist'])) {
            $input['checklist'] = array_map(fn ($done) => (bool) $done, (array) $data['checklist']);
        }
        if (isset($data['difference_threshold'])) {
            $input['difference_threshold'] = (string) $data['difference_threshold'];
        }
        if (array_key_exists('ask_bank_total', $data)) {
            $input['ask_bank_total'] = (bool) $data['ask_bank_total'];
        }
        if (array_key_exists('shared_terminal', $data)) {
            $input['shared_terminal'] = (bool) $data['shared_terminal'];
        }

        return $this->success($setup->update($model, $input, $user), 'Saqlandi.');
    }

    /**
     * Mark a till as a smart kassa
     *
     * `smart_kassa`: this POS terminal is an Android smart kassa (Sunmi P2/P3...): in fiscal mode its receipts go to
     * the store's `smart_kassa_driver` (the provider's virtual kassa bound to it); `fiscal_kassa_id`: the id the
     * provider knows that kassa by (null clears it): 6–32 latin letters, digits and dashes (stored upper case), used
     * by no other active device of the store and no active smart kassa anywhere, and — on a smart kassa — a kassa of
     * the store's STIR at the provider (422 `fiscal_kassa_id` otherwise; the store needs its STIR first).
     * `kassa_status`: what the provider says about it (null when the driver cannot say). Audited. settings.manage; a terminal of another branch the caller does not manage: 404;
     * 403 `module_disabled` without the terminal_card_flow module on the till's branch.
     */
    #[BodyParameter('smart_kassa', required: false, type: 'bool')]
    #[BodyParameter('fiscal_kassa_id', required: false, type: 'string|null')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::DEVICE . '}')]
    public function updateDevice(Request $request, PosModules $modules, AuditLogger $audit, SmartKassaRouting $routing, string $uuid): JsonResponse
    {
        $store = $this->currentStore($request);
        if (is_string($request->input('fiscal_kassa_id'))) {
            $request->merge(['fiscal_kassa_id' => SmartKassaRouting::normalize($request->input('fiscal_kassa_id'))]);
        }
        $data = $request->validate([
            'smart_kassa' => ['sometimes', 'boolean'],
            'fiscal_kassa_id' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z0-9][A-Z0-9-]{5,31}$/'],
        ], UzValidation::messages([
            'fiscal_kassa_id.regex' => "Kassa raqami 6–32 belgi: lotin harflari, raqamlar va \"-\".",
        ]), ['smart_kassa' => 'Smart-kassa', 'fiscal_kassa_id' => 'Provayder kassa raqami']);

        $device = Str::isUuid($uuid) ? Device::where('store_id', $store->id)->where('uuid', $uuid)
            ->where('type', DeviceType::POS->value)->whereNull('revoked_at')->first() : null;
        $branch = $device?->branch_id !== null ? Branch::where('store_id', $store->id)->find($device->branch_id) : null;
        if ($device === null || $branch === null) {
            return $this->error('Kassa topilmadi.', [], 404);
        }
        $this->authorizeFor($request, Permission::SETTINGS_MANAGE, $branch);
        $modules->assertEnabled($branch, PosModule::TERMINAL_CARD_FLOW);

        $before = ['smart_kassa' => (bool) $device->getAttribute('smart_kassa'), 'fiscal_kassa_id' => $device->getAttribute('fiscal_kassa_id')];
        if (array_key_exists('smart_kassa', $data)) {
            $device->forceFill(['smart_kassa' => (bool) $data['smart_kassa']]);
        }
        if (array_key_exists('fiscal_kassa_id', $data)) {
            $device->forceFill(['fiscal_kassa_id' => SmartKassaRouting::normalize($data['fiscal_kassa_id'] ?? null)]);
        }
        if ($device->isDirty()) {
            $this->assertKassaIdFree($device, $store, $routing);
            try {
                $device->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['fiscal_kassa_id' => self::KASSA_TAKEN]);
            }
            $audit->record('devices.smart_kassa_changed', $device, $before, [
                'smart_kassa' => (bool) $device->getAttribute('smart_kassa'),
                'fiscal_kassa_id' => $device->getAttribute('fiscal_kassa_id'),
            ], [], ['store_id' => (int) $store->id, 'branch_id' => (int) $branch->id]);
        }

        return $this->success(self::device($device, $store), 'Saqlandi.');
    }

    /**
     * Bank terminal reports
     *
     * The bank terminal day totals entered at the Z (or after it), newest business day first: the branches the caller
     * sees (`branch_id` narrows to one), a business day range (`from`, `to`, YYYY-MM-DD), `over_threshold=1` for the
     * mismatches only (an alerted report stays one after a correction); at most `limit` (50, up to 200). A shared
     * terminal's report covers several shifts (`shared`, `shifts`; `final` once all are closed). finance.view or
     * reports.view.
     */
    #[QueryParameter('branch_id', description: 'Filial.', type: 'int', required: false)]
    #[QueryParameter('from', description: 'Business day from (YYYY-MM-DD)', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Business day to (YYYY-MM-DD)', type: 'string', required: false)]
    #[QueryParameter('over_threshold', description: 'Only the mismatches.', type: 'bool', required: false)]
    #[QueryParameter('limit', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . BankTerminalPresenter::REPORT . '>}')]
    public function reports(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $data = $request->validate([
            'branch_id' => UzValidation::rowId(),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'over_threshold' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);
        /** @var User $user */
        $user = $request->user();
        $permission = $this->permissions->allows($user, Permission::FINANCE_VIEW) ? Permission::FINANCE_VIEW : Permission::REPORTS_VIEW;
        $branch = $this->branchFilter($request, $permission);

        $reports = BankTerminalReport::query()
            ->where('store_id', $store->id)
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->when(isset($data['from']), fn ($query) => $query->where('business_date', '>=', $data['from']))
            ->when(isset($data['to']), fn ($query) => $query->where('business_date', '<=', $data['to']))
            ->when($request->boolean('over_threshold'), fn ($query) => $query->where('over_threshold', true))
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->limit((int) ($data['limit'] ?? 50))
            ->get();
        $shifts = PosShift::whereIn('id', $reports->pluck('shift_id'))->get()->keyBy('id');

        return $this->success($reports->map(fn (BankTerminalReport $report) => BankTerminalPresenter::present($report, $shifts->get($report->shift_id)))->values()->all());
    }

    /**
     * Enter or correct a shift's bank terminal report
     *
     * As the till's PUT /pos/shifts/{uuid}/bank-report (same body), from the panel: for a shift whose Z went without
     * it, or to correct it. Audited (the replaced entry stays in `history`); a final difference above the threshold
     * alerts the owner, and a correction never clears an alert. An open shift: finance.manage or pos.close_shift (the
     * latter sees the comparison only once final); a closed one: finance.manage only (403 `bank_report_locked`).
     * 403 `module_disabled` without the terminal_card_flow module on the shift's branch.
     */
    #[BodyParameter('source', description: 'manual | import | provider (manual by default).', required: false, type: "'manual'|'import'|'provider'")]
    #[BodyParameter('bank_total', description: "manual: the bank terminal's day total (decimal string).", required: false, type: 'string')]
    #[BodyParameter('bank_count', description: 'manual: how many card payments the slip lists.', required: false, type: 'int')]
    #[BodyParameter('transactions', description: "import: the bank report's rows.", required: false, infer: false, type: 'list<array{rrn?: string|null, amount: string}>')]
    #[BodyParameter('terminal_ref', description: "The bank terminal's id or name (optional).", required: false, type: 'string')]
    #[BodyParameter('note', required: false, type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BankTerminalPresenter::REPORT . '}')]
    public function enter(Request $request, BankTerminalReconciler $reconciler, PosModules $modules, string $uuid): JsonResponse
    {
        $store = $this->currentStore($request);
        $data = $request->validate(PosBankTerminalController::rules());
        $shift = Str::isUuid($uuid) ? PosShift::where('store_id', $store->id)->where('uuid', $uuid)->first() : null;
        $branch = $shift !== null ? Branch::find($shift->branch_id) : null;
        if ($shift === null || $branch === null) {
            return $this->error('Smena topilmadi.', [], 404);
        }
        /** @var User $user */
        $user = $request->user();
        $finance = $this->permissions->allows($user, Permission::FINANCE_MANAGE);
        $this->authorizeFor($request, $finance ? Permission::FINANCE_MANAGE : Permission::POS_CLOSE_SHIFT, $branch);
        $modules->assertEnabled($branch, PosModule::TERMINAL_CARD_FLOW);
        PosBankTerminalController::assertMayCorrect($this->permissions, $user, $shift);

        $report = $reconciler->record($shift, PosBankTerminalController::input($data), $user);

        return $this->success(
            // Review P6 S#5: without finance.manage, the comparison only once final (as the till sees it)
            BankTerminalPresenter::present($report, $shift, hideUntilFinal: !$finance),
            $report->over_threshold ? "Saqlandi. Bank terminali va Z mos emas: egasiga xabar yuborildi." : 'Saqlandi.',
        );
    }

    private const KASSA_TAKEN = "Bu kassa raqami boshqa qurilmaga biriktirilgan.";

    /**
     * Review P6 S#4: a smart kassa's provider id belongs to one till: not used by another active device of the store,
     * nor by an active smart kassa of any store (the database's partial unique indexes back both); and a smart
     * kassa's id must be a kassa of the store's STIR at its provider (SmartKassaRouting::belongs). 422 otherwise.
     *
     * @throws ValidationException
     */
    private function assertKassaIdFree(Device $device, Store $store, SmartKassaRouting $routing): void
    {
        $kassaId = $device->getAttribute('fiscal_kassa_id');
        if (blank($kassaId)) {
            return;
        }
        $smart = (bool) $device->getAttribute('smart_kassa');

        $taken = Device::query()
            ->whereKeyNot($device->id)
            ->whereNull('revoked_at')
            ->where('fiscal_kassa_id', $kassaId)
            ->where(fn ($query) => $smart
                ? $query->where('store_id', $store->id)->orWhere('smart_kassa', true)
                : $query->where('store_id', $store->id))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['fiscal_kassa_id' => self::KASSA_TAKEN]);
        }

        if (!$smart || !$device->isDirty(['smart_kassa', 'fiscal_kassa_id'])) {
            return;
        }
        if (blank($store->tin)) {
            throw ValidationException::withMessages(['fiscal_kassa_id' => "Avval do'konning STIRini kiriting: kassa raqami STIR bo'yicha tekshiriladi."]);
        }
        try {
            $belongs = $routing->belongs((string) $kassaId, $store);
        } catch (FiscalGatewayException $e) {
            throw ValidationException::withMessages(['fiscal_kassa_id' => "Fiskal provayder kassani tekshira olmadi: {$e->getMessage()}"]);
        }
        if (!$belongs) {
            throw ValidationException::withMessages(['fiscal_kassa_id' => "Bu kassa do'kon STIRiga tegishli emas (provayderda boshqa soliq to'lovchiga ro'yxatdan o'tgan yoki topilmadi)."]);
        }
    }

    /**
     * A till's fiscal view; with $store, what the smart kassa driver says about it.
     *
     * @return array<string, mixed>
     */
    private static function device(Device $device, ?Store $store): array
    {
        $status = null;
        if ($store !== null && (bool) $device->getAttribute('smart_kassa')) {
            try {
                $gateway = app(FiscalGatewayManager::class)->driver(FiscalSettings::of($store)->smartKassaDriver());
                $status = $gateway instanceof SmartKassaGateway ? $gateway->kassaStatus($device, $store)->toArray() : null;
            } catch (FiscalGatewayException $e) {
                $status = ['registered' => false, 'kassa_id' => $device->getAttribute('fiscal_kassa_id'), 'message' => $e->getMessage()];
            }
        }

        return [
            'uuid' => (string) $device->uuid,
            'name' => $device->name,
            'branch_id' => $device->branch_id !== null ? (int) $device->branch_id : null,
            'smart_kassa' => (bool) $device->getAttribute('smart_kassa'),
            'fiscal_kassa_id' => $device->getAttribute('fiscal_kassa_id'),
            'kassa_status' => $status,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ];
    }
}
