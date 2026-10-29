<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ComplaintSupportStatus;
use App\Models\Complaint;
use App\Models\SupportCannedReply;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Customers\ComplaintInbox;
use App\Services\Admin\Customers\ComplaintLabels;
use App\Services\Admin\Customers\CustomerAdminException;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-31: Mijozlar va yordam → Shikoyatlar (admin.support.view reads, admin.support.manage changes): the inbox of
 * every customer complaint, its card, taking it (status, assignee), replying to the customer through the RIZQIM bot,
 * resolving it with a note. Each change is audited with the complaint as the subject.
 */
#[Group('Admin')]
class AdminComplaintController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    private const ROW = 'array{uuid: string, type: string, type_label: string, description: string|null, support_status: string, support_status_label: string, store_flow: string, store_flow_label: string, escalated: bool, store_replied: bool, waiting: bool, customer: array{id: int, name: string}|null, order: array{uuid: string, short_code: string|null}|null, store: array{id: int, name: string}|null, assignee: array{id: int, name: string}|null, created_at: string|null, answered_at: string|null, resolved_at: string|null}';

    public function __construct(
        private readonly ComplaintInbox $inbox,
    ) {}

    /**
     * Complaints
     *
     * Cursor pages (`cursor` from `next_cursor`, `per_page` 1–100, default 30). `status`: open (default: new and in
     * progress, the oldest first), new, in_progress, answered, resolved, all (newest first). `assigned`: me, none or a
     * member id; `type` (WRONG_ITEM, ORDER_NOT_RECEIVED, QUALITY_ISSUE, COURIER_ISSUE, PAYMENT_ISSUE, OTHER); `waiting=1`:
     * only the ones waiting longer than `waiting_hours` (open, not answered by the store); `q`: the order's code or a
     * word of the text. `counts` per support status plus `open` and `waiting`; `assignees`: the members a complaint
     * can be given to; `types` and `statuses`: the words.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>, next_cursor: string|null, per_page: int, counts: array<string, int>, waiting_hours: int, assignees: list<array{id: int, name: string}>, types: array<string, string>, statuses: array<string, string>}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(ComplaintInbox::STATUS_FILTERS)],
            'assigned' => ['nullable', 'string', 'regex:/^(me|none|[1-9][0-9]{0,17})$/'],
            'type' => ['nullable', Rule::in(array_keys(ComplaintLabels::TYPES))],
            'waiting' => 'nullable|boolean',
            'q' => 'nullable|string|min:2|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());
        $filters['waiting'] = $request->boolean('waiting');

        $page = $this->inbox->page($this->member($request), $filters);

        return $this->success([
            'data' => $this->inbox->rows($page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
            'counts' => $this->inbox->counts(),
            'waiting_hours' => ComplaintInbox::waitingHours(),
            'assignees' => $this->inbox->assignees(),
            'types' => ComplaintLabels::TYPES,
            'statuses' => collect(ComplaintSupportStatus::cases())->mapWithKeys(fn (ComplaintSupportStatus $status) => [$status->value => $status->label()])->all(),
        ]);
    }

    /**
     * Complaint card
     *
     * The row plus the full `description`, the `customer` (phone masked unless admin.pii.view; a reveal is audited;
     * `telegram_linked` says whether a reply can reach them), the `order`, `store_reply` (the store's own answer),
     * `replies` (what support sent), `resolution` (note, by, at), `history` (its audit trail) and `actions` (reply,
     * resolve, reopen).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $complaint = Complaint::query()->where('uuid', $uuid)->first();
        if ($complaint === null) {
            return $this->refuse('Shikoyat topilmadi.', 'not_found', 404);
        }

        return $this->success($this->inbox->card($complaint, $this->member($request)));
    }

    /**
     * Take or move a complaint
     *
     * `support_status` (new, in_progress, answered — `resolved` is the resolve action: 422 `use_resolve`) and/or
     * `assigned_to` (a team member with support.view, or null; 422 `assignee_invalid`). Moving to in_progress without
     * an assignee gives it to you; moving a resolved one back reopens it. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Resolve needs a note (use_resolve), or the assignee cannot take complaints (assignee_invalid)', type: self::ERROR)]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'support_status' => ['sometimes', 'required', Rule::in(ComplaintSupportStatus::values())],
            'assigned_to' => 'sometimes|nullable|integer|min:1|max:999999999999999999',
        ], UzValidation::messages());

        return $this->act($request, $uuid, function (Complaint $complaint) use ($request, $data) {
            $change = $this->inbox->update($complaint, $this->member($request), $data);
            $audit = AdminAudit::for($request)->changes($change['before'], $change['after']);
            if ($change['before'] === $change['after']) {
                $audit->skip();
            }
        }, "Shikoyat yangilandi.");
    }

    /**
     * Reply to the customer
     *
     * `text` (1–1000 characters, plain text) goes to the customer's Telegram through the RIZQIM bot, queued; kept in
     * the complaint's `replies`; the complaint becomes `answered` (and yours, if nobody had it). `canned_reply_id`: the
     * tayyor javob it started from (optional). 422 `no_recipient` (no Telegram), 503 `telegram_not_configured`, 409
     * `already_resolved`. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'The customer has no Telegram (no_recipient)', type: self::ERROR)]
    #[ApiDocResponse(409, 'Already resolved (already_resolved)', type: self::ERROR)]
    public function reply(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'text' => 'required|string|min:1|max:1000',
            'canned_reply_id' => 'nullable|integer|exists:support_canned_replies,id',
        ], UzValidation::messages());
        $canned = isset($data['canned_reply_id']) ? SupportCannedReply::query()->find($data['canned_reply_id']) : null;

        return $this->act($request, $uuid, function (Complaint $complaint) use ($request, $data, $canned) {
            $before = $complaint->support_status->value;
            $sent = $this->inbox->reply($complaint, $this->member($request), $data['text'], $canned);
            AdminAudit::for($request)
                ->changes(['support_status' => $before], ['support_status' => ComplaintSupportStatus::ANSWERED->value])
                ->meta(['text' => mb_substr(trim($data['text']), 0, 500), 'canned_reply_id' => $canned?->id, 'reply_id' => (int) $sent['reply']->id]);
        }, 'Javob mijozga yuborildi.');
    }

    /**
     * Resolve a complaint
     *
     * `note` (3–1000 characters): how it was solved. The complaint becomes `resolved`, and the store flow is closed
     * with the note as its resolution (the customer reads it in the Mini App). 409 `already_resolved`. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(409, 'Already resolved (already_resolved)', type: self::ERROR)]
    public function resolve(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'note' => 'required|string|min:3|max:1000',
        ], UzValidation::messages());

        return $this->act($request, $uuid, function (Complaint $complaint) use ($request, $data) {
            $change = $this->inbox->resolve($complaint, $this->member($request), $data['note']);
            AdminAudit::for($request)->changes($change['before'], $change['after'])->meta(['note' => mb_substr(trim($data['note']), 0, 500)]);
        }, 'Shikoyat hal qilindi.');
    }

    /**
     * @param  callable(Complaint): void  $action
     */
    private function act(Request $request, string $uuid, callable $action, string $message): JsonResponse
    {
        $complaint = Complaint::query()->where('uuid', $uuid)->first();
        if ($complaint === null) {
            return $this->refuse('Shikoyat topilmadi.', 'not_found', 404);
        }

        try {
            $action($complaint);
        } catch (CustomerAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($complaint);

        return $this->success($this->inbox->card($complaint->refresh(), $this->member($request)), $message);
    }
}
