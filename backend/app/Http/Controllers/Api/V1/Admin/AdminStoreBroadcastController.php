<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\StoreStatus;
use App\Http\Requests\Admin\AdminRules;
use App\Models\StoreBroadcast;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Stores\StoreAdminException;
use App\Services\Admin\Stores\StoreBroadcastService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-22: ommaviy e'lon — a Telegram announcement to the owners of all or selected stores. The list needs
 * admin.stores.view; preview and send admin.stores.manage, and sending also a step-up, a `reason`, the preview's
 * `recipients` count confirmed, at most 5 attempts per member an hour (throttle:admin-store-broadcast) and
 * StoreBroadcastService::LIMIT_PER_DAY a day platform-wide.
 */
#[Group('Admin')]
class AdminStoreBroadcastController extends AdminController
{
    private const ROW = 'array{uuid: string, audience: string, text: string, reason: string, stores: int, recipients: int, queued: int, status: string, created_by: array{id: int, name: string}|null, created_at: string|null, finished_at: string|null}';

    private const PREVIEW = 'array{stores: int, recipients: int, without_telegram: int, sample: list<string>}';

    public function __construct(
        private readonly StoreBroadcastService $broadcasts,
    ) {}

    /**
     * Announcements
     *
     * The last 50, newest first.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{broadcasts: list<' . self::ROW . '>, limit_per_day: int, sent_today: int}}')]
    public function index(): JsonResponse
    {
        $rows = StoreBroadcast::query()->with('creator:id,name')->orderByDesc('id')->limit(50)->get()
            ->map(fn (StoreBroadcast $broadcast) => [
                'uuid' => (string) $broadcast->uuid,
                'audience' => $broadcast->audience,
                'text' => $broadcast->text,
                'reason' => $broadcast->reason,
                'stores' => (int) $broadcast->stores,
                'recipients' => (int) $broadcast->recipients,
                'queued' => (int) $broadcast->queued,
                'status' => $broadcast->status,
                'created_by' => $broadcast->creator ? ['id' => (int) $broadcast->creator->id, 'name' => (string) $broadcast->creator->name] : null,
                'created_at' => $broadcast->created_at?->toIso8601String(),
                'finished_at' => $broadcast->finished_at?->toIso8601String(),
            ])->values()->all();

        return $this->success([
            'broadcasts' => $rows,
            'limit_per_day' => StoreBroadcastService::LIMIT_PER_DAY,
            'sent_today' => StoreBroadcast::query()->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }

    /**
     * Preview an announcement's audience
     *
     * `audience`: `all` (optionally `statuses[]` and `region_id`) or `selected` (`store_ids[]`, up to 500). Answers how
     * many stores, how many owners get it (`recipients`, owners who joined the bot, each once), how many stores have
     * no owner on Telegram, and up to 5 store names. Nothing is recorded.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::PREVIEW . '}')]
    public function preview(Request $request): JsonResponse
    {
        $audience = $request->validate(self::audienceRules(), UzValidation::messages());
        AdminAudit::for($request)->skip();

        return $this->success($this->broadcasts->preview($audience));
    }

    /**
     * Send an announcement
     *
     * The audience fields, `text` (1–2000 characters, plain text, sent as "📣 RIZQIM e'loni"), `recipients` (the
     * preview's count: a different count now is 409 `count_changed` with the new `preview`) and `reason`. Step-up;
     * 429 when over 5 attempts an hour per member or the daily limit (`daily_limit`); 422 `no_recipient`; 503
     * `telegram_not_configured`. 201 with the broadcast; the messages are queued.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{uuid: string, recipients: int, stores: int, status: string}}')]
    #[ApiDocResponse(409, 'The audience changed since the preview (count_changed)', type: 'array{success: false, message: string, code: string, data: array{reason: string, preview: ' . self::PREVIEW . '}}')]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...self::audienceRules(),
            'text' => 'required|string|min:1|max:2000',
            'recipients' => 'required|integer|min:1',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $audience = array_intersect_key($data, array_flip(['audience', 'store_ids', 'statuses', 'region_id']));
        try {
            $broadcast = $this->broadcasts->send($audience, $data['text'], (int) $data['recipients'], $this->member($request), $data['reason']);
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($broadcast)->meta([
            'audience' => $broadcast->audience,
            'stores' => (int) $broadcast->stores,
            'recipients' => (int) $broadcast->recipients,
            'store_ids' => $broadcast->store_ids,
            'text' => mb_substr($broadcast->text, 0, 500),
        ]);

        return $this->success([
            'uuid' => (string) $broadcast->uuid,
            'recipients' => (int) $broadcast->recipients,
            'stores' => (int) $broadcast->stores,
            'status' => $broadcast->status,
        ], "E'lon navbatga qo'yildi: {$broadcast->recipients} ta egaga yuboriladi.", 201);
    }

    /**
     * @return array<string, mixed>
     */
    private static function audienceRules(): array
    {
        return [
            'audience' => ['required', Rule::in(['all', 'selected'])],
            'store_ids' => 'required_if:audience,selected|array|max:' . StoreBroadcastService::MAX_SELECTED,
            'store_ids.*' => 'integer|min:1|max:999999999999999999',
            'statuses' => 'nullable|array',
            'statuses.*' => [Rule::enum(StoreStatus::class)],
            'region_id' => 'nullable|integer|min:1|max:999999999999999999',
        ];
    }
}
