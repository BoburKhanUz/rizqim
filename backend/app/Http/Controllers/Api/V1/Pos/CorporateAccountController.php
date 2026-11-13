<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Accounts\CorporateAccounts;
use App\Domain\Pos\Booking\UzPhone;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountMember;
use App\Models\Store;
use App\Services\Admin\Export\XlsxExporter;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Support\Money;
use App\Support\PiiMask;
use App\Support\StoreTimezone;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P6-44 (D4): corporate accounts — a company's members (talons) with their limits, the till's member lookup and the
 * monthly statement (docs/API.md "Korxona hisobi va talon"). The account itself (kind, company details, monthly
 * limit, charge approval, talon mode) is edited with CustomerAccountController; the charge at the till is the bill's
 * `account` tender with `member_code` or `member_phone` (PaymentService → CustomerAccountService::charge).
 *
 * Members: customer_accounts.manage (setup: the corporate_accounts module). Lookup: customer_accounts.manage or
 * pos.take_payment. Statement and its Excel file: reports.view. Another store's account or member: 404; a
 * branch's account only in that branch, as for nasiya.
 *
 * Review S1/S2: the statement and its Excel file show talon codes (CorporateAccounts::maskCode), the INN and the contact
 * phone masked unless the caller may manage the account (customer_accounts.manage); a branch-bound reader of a
 * store-wide account gets the statement of their branch's entries only (`branch_id`). Review S5: a lookup by phone gives
 * the talon code masked.
 */
#[Group('POS: corporate accounts', "Korxona hisobi: xodimlar (talon), kunlik limit va abonement, kassada qidirish, oylik hisobot va Excel (P6-44).")]
class CorporateAccountController extends MenuStaffController
{
    public const MEMBER = 'array{uuid: string, name: string, phone: string|null, code: string, daily_limit: string|null, meals_per_day: int|null, is_active: bool, note: string|null, created_at: string|null, updated_at: string|null}';

    public const STATEMENT = 'array{account: array{uuid: string, name: string, company_name: string|null, inn: string|null, contact_name: string|null, contact_phone: string|null, invoice_email: string|null, talon_mode: \App\Domain\Pos\Accounts\TalonMode}, month: string, from: string, to: string, days: list<string>, rows: list<array{member: array{uuid: string, name: string, code: string, is_active: bool}|null, cells: list<array{date: string, amount: string, meals: int}>, amount: string, meals: int}>, day_totals: list<array{date: string, amount: string, meals: int}>, total: string, meals: int, repaid_total: string, monthly_limit: string|null, balance: string, branch_id: int|null, masked: bool}';

    private const MAX_BULK = 500;

    public function __construct(
        PermissionService $permissions,
        protected AuditLogger $audit,
    ) {
        parent::__construct($permissions);
    }

    /**
     * Members of a corporate account
     *
     * Everyone of the company (active first, then by name), with their talon code and limits. `q`: name, code or phone
     * digits; `active`: only active (1) or inactive (0) ones.
     */
    #[QueryParameter('q', 'Name, talon code or phone digits.', type: 'string')]
    #[QueryParameter('active', 'Only active (true) or deactivated (false) members.', type: 'bool')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{members: list<' . self::MEMBER . '>, active_count: int}}')]
    public function members(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ], UzValidation::messages());
        $account = $this->corporate($request, $uuid, Permission::CUSTOMER_ACCOUNTS_MANAGE);

        $query = CustomerAccountMember::where('customer_account_id', $account->id);
        if ($request->filled('q')) {
            $q = trim($request->string('q')->toString());
            $digits = UzPhone::searchDigits($q);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)) . '%';
            $query->where(function (Builder $query) use ($like, $q, $digits) {
                $query->whereRaw('lower(name) like ?', [$like])->orWhere('code', strtoupper($q));
                if ($digits !== null) {
                    $query->orWhere('phone', 'like', "%{$digits}%");
                }
            });
        }
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return $this->success([
            'members' => $query->orderByDesc('is_active')->orderBy('name')->orderBy('id')->limit(2000)->get()
                ->map(fn (CustomerAccountMember $member) => self::member($member))->values()->all(),
            'active_count' => CustomerAccountMember::where('customer_account_id', $account->id)->where('is_active', true)->count(),
        ]);
    }

    /**
     * Add a member
     *
     * `name` required; `phone` (+998…, unique among the store's members), `code` (the talon: 4–32 letters, digits
     * or "-"; default: ten random digits; unique in the store), `daily_limit` (sum per business day; null: none),
     * `meals_per_day` (subscription; null: none), `note`. Needs customer_accounts.manage.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::MEMBER . '}')]
    public function storeMember(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:32',
            'code' => 'nullable|string|max:32',
            'daily_limit' => 'nullable|numeric|min:0|max:999999999|decimal:0,2',
            'meals_per_day' => 'nullable|integer|min:1|max:20',
            'note' => 'nullable|string|max:500',
        ], UzValidation::messages());
        $account = $this->corporate($request, $uuid, Permission::CUSTOMER_ACCOUNTS_MANAGE);
        $data = $this->memberData($validated, $account, null);

        $member = DB::transaction(function () use ($request, $account, $data) {
            $member = new CustomerAccountMember($data + ['store_id' => $account->store_id, 'customer_account_id' => $account->id]);
            $member->code ??= CorporateAccounts::newCode((int) $account->store_id);
            $member->save();
            $this->audit->record('customer_account.member_created', $account, [], self::auditFields($member), ['member_uuid' => $member->uuid], ['actor' => $request->user()]);

            return $member;
        });

        return $this->success(self::member($member->refresh()), "Xodim qo'shildi.", 201);
    }

    /**
     * Change a member
     *
     * Only the fields sent change; `is_active: false` stops their talon (the till refuses it: `member_inactive`);
     * `regenerate_code: true` gives them a new talon code (the old chit stops working). Needs customer_accounts.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::MEMBER . '}')]
    public function updateMember(Request $request, string $uuid, string $member): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'phone' => 'nullable|string|max:32',
            'code' => 'nullable|string|max:32',
            'daily_limit' => 'nullable|numeric|min:0|max:999999999|decimal:0,2',
            'meals_per_day' => 'nullable|integer|min:1|max:20',
            'is_active' => 'sometimes|boolean',
            'note' => 'nullable|string|max:500',
            'regenerate_code' => 'sometimes|boolean',
        ], UzValidation::messages());
        $account = $this->corporate($request, $uuid, Permission::CUSTOMER_ACCOUNTS_MANAGE);
        $row = CustomerAccountMember::where('customer_account_id', $account->id)->where('uuid', $member)->first();
        if ($row === null) {
            throw new HttpResponseException($this->error('Xodim topilmadi.', ['reason' => 'member_not_found'], 404));
        }
        $data = $this->memberData($validated, $account, $row);
        $regenerate = $request->boolean('regenerate_code');

        DB::transaction(function () use ($request, $account, $row, $data, $regenerate) {
            $before = self::auditFields($row);
            $row->fill($data);
            if ($regenerate) {
                $row->code = CorporateAccounts::newCode((int) $account->store_id);
            }
            $row->save();
            $this->audit->record('customer_account.member_updated', $account, $before, self::auditFields($row), ['member_uuid' => $row->uuid], ['actor' => $request->user()]);
        });

        return $this->success(self::member($row->refresh()), "Xodim o'zgartirildi.");
    }

    /**
     * Add members from pasted lines
     *
     * `lines`: one member per line, "name;phone" (the phone may be empty; a third column, when given, is the talon code).
     * `daily_limit` and `meals_per_day` apply to each. Up to 500 lines; blank lines are skipped. All or nothing: any bad line
     * (no name, a wrong phone, a phone or code already in the store or twice in the paste) answers 422 `bulk_invalid`
     * with `lines: [{line, reason, message}]` and adds no one. Needs customer_accounts.manage.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{created: int, members: list<' . self::MEMBER . '>}}')]
    public function bulk(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'lines' => 'required|string|max:100000',
            'daily_limit' => 'nullable|numeric|min:0|max:999999999|decimal:0,2',
            'meals_per_day' => 'nullable|integer|min:1|max:20',
        ], UzValidation::messages());
        $account = $this->corporate($request, $uuid, Permission::CUSTOMER_ACCOUNTS_MANAGE);
        $storeId = (int) $account->store_id;

        $rows = [];
        $errors = [];
        $phones = [];
        $codes = [];
        $lines = preg_split('/\r\n|\r|\n/', (string) $data['lines']) ?: [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $number = $index + 1;
            $parts = array_map('trim', preg_split('/[;\t]/', $line) ?: []);
            $name = mb_substr($parts[0] ?? '', 0, 120);
            $rawPhone = $parts[1] ?? '';
            $code = CorporateAccounts::normalizeCode($parts[2] ?? null);
            $phone = $rawPhone !== '' ? UzPhone::normalize($rawPhone) : null;

            $error = match (true) {
                $name === '' => ['name_required', 'Ism yozilmagan.'],
                $rawPhone !== '' && $phone === null => ['phone_invalid', "Telefon raqami noto'g'ri."],
                $phone !== null && isset($phones[$phone]) => ['phone_duplicate', "Bu telefon ro'yxatda ikki marta."],
                $code !== null && preg_match(CorporateAccounts::CODE_PATTERN, $code) !== 1 => ['code_invalid', "Talon kodi 4–32 ta harf, raqam yoki \"-\"."],
                $code !== null && isset($codes[$code]) => ['code_duplicate', "Bu talon kodi ro'yxatda ikki marta."],
                default => null,
            };
            if ($error !== null) {
                $errors[] = ['line' => $number, 'reason' => $error[0], 'message' => $error[1]];

                continue;
            }
            if ($phone !== null) {
                $phones[$phone] = $number;
            }
            if ($code !== null) {
                $codes[$code] = $number;
            }
            $rows[] = ['line' => $number, 'name' => $name, 'phone' => $phone, 'code' => $code];
        }
        if (count($rows) + count($errors) > self::MAX_BULK) {
            throw ValidationException::withMessages(['lines' => "Bir martada ko'pi bilan " . self::MAX_BULK . ' ta xodim.']);
        }
        if ($rows === [] && $errors === []) {
            throw ValidationException::withMessages(['lines' => "Ro'yxat bo'sh."]);
        }

        // Already in the store
        $takenPhones = $phones !== [] ? CustomerAccountMember::where('store_id', $storeId)->whereIn('phone', array_keys($phones))->pluck('phone')->all() : [];
        $takenCodes = $codes !== [] ? CustomerAccountMember::where('store_id', $storeId)->whereIn('code', array_map('strval', array_keys($codes)))->pluck('code')->all() : [];
        foreach ($takenPhones as $phone) {
            $errors[] = ['line' => $phones[$phone], 'reason' => 'phone_taken', 'message' => 'Bu telefon bilan xodim allaqachon bor.'];
        }
        foreach ($takenCodes as $code) {
            $errors[] = ['line' => $codes[$code], 'reason' => 'code_taken', 'message' => 'Bu talon kodi band.'];
        }
        if ($errors !== []) {
            usort($errors, fn (array $a, array $b) => $a['line'] <=> $b['line']);
            throw new HttpResponseException($this->error("Ro'yxatda xato qatorlar bor: hech kim qo'shilmadi.", ['reason' => 'bulk_invalid', 'lines' => $errors], 422));
        }

        $limit = isset($data['daily_limit']) ? Money::of((string) $data['daily_limit'])->toString() : null;
        $meals = isset($data['meals_per_day']) ? (int) $data['meals_per_day'] : null;
        $members = DB::transaction(function () use ($request, $account, $rows, $limit, $meals, $storeId) {
            $created = [];
            foreach ($rows as $row) {
                $created[] = CustomerAccountMember::create([
                    'store_id' => $storeId,
                    'customer_account_id' => $account->id,
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'code' => $row['code'] ?? CorporateAccounts::newCode($storeId),
                    'daily_limit' => $limit,
                    'meals_per_day' => $meals,
                ]);
            }
            $this->audit->record('customer_account.members_imported', $account, [], [], ['count' => count($created), 'daily_limit' => $limit, 'meals_per_day' => $meals], ['actor' => $request->user()]);

            return $created;
        });

        return $this->success([
            'created' => count($members),
            'members' => array_map(fn (CustomerAccountMember $member) => self::member($member->refresh()), $members),
        ], count($members) . " ta xodim qo'shildi.", 201);
    }

    /**
     * Find a member at the till
     *
     * By the talon's `code` (scanned or typed) or the member's `phone`: the member, their company's account and what
     * is left today (the branch's business day) and this month, for the cashier to see before charging. A member of
     * another store, or of an account of another branch: 404 `member_not_found`. Found by `phone`, the talon `code` comes
     * masked (the charge by phone needs no code). Not gated by the corporate_accounts module: an existing account is
     * still charged at the till when the module is off. Needs customer_accounts.manage or pos.take_payment.
     */
    #[QueryParameter('code', 'The talon code (QR or badge).', type: 'string')]
    #[QueryParameter('phone', "The member's phone, when there is no talon.", type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{account: array{uuid: string, name: string, company_name: string|null, talon_mode: \App\Domain\Pos\Accounts\TalonMode, charge_approval: bool, is_blocked: bool, credit_limit: string|null, balance: string}, member: array{uuid: string, name: string, phone: string|null, code: string, daily_limit: string|null, meals_per_day: int|null, is_active: bool}, usage: array{business_date: string, member: array{amount: string, meals: int, daily_limit: string|null, daily_available: string|null, meals_per_day: int|null, meals_left: int|null}|null, month: array{amount: string, monthly_limit: string|null, available: string|null}, credit_available: string|null}}}')]
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required_without:phone|nullable|string|max:32',
            'phone' => 'required_without:code|nullable|string|max:32',
        ], UzValidation::messages());

        $corporate = app(CorporateAccounts::class);
        $member = $corporate->findInStore($this->storeId($request), $request->input('code'), $request->input('phone'));
        $account = $member !== null ? $this->visible($request)->whereKey($member->customer_account_id)->first() : null;
        if ($member === null || $account === null || !$account->isCorporate()) {
            throw new HttpResponseException($this->error('Xodim topilmadi: talon kodi yoki telefonni tekshiring.', ['reason' => 'member_not_found'], 404));
        }
        $branchId = $this->permissions->actingBranchId($request->user());
        $date = $corporate->businessDateAt($branchId !== null && $branchId !== PermissionService::NO_BRANCH ? $branchId : null, $account);

        return $this->success([
            'account' => [
                'uuid' => (string) $account->uuid,
                'name' => (string) $account->name,
                'company_name' => $account->company_name,
                'talon_mode' => $account->talon_mode->value,
                'charge_approval' => (bool) $account->charge_approval,
                'is_blocked' => (bool) $account->is_blocked,
                'credit_limit' => $account->credit_limit,
                'balance' => $account->balance,
            ],
            'member' => [
                'uuid' => (string) $member->uuid,
                'name' => (string) $member->name,
                // The till shows the phone masked (PII)
                'phone' => PiiMask::phone($member->phone),
                // Review S5: the talon itself only to whoever showed it
                'code' => CorporateAccounts::normalizeCode($request->input('code')) !== null ? (string) $member->code : CorporateAccounts::maskCode((string) $member->code),
                'daily_limit' => $member->daily_limit,
                'meals_per_day' => $member->meals_per_day !== null ? (int) $member->meals_per_day : null,
                'is_active' => (bool) $member->is_active,
            ],
            'usage' => $corporate->usage($account, $member, $date),
        ]);
    }

    /**
     * Monthly statement of a corporate account
     *
     * `month` (YYYY-MM; default: this month in the store's time zone): what each member ate on each business day
     * (net of refunds; `meals` = bills), charges without a member in a row of their own (`member` null), the totals by
     * day and in all, the repayments received in the month and the debt now (below zero: the company's overpayment,
     * "Ortiqcha to'lov"). Talon codes, the INN and the contact phone are masked (`masked: true`) unless the caller holds
     * customer_accounts.manage for the account. A branch-bound reader of a store-wide account sees only their branch's
     * charges and repayments (`branch_id`: that branch; null: all). Needs reports.view.
     */
    #[QueryParameter('month', 'YYYY-MM; default: the current month.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STATEMENT . '}')]
    public function statement(Request $request, string $uuid): JsonResponse
    {
        [$account, $month, $branchId, $full] = $this->statementOf($request, $uuid);

        return $this->success(['account' => self::accountHead($account, $full)] + self::masked(app(CorporateAccounts::class)->statement($account, $month, $branchId), $full));
    }

    /**
     * Monthly statement as Excel
     *
     * The statement of GET .../corporate-statement as an .xlsx file: sheet "Hisobot" (a member per row, a business day
     * per column, the totals), sheet "Hisob-faktura" (the company, the month, the totals, the debt) — openspout, texts
     * as text; masked and scoped as the statement. Audited (customer_account.statement_exported). Needs reports.view.
     */
    #[QueryParameter('month', 'YYYY-MM; default: the current month.', type: 'string')]
    #[ApiDocResponse(200, 'An .xlsx file: sheets "Hisobot" and "Hisob-faktura"', type: 'string')]
    public function export(Request $request, string $uuid): BinaryFileResponse
    {
        [$account, $month, $branchId, $full] = $this->statementOf($request, $uuid);
        $statement = self::masked(app(CorporateAccounts::class)->statement($account, $month, $branchId), $full);

        $path = tempnam(sys_get_temp_dir(), 'rizqim-corporate-');
        if ($path === false) {
            abort(500, "Faylni tayyorlab bo'lmadi.");
        }

        try {
            self::write($path, $account, $statement, $full);
        } catch (\Throwable $e) {
            @unlink($path);

            throw $e;
        }

        $this->audit->record('customer_account.statement_exported', $account, [], [], array_filter(['month' => $month, 'rows' => count($statement['rows']), 'branch_id' => $branchId, 'masked' => !$full]), ['actor' => $request->user()]);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) \Illuminate\Support\Str::ascii((string) $account->name))), '-');

        return response()->download($path, 'korxona-' . ($slug !== '' ? $slug : 'hisob') . "-{$month}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    /**
     * @param  array<string, mixed>  $statement
     */
    private static function write(string $path, CustomerAccount $account, array $statement, bool $full): void
    {
        $bold = (new Style())->setFontBold();
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Hisobot');

        $days = $statement['days'];
        $header = ['Xodim', 'Talon kodi'];
        foreach ($days as $date) {
            $header[] = (string) (int) substr($date, 8, 2);
        }
        array_push($header, 'Jami (so\'m)', 'Ovqatlar');
        $writer->addRow(XlsxExporter::row($header, $bold));

        foreach ($statement['rows'] as $row) {
            $cells = array_column($row['cells'], null, 'date');
            $values = [$row['member']['name'] ?? 'Xodimsiz (korxona)', $row['member']['code'] ?? ''];
            foreach ($days as $date) {
                $values[] = isset($cells[$date]) ? Money::of($cells[$date]['amount'])->toFloat() : null;
            }
            array_push($values, Money::of($row['amount'])->toFloat(), (int) $row['meals']);
            $writer->addRow(XlsxExporter::row($values));
        }

        $totals = array_column($statement['day_totals'], null, 'date');
        $values = ['Jami', ''];
        foreach ($days as $date) {
            $values[] = isset($totals[$date]) ? Money::of($totals[$date]['amount'])->toFloat() : null;
        }
        array_push($values, Money::of($statement['total'])->toFloat(), (int) $statement['meals']);
        $writer->addRow(XlsxExporter::row($values, $bold));

        $writer->addNewSheetAndMakeItCurrent()->setName('Hisob-faktura');
        $writer->addRow(XlsxExporter::row(['Korxona hisobi — oylik hisob'], $bold));
        $writer->addRow(XlsxExporter::row([]));
        foreach ([
            ['Hisob', (string) $account->name],
            ['Korxona', (string) ($account->company_name ?? '')],
            ['STIR', (string) (($full ? $account->inn : CustomerAccountController::maskInn($account->inn)) ?? '')],
            ['Mas\'ul shaxs', (string) ($account->contact_name ?? '')],
            ['Telefon', (string) (($full ? PiiMask::formatPhone($account->contact_phone) : PiiMask::phone($account->contact_phone)) ?? '')],
            ['Hisob-faktura uchun e-pochta', (string) ($account->invoice_email ?? '')],
            ['Oy', (string) $statement['month']],
            ['Davr', $statement['from'] . ' — ' . $statement['to']],
            ...($statement['branch_id'] !== null ? [['Filial', (string) (\App\Models\Branch::whereKey($statement['branch_id'])->value('name') ?? '')]] : []),
        ] as $pair) {
            $writer->addRow(XlsxExporter::row($pair));
        }
        $writer->addRow(XlsxExporter::row([]));
        $writer->addRow(XlsxExporter::row(['Oy davomida yozilgan (so\'m)', Money::of($statement['total'])->toFloat()], $bold));
        $writer->addRow(XlsxExporter::row(['Ovqatlar soni', (int) $statement['meals']]));
        $writer->addRow(XlsxExporter::row(['Oy davomida to\'langan (so\'m)', Money::of($statement['repaid_total'])->toFloat()]));
        $writer->addRow(XlsxExporter::row(['Oylik limit (so\'m)', $statement['monthly_limit'] !== null ? Money::of($statement['monthly_limit'])->toFloat() : 'Cheksiz']));
        // Review C7: a refund after the invoice was paid leaves the company's credit
        $balance = Money::of($statement['balance']);
        $writer->addRow(XlsxExporter::row($balance->isNegative() ? ['Ortiqcha to\'lov (so\'m)', $balance->abs()->toFloat()] : ['Hozirgi qarz (so\'m)', $balance->toFloat()], $bold));
        $writer->close();
    }

    /**
     * The account, the month, the branch whose entries alone count (a branch-bound reader of a store-wide account;
     * null: all) and whether the caller sees codes and details in full (customer_accounts.manage for the account).
     *
     * @return array{0: CustomerAccount, 1: string, 2: int|null, 3: bool}
     */
    private function statementOf(Request $request, string $uuid): array
    {
        $request->validate(['month' => 'nullable|date_format:Y-m'], UzValidation::messages());
        $user = $request->user();
        $acting = $this->permissions->actingBranchId($user);
        $account = $this->visible($request)->where('uuid', $uuid)->first();
        if ($account === null) {
            throw new HttpResponseException($this->error('Hisob topilmadi.', [], 404));
        }
        $branchId = null;
        if ($account->branch_id === null && $acting !== null && $acting !== PermissionService::NO_BRANCH && $user->role !== \App\Enums\UserRole::SUPER_ADMIN) {
            // A store-wide account read from one branch: allowed, with that branch's entries only
            if (!$this->permissions->has($user, Permission::REPORTS_VIEW)) {
                throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
            }
            $branchId = (int) $acting;
        } else {
            $this->authorizeFor($request, Permission::REPORTS_VIEW, $account);
        }
        if (!$account->isCorporate()) {
            throw new HttpResponseException($this->error('Bu nasiya hisobi: xodimlar va oylik hisobot faqat korxona hisobida.', ['reason' => 'account_not_corporate'], 422));
        }
        $month = $request->filled('month')
            ? (string) $request->input('month')
            : Carbon::now(StoreTimezone::of(Store::find($account->store_id)))->format('Y-m');

        return [$account, $month, $branchId, $this->permissions->allows($user, Permission::CUSTOMER_ACCOUNTS_MANAGE, $account)];
    }

    /**
     * The statement with its talon codes masked unless `full` (review S1).
     *
     * @param  array<string, mixed>  $statement
     * @return array<string, mixed>
     */
    private static function masked(array $statement, bool $full): array
    {
        if (!$full) {
            foreach ($statement['rows'] as $index => $row) {
                if ($row['member'] !== null) {
                    $statement['rows'][$index]['member']['code'] = CorporateAccounts::maskCode((string) $row['member']['code']);
                }
            }
        }

        return $statement + ['masked' => !$full];
    }

    /**
     * @return array<string, mixed>
     */
    private static function accountHead(CustomerAccount $account, bool $full): array
    {
        return [
            'uuid' => (string) $account->uuid,
            'name' => (string) $account->name,
            'company_name' => $account->company_name,
            'inn' => $full ? $account->inn : CustomerAccountController::maskInn($account->inn),
            'contact_name' => $account->contact_name,
            'contact_phone' => $full ? $account->contact_phone : PiiMask::phone($account->contact_phone),
            'invoice_email' => $account->invoice_email,
            'talon_mode' => $account->talon_mode->value,
        ];
    }

    /**
     * The validated member fields, normalized; the phone and code unique in the store.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function memberData(array $data, CustomerAccount $account, ?CustomerAccountMember $member): array
    {
        unset($data['regenerate_code']);
        $storeId = (int) $account->store_id;

        if (array_key_exists('phone', $data)) {
            $raw = $data['phone'] !== null ? trim((string) $data['phone']) : '';
            $phone = $raw !== '' ? UzPhone::normalize($raw) : null;
            if ($raw !== '' && $phone === null) {
                throw ValidationException::withMessages(['phone' => "Telefon raqami noto'g'ri: +998XXXXXXXXX yoki 9 xonali raqam."]);
            }
            if ($phone !== null && CustomerAccountMember::where('store_id', $storeId)->where('phone', $phone)
                ->when($member !== null, fn ($query) => $query->whereKeyNot($member->id))->exists()) {
                throw ValidationException::withMessages(['phone' => 'Bu telefon raqami bilan xodim allaqachon bor.']);
            }
            $data['phone'] = $phone;
        }
        if (array_key_exists('code', $data)) {
            $code = CorporateAccounts::normalizeCode($data['code']);
            if ($code === null) {
                unset($data['code']); // keep (or, on create, generate) the code
            } else {
                if (preg_match(CorporateAccounts::CODE_PATTERN, $code) !== 1) {
                    throw ValidationException::withMessages(['code' => "Talon kodi 4–32 ta lotin harfi, raqam yoki \"-\" bo'lsin."]);
                }
                if (CustomerAccountMember::where('store_id', $storeId)->where('code', $code)
                    ->when($member !== null, fn ($query) => $query->whereKeyNot($member->id))->exists()) {
                    throw ValidationException::withMessages(['code' => 'Bu talon kodi band.']);
                }
                $data['code'] = $code;
            }
        }
        if (array_key_exists('daily_limit', $data)) {
            $data['daily_limit'] = $data['daily_limit'] !== null ? Money::of((string) $data['daily_limit'])->toString() : null;
        }
        foreach (['name', 'note'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return $data;
    }

    /**
     * The store's accounts the caller sees (store-wide ones and those of the branch they act in).
     *
     * @return Builder<CustomerAccount>
     */
    private function visible(Request $request): Builder
    {
        $acting = $this->permissions->actingBranchId($request->user());

        return CustomerAccount::query()
            ->where('store_id', $this->storeId($request))
            ->when($acting !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $acting)));
    }

    private function corporate(Request $request, string $uuid, Permission $permission): CustomerAccount
    {
        $account = $this->visible($request)->where('uuid', $uuid)->first();
        if ($account === null) {
            throw new HttpResponseException($this->error('Hisob topilmadi.', [], 404));
        }
        $this->authorizeFor($request, $permission, $account);
        if (!$account->isCorporate()) {
            throw new HttpResponseException($this->error('Bu nasiya hisobi: xodimlar va oylik hisobot faqat korxona hisobida.', ['reason' => 'account_not_corporate'], 422));
        }

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    private static function member(CustomerAccountMember $member): array
    {
        return [
            'uuid' => (string) $member->uuid,
            'name' => (string) $member->name,
            'phone' => $member->phone,
            'code' => (string) $member->code,
            'daily_limit' => $member->daily_limit,
            'meals_per_day' => $member->meals_per_day !== null ? (int) $member->meals_per_day : null,
            'is_active' => (bool) $member->is_active,
            'note' => $member->note,
            'created_at' => $member->created_at?->toJSON(),
            'updated_at' => $member->updated_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function auditFields(CustomerAccountMember $member): array
    {
        return [
            'name' => $member->name,
            'phone' => PiiMask::phone($member->phone),
            'code' => substr((string) $member->code, 0, 2) . '***',
            'daily_limit' => $member->daily_limit,
            'meals_per_day' => $member->meals_per_day,
            'is_active' => (bool) $member->is_active,
        ];
    }
}
