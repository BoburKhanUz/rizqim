<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Accounts\CustomerAccountEntryType;
use App\Domain\Pos\Accounts\CustomerAccountService;
use App\Domain\Pos\Booking\UzPhone;
use App\Domain\Pos\Contracts\PosTender;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountEntry;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Support\Money;
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
use Illuminate\Validation\ValidationException;

/**
 * P3-17: customer accounts (nasiya) for the till and the web panel (docs/API.md "Bron va nasiya"). Reading accounts
 * and taking a repayment: customer_accounts.manage or pos.take_payment (the cashier takes the money); creating,
 * changing, blocking and correcting them: customer_accounts.manage (owner, manager). A store-wide account is seen in
 * every branch; an account of a branch only there (and by store-wide staff). Another store's account: 404.
 */
#[Group('POS: customer accounts', "Nasiya hisoblari: limit, qarz, qarzdorlar, ko'chirma va qarz to'lovi kredit cheki bilan (P3-17).")]
class CustomerAccountController extends MenuStaffController
{
    public const ACCOUNT = 'array{uuid: string, name: string, phone: string|null, group_name: string|null, branch_id: int|null, credit_limit: string|null, balance: string, available_credit: string|null, is_blocked: bool, note: string|null, created_at: string|null, updated_at: string|null}';

    public const ENTRY = 'array{uuid: string, type: string, type_label: string, amount: string, balance_after: string, bill_uuid: string|null, tender: string|null, card_type: string|null, transaction_id: string|null, fiscal_receipt_uuid: string|null, fiscal_status: string|null, branch_id: int|null, user: array{id: int, name: string|null}|null, approved_by: array{id: int, name: string|null}|null, note: string|null, created_at: string|null}';

    public function __construct(
        PermissionService $permissions,
        protected CustomerAccountService $accounts,
        protected AuditLogger $audit,
    ) {
        parent::__construct($permissions);
    }

    /**
     * Customer accounts
     *
     * Search by `q` (name, group or phone digits); `debtors=1`: only those who owe, biggest debt first. Paginated
     * (`per_page` up to 100).
     */
    #[QueryParameter('q', 'Name, group name or phone digits.', type: 'string')]
    #[QueryParameter('debtors', 'Only accounts with a debt.', type: 'bool')]
    #[QueryParameter('per_page', 'Up to 100 (default 30).', type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{accounts: list<' . self::ACCOUNT . '>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'debtors' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ], UzValidation::messages());

        $query = $this->visible($request);
        if ($request->filled('q')) {
            $q = trim($request->string('q')->toString());
            $digits = UzPhone::searchDigits($q);
            $query->where(function (Builder $query) use ($q, $digits) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)) . '%';
                $query->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(group_name) like ?', [$like]);
                if ($digits !== null) {
                    $query->orWhere('phone', 'like', "%{$digits}%");
                }
            });
        }
        if ($request->boolean('debtors')) {
            $query->where('balance', '>', 0)->orderByDesc('balance');
        }
        $page = $query->orderBy('name')->orderBy('id')->paginate((int) $request->input('per_page', 30));

        return $this->success([
            'accounts' => collect($page->items())->map(fn (CustomerAccount $account) => $this->present($account))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Debtors
     *
     * Everyone who owes, biggest debt first (up to 500), and the total debt.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{total_debt: string, count: int, accounts: list<' . self::ACCOUNT . '>}}')]
    public function debtors(Request $request): JsonResponse
    {
        $query = $this->visible($request)->where('balance', '>', 0);
        $total = Money::of((string) (clone $query)->sum('balance'));
        $count = (clone $query)->count();

        return $this->success([
            'total_debt' => $total->toString(),
            'count' => $count,
            'accounts' => $query->orderByDesc('balance')->orderBy('id')->limit(500)->get()
                ->map(fn (CustomerAccount $account) => $this->present($account))->values()->all(),
        ]);
    }

    /**
     * Open a customer account
     *
     * `name` required; `phone` (+998…, unique in the store), `group_name`, `credit_limit` (null: no limit; 0: no credit),
     * `note`, `branch_id` (only that branch; default: store-wide, a branch-bound manager's own branch).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ACCOUNT . '}')]
    public function store(Request $request): JsonResponse
    {
        $data = $this->normalized($request, $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:32',
            'group_name' => 'nullable|string|max:120',
            'credit_limit' => 'nullable|numeric|min:0|max:999999999|decimal:0,2',
            'is_blocked' => 'sometimes|boolean',
            'note' => 'nullable|string|max:500',
            'branch_id' => UzValidation::rowId(),
        ], UzValidation::messages()), null);
        $user = $request->user();

        $branchId = null;
        if ($request->filled('branch_id')) {
            $branchId = (int) $this->branch($request, Permission::CUSTOMER_ACCOUNTS_MANAGE)->id;
        } elseif ($this->permissions->isBranchScoped($user)) {
            $branchId = (int) $user->branch_id;
        }

        $account = new CustomerAccount($data + ['store_id' => $this->storeId($request), 'branch_id' => $branchId, 'created_by' => $user->id]);
        $account->save();
        $this->audit->record('customer_account.created', $account, [], $this->auditFields($account), [], ['actor' => $user]);

        return $this->success($this->present($account->refresh()), 'Nasiya hisobi ochildi.', 201);
    }

    /**
     * A customer account, with its last 20 entries
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{account: ' . self::ACCOUNT . ', entries: list<' . self::ENTRY . '>}}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $account = $this->account($request, $uuid);
        $entries = $account->entries()->with(['user:id,name', 'approver:id,name'])->orderByDesc('id')->limit(20)->get();

        return $this->success([
            'account' => $this->present($account),
            'entries' => $entries->map(fn (CustomerAccountEntry $entry) => $this->entry($entry))->values()->all(),
        ]);
    }

    /**
     * Change a customer account
     *
     * Only the fields sent change; `is_blocked: true` stops new credit (repayments still work).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ACCOUNT . '}')]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $account = $this->account($request, $uuid, Permission::CUSTOMER_ACCOUNTS_MANAGE);
        $data = $this->normalized($request, $request->validate([
            'name' => 'sometimes|string|max:120',
            'phone' => 'nullable|string|max:32',
            'group_name' => 'nullable|string|max:120',
            'credit_limit' => 'nullable|numeric|min:0|max:999999999|decimal:0,2',
            'is_blocked' => 'sometimes|boolean',
            'note' => 'nullable|string|max:500',
            'branch_id' => UzValidation::rowId(),
        ], UzValidation::messages()), $account);

        $before = $this->auditFields($account);
        $account->fill($data)->save();
        $this->audit->record('customer_account.updated', $account, $before, $this->auditFields($account), [], ['actor' => $request->user()]);

        return $this->success($this->present($account->refresh()), "Nasiya hisobi o'zgartirildi.");
    }

    /**
     * Account statement
     *
     * The entries between `from` and `to` (dates in the store's time zone, inclusive; default: the last 30 days), with
     * the debt before and after and the totals by type.
     */
    #[QueryParameter('from', 'First day, Y-m-d.', type: 'string')]
    #[QueryParameter('to', 'Last day, Y-m-d, inclusive.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{account: ' . self::ACCOUNT . ', from: string, to: string, opening_balance: string, closing_balance: string, charges_total: string, repayments_total: string, adjustments_total: string, entries: list<' . self::ENTRY . '>}}')]
    public function statement(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ], UzValidation::messages());

        $account = $this->account($request, $uuid);
        $tz = StoreTimezone::of(Store::find($account->store_id));
        $to = $request->filled('to') ? Carbon::createFromFormat('Y-m-d', (string) $request->input('to'), $tz)->startOfDay() : Carbon::now($tz)->startOfDay();
        $from = $request->filled('from') ? Carbon::createFromFormat('Y-m-d', (string) $request->input('from'), $tz)->startOfDay() : $to->copy()->subDays(29);
        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['to' => "Oxirgi kun birinchi kundan oldin bo'lmasin."]);
        }
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => "Ko'pi bilan bir yillik oraliq."]);
        }

        $start = $from->copy()->utc();
        $end = $to->copy()->addDay()->utc();

        $opening = Money::of((string) CustomerAccountEntry::where('customer_account_id', $account->id)->where('created_at', '<', $start)->sum('amount'));
        $entries = CustomerAccountEntry::where('customer_account_id', $account->id)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->with(['user:id,name', 'approver:id,name'])
            ->orderBy('id')
            ->limit(2000)
            ->get();

        $totals = [];
        foreach (CustomerAccountEntryType::cases() as $type) {
            $totals[$type->value] = $entries->filter(fn (CustomerAccountEntry $entry) => $entry->type === $type)
                ->reduce(fn (Money $sum, CustomerAccountEntry $entry) => $sum->plus($entry->amount), Money::zero());
        }
        $closing = $opening->plus(Money::sum(...array_values($totals)));

        return $this->success([
            'account' => $this->present($account),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'opening_balance' => $opening->toString(),
            'closing_balance' => $closing->toString(),
            'charges_total' => $totals[CustomerAccountEntryType::CHARGE->value]->toString(),
            'repayments_total' => $totals[CustomerAccountEntryType::REPAYMENT->value]->abs()->toString(),
            'adjustments_total' => $totals[CustomerAccountEntryType::ADJUSTMENT->value]->toString(),
            'entries' => $entries->map(fn (CustomerAccountEntry $entry) => $this->entry($entry))->values()->all(),
        ]);
    }

    /**
     * Take a repayment
     *
     * `amount` (at most the debt), `tender` cash|card|qr (`card_type`, `transaction_id`), `note`. Issues a CREDIT
     * receipt (`fiscal_receipt_uuid`); the owner is told on Telegram. The `Idempotency-Key` header is required.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{account: ' . self::ACCOUNT . ', entry: ' . self::ENTRY . '}}')]
    public function repay(Request $request, string $uuid): JsonResponse
    {
        $key = $request->headers->get('Idempotency-Key');
        if ($key === null || trim($key) === '') {
            throw new HttpResponseException($this->error("Idempotency-Key sarlavhasi majburiy: pul bilan bog'liq so'rov qayta yuborilganda ikki marta yozilmasligi uchun.", ['reason' => 'idempotency_key_required'], 400));
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:999999999|decimal:0,2',
            'tender' => ['required', 'string', 'in:cash,card,qr'],
            'card_type' => 'nullable|string|max:32',
            'transaction_id' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ], UzValidation::messages());

        $user = $request->user();
        $account = $this->account($request, $uuid);
        $entry = $this->accounts->repay(
            $account,
            Money::of((string) $data['amount']),
            PosTender::from($data['tender']),
            $data['card_type'] ?? null,
            $data['transaction_id'] ?? null,
            $data['note'] ?? null,
            $key,
            $user,
            $this->permissions->terminalOf($user)?->id,
            $this->actingBranch($user),
        );
        $entry->load(['user:id,name', 'approver:id,name']);

        return $this->success([
            'account' => $this->present($account->refresh()),
            'entry' => $this->entry($entry),
        ], "Qarz to'lovi qabul qilindi.", 201);
    }

    /**
     * Correct the debt
     *
     * `amount` > 0 adds to the debt, < 0 takes from it (a write-off); `note` required. The debt never goes below 0.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{account: ' . self::ACCOUNT . ', entry: ' . self::ENTRY . '}}')]
    public function adjust(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:-999999999|max:999999999|decimal:0,2',
            'note' => 'required|string|max:500',
        ], UzValidation::messages());

        $user = $request->user();
        $account = $this->account($request, $uuid, Permission::CUSTOMER_ACCOUNTS_MANAGE);
        $entry = $this->accounts->adjust($account, Money::of((string) $data['amount']), $data['note'], $user, $this->permissions->terminalOf($user)?->id, $this->actingBranch($user));
        $entry->load(['user:id,name', 'approver:id,name']);

        return $this->success([
            'account' => $this->present($account->refresh()),
            'entry' => $this->entry($entry),
        ], 'Qarz tuzatildi.', 201);
    }

    /**
     * The store's accounts the caller sees: store-wide ones and those of the branch they act in.
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

    private function account(Request $request, string $uuid, ?Permission $permission = null): CustomerAccount
    {
        $account = $this->visible($request)->where('uuid', $uuid)->first();
        if ($account === null) {
            throw new HttpResponseException($this->error('Nasiya hisobi topilmadi.', [], 404));
        }
        if ($permission !== null) {
            $this->authorizeFor($request, $permission, $account);
        }

        return $account;
    }

    private function actingBranch(User $user): ?int
    {
        $acting = $this->permissions->actingBranchId($user);

        return $acting !== null && $acting !== PermissionService::NO_BRANCH ? $acting : null;
    }

    /**
     * The validated fields, normalized (phone, money), the phone checked to be unique in the store.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(Request $request, array $data, ?CustomerAccount $account): array
    {
        unset($data['branch_id']);

        if (array_key_exists('phone', $data)) {
            $phone = $data['phone'] !== null && trim((string) $data['phone']) !== '' ? UzPhone::normalize((string) $data['phone']) : null;
            if ($data['phone'] !== null && trim((string) $data['phone']) !== '' && $phone === null) {
                throw ValidationException::withMessages(['phone' => "Telefon raqami noto'g'ri: +998XXXXXXXXX yoki 9 xonali raqam."]);
            }
            if ($phone !== null && CustomerAccount::where('store_id', $this->storeId($request))->where('phone', $phone)
                ->when($account !== null, fn ($query) => $query->whereKeyNot($account->id))->exists()) {
                throw ValidationException::withMessages(['phone' => 'Bu telefon raqami bilan nasiya hisobi allaqachon bor.']);
            }
            $data['phone'] = $phone;
        }
        if (array_key_exists('credit_limit', $data)) {
            $data['credit_limit'] = $data['credit_limit'] !== null ? Money::of((string) $data['credit_limit'])->toString() : null;
        }
        if (array_key_exists('is_blocked', $data)) {
            $data['is_blocked'] = (bool) $data['is_blocked'];
        }
        foreach (['name', 'group_name', 'note'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CustomerAccount $account): array
    {
        $available = $account->credit_limit !== null
            ? Money::max(Money::zero(), Money::of($account->credit_limit)->minus($account->balance))->toString()
            : null;

        return [
            'uuid' => (string) $account->uuid,
            'name' => (string) $account->name,
            'phone' => $account->phone,
            'group_name' => $account->group_name,
            'branch_id' => $account->branch_id !== null ? (int) $account->branch_id : null,
            'credit_limit' => $account->credit_limit,
            'balance' => $account->balance,
            'available_credit' => $available,
            'is_blocked' => (bool) $account->is_blocked,
            'note' => $account->note,
            'created_at' => $account->created_at?->toJSON(),
            'updated_at' => $account->updated_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(CustomerAccountEntry $entry): array
    {
        $user = $entry->user;
        $approver = $entry->approver;

        return [
            'uuid' => (string) $entry->uuid,
            'type' => $entry->type->value,
            'type_label' => $entry->type->label(),
            'amount' => $entry->amount,
            'balance_after' => $entry->balance_after,
            'bill_uuid' => $entry->bill_uuid,
            'tender' => $entry->tender,
            'card_type' => $entry->card_type,
            'transaction_id' => $entry->transaction_id,
            'fiscal_receipt_uuid' => $entry->fiscal_receipt_uuid,
            'fiscal_status' => $entry->fiscal_status,
            'branch_id' => $entry->branch_id !== null ? (int) $entry->branch_id : null,
            'user' => $user !== null ? ['id' => (int) $user->id, 'name' => $user->name] : null,
            'approved_by' => $approver !== null ? ['id' => (int) $approver->id, 'name' => $approver->name] : null,
            'note' => $entry->note,
            'created_at' => $entry->created_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditFields(CustomerAccount $account): array
    {
        return [
            'name' => $account->name,
            'phone' => $account->phone,
            'group_name' => $account->group_name,
            'credit_limit' => $account->credit_limit,
            'is_blocked' => (bool) $account->is_blocked,
            'branch_id' => $account->branch_id,
        ];
    }
}
