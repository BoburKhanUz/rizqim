<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\PromoCode;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Marketing\MarketingException;
use App\Services\Admin\Marketing\PromoCodes;
use App\Services\Promo\PromoCodeService;
use App\Support\RowId;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-31: Marketing -> Promo-kodlar (docs/API.md "Marketing"). Read: admin.marketing.view; change:
 * admin.marketing.manage and a `reason`; deleting also a step-up (admin.recent). The rules are the order flow's own
 * (PromoCodes); once an order carries a code, what it gives can no longer change.
 */
#[Group('Admin')]
class AdminMarketingPromoCodeController extends AdminMarketingController
{
    private const ROW = 'array{id: int, code: string, type: string, type_label: string, value: string, min_order_amount: string, max_discount_amount: string|null, usage_limit: int|null, per_user_limit: int|null, store: array{id: int, name: string}|null, is_active: bool, expires_at: string|null, status: string, status_label: string, stats: array{uses: int, unique_customers: int, discount_total: string, gmv: string}, locked: bool, created_at: string|null, updated_at: string|null}';

    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly PromoCodes $codes,
        private readonly PromoCodeService $normalizer,
    ) {}

    /**
     * Promo codes
     *
     * Pages (`page`, `per_page` 1-100, default 30). Filters: `q` (part of the code), `status` (active, inactive,
     * expired, exhausted), `store_id` (codes tied to that store). `sort`: newest (default), code, expiry. Money as
     * decimal strings; `value` is a percent for `percentage` codes and so'm for `fixed` ones.
     *
     * `stats` counts the orders that use the code and are still standing (not cancelled, rejected or refunded, the
     * same orders the limits count): `uses`, `unique_customers`, `discount_total` (so'm given away) and `gmv` (the
     * orders' totals after the discount). `locked`: some order carries the code, so its value can no longer change.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>, current_page: int, last_page: int, per_page: int, total: int, statuses: array<string, string>, types: array<string, string>}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:50',
            'status' => ['nullable', Rule::in(array_keys(PromoCodes::STATUSES))],
            'store_id' => UzValidation::rowId(),
            'sort' => ['nullable', Rule::in(['newest', 'code', 'expiry'])],
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $page = $this->codes->paginate($filters, $request->integer('per_page', 30) ?: 30)->withQueryString();

        return $this->success([
            'data' => $this->codes->rows($page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'statuses' => PromoCodes::STATUSES,
            'types' => PromoCodes::TYPES,
        ]);
    }

    /**
     * Promo code card
     *
     * The row and `recent_orders`: the last 10 orders that carry the code (uuid for the order card, status, discount
     * and total as decimal strings).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(404, 'No such code (not_found)', type: self::ERROR)]
    public function show(int $id): JsonResponse
    {
        $promo = PromoCode::query()->find($id);
        if ($promo === null) {
            return $this->refuse('Promo-kod topilmadi.', 'not_found', 404);
        }

        return $this->success($this->codes->card($promo));
    }

    /**
     * New promo code
     *
     * `code` (3-50 letters, digits, `-` or `_`; stored upper case, one per code), `type` (`percentage` or `fixed`),
     * `value` (percentage: above 0, up to 100, two decimals; fixed: whole so'm above 0), `min_order_amount` (so'm,
     * default 0), `max_discount_amount` (a cap in so'm, only for `percentage`; ignored for `fixed`), `usage_limit`
     * (total uses, empty = unlimited), `per_user_limit` (uses per customer, empty = unlimited), `store_id` (empty = every
     * store), `is_active` (default true), `expires_at` (ISO 8601, in the future, empty = never), `reason`. Answers the
     * row (201). 422 `invalid_value` when the value does not fit the type.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(422, 'A rule refused it (invalid_value) or a field is wrong', type: self::ERROR)]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(null, true), UzValidation::messages(), $this->attributeNames());

        try {
            $promo = $this->codes->create($this->normalize($data));
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }

        AdminAudit::for($request)->subject($promo)->changes([], $this->codes->snapshot($promo));

        return $this->success($this->codes->card($promo), 'Promo-kod yaratildi.', 201);
    }

    /**
     * Change a promo code
     *
     * Any field of the new code, and `reason` (required). Once an order carries the code, `code`, `type`, `value`,
     * `max_discount_amount` and `store_id` cannot change: 422 `promo_code_used` with `data.fields` (create a new code
     * and switch the old one off). The limits, `min_order_amount`, `expires_at` and `is_active` stay editable.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(404, 'No such code (not_found)', type: self::ERROR)]
    #[ApiDocResponse(422, 'The code was used (promo_code_used) or a field is wrong', type: self::ERROR)]
    public function update(Request $request, int $id): JsonResponse
    {
        $promo = PromoCode::query()->find($id);
        $data = $request->validate($this->rules($promo, false), UzValidation::messages(), $this->attributeNames());
        if ($promo === null) {
            return $this->refuse('Promo-kod topilmadi.', 'not_found', 404);
        }

        $before = $this->codes->snapshot($promo);
        try {
            $promo = $this->codes->update($promo, $this->normalize($data));
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }

        $after = $this->codes->snapshot($promo);
        AdminAudit::for($request)->subject($promo)->changes($before, $after);
        if ($before === $after) {
            AdminAudit::for($request)->skip();
        }

        return $this->success($this->codes->card($promo), 'Promo-kod saqlandi.');
    }

    /**
     * Switch a code on
     *
     * `reason` required. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function activate(Request $request, int $id): JsonResponse
    {
        return $this->toggle($request, $id, true);
    }

    /**
     * Switch a code off
     *
     * The code stops working at once (the order flow checks it on every order); orders already placed keep it.
     * `reason` required. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function deactivate(Request $request, int $id): JsonResponse
    {
        return $this->toggle($request, $id, false);
    }

    /**
     * Delete a promo code
     *
     * Only a code no order ever carried; a used one is switched off instead (409 `promo_code_used`). Needs a step-up and
     * a `reason`. Answers `{id}`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int}}')]
    #[ApiDocResponse(409, 'The code was used (promo_code_used)', type: self::ERROR)]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $promo = PromoCode::query()->find($id);
        if ($promo === null) {
            return $this->refuse('Promo-kod topilmadi.', 'not_found', 404);
        }

        $before = $this->codes->snapshot($promo);
        try {
            $this->codes->delete($promo);
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }
        AdminAudit::for($request)->subject($promo)->changes($before, []);

        return $this->success(['id' => $id], "Promo-kod o'chirildi.");
    }

    private function toggle(Request $request, int $id, bool $active): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $promo = PromoCode::query()->find($id);
        if ($promo === null) {
            return $this->refuse('Promo-kod topilmadi.', 'not_found', 404);
        }

        $before = $this->codes->snapshot($promo);
        $promo = $this->codes->update($promo, ['is_active' => $active]);
        AdminAudit::for($request)->subject($promo)->changes($before, $this->codes->snapshot($promo));
        if ($before === $this->codes->snapshot($promo)) {
            AdminAudit::for($request)->skip();
        }

        return $this->success($this->codes->card($promo), $active ? 'Promo-kod yoqildi.' : "Promo-kod o'chirib qo'yildi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?PromoCode $existing, bool $creating): array
    {
        $present = $creating ? 'required' : 'sometimes';
        $normalizer = $this->normalizer;

        return [
            'code' => [$present, 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', function (string $attribute, mixed $value, \Closure $fail) use ($normalizer, $existing) {
                $taken = PromoCode::query()
                    ->where('code', $normalizer->normalize((string) $value))
                    ->when($existing !== null, fn ($query) => $query->where('id', '!=', $existing->id))
                    ->exists();
                if ($taken) {
                    $fail('Bunday promo-kod allaqachon bor.');
                }
            }],
            'type' => [$present, Rule::in(array_keys(PromoCodes::TYPES))],
            'value' => [$present, 'numeric', 'min:0.01', 'max:100000000', 'decimal:0,2'],
            'min_order_amount' => 'sometimes|nullable|numeric|min:0|max:999999999|decimal:0,2',
            'max_discount_amount' => 'sometimes|nullable|numeric|min:0.01|max:999999999|decimal:0,2',
            'usage_limit' => 'sometimes|nullable|integer|min:1|max:10000000',
            'per_user_limit' => 'sometimes|nullable|integer|min:1|max:1000',
            'store_id' => ['sometimes', ...$this->storeRules()],
            'is_active' => 'sometimes|boolean',
            'expires_at' => array_filter(['sometimes', 'nullable', 'date', $creating ? 'after:now' : null]),
            'reason' => AdminRules::reason(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'code' => 'Promo-kod', 'type' => 'Turi', 'value' => 'Chegirma qiymati', 'min_order_amount' => 'Minimal buyurtma summasi',
            'max_discount_amount' => 'Maksimal chegirma', 'usage_limit' => 'Umumiy limit', 'per_user_limit' => 'Mijoz limiti',
            'store_id' => 'Ovqatxona', 'expires_at' => 'Amal qilish muddati', 'reason' => 'Sabab',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        unset($data['reason']);
        if (array_key_exists('expires_at', $data)) {
            $data['expires_at'] = $this->utc($data['expires_at']);
        }
        if (array_key_exists('store_id', $data) && $data['store_id'] !== null) {
            $data['store_id'] = RowId::parse($data['store_id']);
        }

        return $data;
    }
}
