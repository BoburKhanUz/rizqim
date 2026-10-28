<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminPermission;
use App\Http\Requests\Admin\AdminRules;
use App\Models\Store;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\Stores\StoreActions;
use App\Services\Admin\Stores\StoreAdminException;
use App\Services\Admin\Stores\StorePresenter;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-22: actions on a store from its 360° card: admin.stores.manage, a step-up (admin.recent) and a `reason` each;
 * every one is audited with the fields before and after (the route name is the action). Status changes also land in
 * the store's status history. Commission override is P4-21's endpoint (admin.subscriptions.manage). There is no
 * "sign in as the owner".
 */
#[Group('Admin')]
class AdminStoreActionController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly StoreActions $actions,
        private readonly StorePresenter $presenter,
    ) {}

    /**
     * Suspend a store
     *
     * Status `suspended` and hidden from the marketplace (no new Mini App orders, gone from the catalog). The till keeps
     * taking payments and printing receipts. `reason` is required. Answers the card. 409 `already_suspended`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(409, 'Already suspended (already_suspended)', type: self::ERROR)]
    public function suspend(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        return $this->act($request, $id, fn (Store $store) => $this->actions->suspend($store, $this->member($request), $request->string('reason')->toString()), "Ovqatxona to'xtatildi.");
    }

    /**
     * Activate a store
     *
     * Back to `active` and visible, from suspended, pending, under review or rejected. 422 `finance_status` for
     * payment_required / finance_hold (the debt rules decide), 422 `use_pipeline` while its application is still in the
     * pipeline (move it to `faol`), 409 `already_active`. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Not from this status (finance_status, use_pipeline)', type: self::ERROR)]
    public function activate(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        return $this->act($request, $id, fn (Store $store) => $this->actions->activate($store, $this->member($request), $request->string('reason')->toString()), 'Ovqatxona faollashtirildi.');
    }

    /**
     * Change the credit limit
     *
     * `credit_limit`: the debt the store may have, 0–100 000 000 so'm (decimal string or number). Over the new limit an
     * active store becomes payment_required (no new Mini App orders); back within it a payment_required store becomes
     * active — the rule the ledger applies when the debt moves. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function creditLimit(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'credit_limit' => 'required|numeric|min:0|max:100000000|decimal:0,2',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        return $this->act($request, $id, fn (Store $store) => $this->actions->setCreditLimit($store, (string) $data['credit_limit'], $this->member($request), $data['reason']), 'Kredit limiti saqlandi.');
    }

    /**
     * Change the display flags
     *
     * `is_premium`, `is_featured` (tavsiya etilgan) — either or both — and `reason`. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function display(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_premium' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $flags = array_map('boolval', array_intersect_key($data, array_flip(['is_premium', 'is_featured'])));

        return $this->act($request, $id, fn (Store $store) => $this->actions->setDisplay($store, $flags), "Ko'rsatish sozlamalari saqlandi.");
    }

    /**
     * Change the store's payment account
     *
     * P4-32 (the old superadmin store form's money settings). `payment_account_type`: `platform_account` (Click and
     * Payme pay the platform, which settles the store's wallet) or `own_account` (the store's own merchant cashbox; the
     * owner enters its keys in the partner panel, until then the platform collects); `min_payout_reserve`: what must
     * stay in the wallet after a payout, 0–100 000 000 so'm. Either or both, and `reason`. 409 `payments_in_flight`
     * while an online order of the store waits for its payment (its callbacks are verified with the account it was
     * placed with). Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(409, 'An online payment is still on its way (payments_in_flight)', type: self::ERROR)]
    public function paymentAccount(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'payment_account_type' => ['required_without:min_payout_reserve', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\PaymentAccountType::class)],
            'min_payout_reserve' => 'required_without:payment_account_type|numeric|min:0|max:100000000|decimal:0,2',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $change = array_map('strval', array_intersect_key($data, array_flip(['payment_account_type', 'min_payout_reserve'])));

        return $this->act($request, $id, fn (Store $store) => $this->actions->setPaymentAccount($store, $change), "To'lov hisobi sozlamalari saqlandi.");
    }

    /**
     * Set the owner's login phone
     *
     * P4-32 (the old superadmin panel's "SMS bilan kirishni yoqish"): for an owner who lost the number they sign in
     * with. `phone` (+998XXXXXXXXX) becomes the owner's login phone, vouched for by the platform: its holder signs in to
     * the partner panel and the apps by SMS code, and every app session of the old number ends (audited in the store's
     * log too, the phone masked). Needs `stores.impersonate` (superadmin by default): the number takes over the store.
     * `reason` is required. 409 `owner_phone_taken` (another staff account has it), 422 `owner_phone_invalid`,
     * `no_owner`. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(409, 'Another staff account has the phone (owner_phone_taken)', type: self::ERROR)]
    #[ApiDocResponse(422, 'Not a phone (owner_phone_invalid) or no owner (no_owner)', type: self::ERROR)]
    public function ownerLoginPhone(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());
        $store = Store::query()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        try {
            $change = $this->actions->setOwnerLoginPhone($store, $data['phone']);
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        // The phones are masked in the log (AuditRedactor)
        $audit = AdminAudit::for($request)->subject($change['owner'])->store((int) $store->id)->changes($change['before'], $change['after'])->meta(['owner_id' => (int) $change['owner']->id]);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->presenter->card($store->refresh(), $this->member($request)), "Egasining kirish raqami saqlandi. U endi shu raqamga keladigan SMS kod bilan kiradi.");
    }

    /**
     * New Telegram link for the owner
     *
     * A new one-time bot link binding a Telegram account to the owner's account; the old link stops working. It is a
     * credential, so it goes to the owner's own phone by SMS (`delivered_via: "sms"`, `invite_link: null`). When no SMS
     * can be sent, the link comes back in `invite_link` only to a member with `stores.impersonate` (superadmin by
     * default); anyone else gets 422 `link_not_deliverable` and the old link stays. An owner already on Telegram is
     * never relinked: 409 `owner_already_linked`. `reason` is required. 422 `no_owner`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{invite_link: string|null, delivered_via: string|null, owner_linked: bool}}')]
    #[ApiDocResponse(409, 'The owner is already on Telegram (owner_already_linked)', type: self::ERROR)]
    #[ApiDocResponse(422, 'No owner (no_owner) or the link cannot be delivered (link_not_deliverable)', type: self::ERROR)]
    public function telegramLink(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $store = Store::query()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        $member = $this->member($request);
        try {
            $result = $this->actions->regenerateTelegramLink($store, app(AdminPermissionService::class)->has($member, AdminPermission::STORES_IMPERSONATE));
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        // The link itself never reaches the audit log: it is a credential
        AdminAudit::for($request)->subject($store)->store((int) $store->id)->meta([
            'owner_id' => (int) $result['owner']->id,
            'delivered_via' => $result['delivered_via'],
            'link_shown_to_member' => $result['invite_link'] !== null,
        ]);

        return $this->success(
            ['invite_link' => $result['invite_link'], 'delivered_via' => $result['delivered_via'], 'owner_linked' => false],
            $result['delivered_via'] === 'sms' ? "Yangi Telegram havola egasining telefoniga SMS bilan yuborildi." : 'Yangi Telegram havola tayyor. Uni egasiga yuboring.',
        );
    }

    /**
     * Message the owner
     *
     * `text` (1–1000 characters, plain text) to the owner's Telegram through the RIZQIM bot, queued; `reason`. 422
     * `no_recipient` when the owner has not joined the bot, 503 `telegram_not_configured`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{recipients: int}}')]
    #[ApiDocResponse(422, 'The owner has no Telegram (no_recipient)', type: self::ERROR)]
    public function message(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'text' => 'required|string|min:1|max:1000',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());
        $store = Store::query()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        try {
            $sent = $this->actions->messageOwner($store, $data['text']);
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($store)->store((int) $store->id)->meta(['text' => mb_substr(trim($data['text']), 0, 500), 'recipients' => $sent['recipients']]);

        return $this->success($sent, 'Xabar ovqatxona egasiga yuborildi.');
    }

    /**
     * @param  callable(Store): array{before: array<string, mixed>, after: array<string, mixed>}  $action
     */
    private function act(Request $request, int $id, callable $action, string $message): JsonResponse
    {
        $store = Store::query()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        try {
            $change = $action($store);
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $audit = AdminAudit::for($request)->subject($store)->store((int) $store->id)->changes($change['before'], $change['after']);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->presenter->card($store->refresh(), $this->member($request)), $message);
    }
}
