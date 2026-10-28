<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\Store;
use App\Services\Admin\AdminAudit;
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
     * New Telegram link for the owner
     *
     * A new one-time bot link binding the owner's Telegram to their account; the old link stops working. Shown only in
     * this answer (`invite_link`). `owner_linked`: the owner already joined — opening the new link moves the account to
     * the Telegram that opens it. `reason` is required. 422 `no_owner`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{invite_link: string, owner_linked: bool}}')]
    #[ApiDocResponse(422, 'The store has no owner (no_owner)', type: self::ERROR)]
    public function telegramLink(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $store = Store::query()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        try {
            $result = $this->actions->regenerateTelegramLink($store);
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        // The link itself never reaches the audit log: it is a credential
        AdminAudit::for($request)->subject($store)->store((int) $store->id)->meta(['owner_id' => (int) $result['owner']->id, 'owner_linked' => $result['owner_linked']]);

        return $this->success(['invite_link' => $result['invite_link'], 'owner_linked' => $result['owner_linked']], 'Yangi Telegram havola tayyor. Uni egasiga yuboring.');
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
