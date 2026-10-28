<?php

namespace App\Services\Admin\Customers;

use App\Enums\AdminPermission;
use App\Enums\ComplaintSupportStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\SupportCannedReply;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminPii;
use App\Services\Telegram\TelegramMessenger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * P4-31: Mijozlar va yordam → Shikoyatlar qutisi. Every customer complaint of the platform with its support side
 * (App\Enums\ComplaintSupportStatus: new → in_progress → answered → resolved), who took it, the store's own answer and
 * the customer ↔ store flow (complaints.status), and what support wrote to the customer.
 *
 * - update: the support status (not `resolved`: that is resolve(), with a note) and the assignee; taking a complaint
 *   in progress without an assignee assigns it to the member; moving a resolved one back reopens it (the store flow
 *   goes back to in_review).
 * - reply: a plain-text message to the customer through the RIZQIM bot, queued like every Telegram message (the
 *   order interventions' path); kept as a ComplaintReply; the complaint becomes `answered`. Without a bot or without a
 *   Telegram account nothing is saved (503 telegram_not_configured, 422 no_recipient).
 * - resolve: `resolved` with a note, and the store flow is closed with that note as the resolution comment (the Mini
 *   App shows it to the customer), resolved_by the member — what the old panel's "Hal qilish" did.
 *
 * Waiting: open on the support side (new, in progress), not answered by the store without the customer sending it on
 * (store flow owner_replied), and older than support.complaint_waiting_hours (the attention source and the `waiting`
 * filter).
 */
class ComplaintInbox
{
    public const STATUS_FILTERS = ['open', 'new', 'in_progress', 'answered', 'resolved', 'all'];

    public function __construct(
        private readonly AdminPii $pii,
        private readonly AdminPermissionService $permissions,
        private readonly TelegramMessenger $telegram,
    ) {}

    public static function waitingHours(): int
    {
        return max(1, (int) config('support.complaint_waiting_hours', 4));
    }

    /**
     * Complaints still waiting for the support team for too long.
     *
     * @return Builder<Complaint>
     */
    public static function waiting(?CarbonImmutable $now = null): Builder
    {
        $now ??= CarbonImmutable::now();

        return Complaint::query()
            ->whereIn('support_status', ComplaintSupportStatus::openValues())
            ->whereNotIn('status', ['owner_replied', 'closed'])
            ->where('created_at', '<=', $now->subHours(self::waitingHours()));
    }

    /**
     * @param  array{status?: string|null, assigned?: string|null, type?: string|null, waiting?: bool|null, q?: string|null, per_page?: int|null, cursor?: string|null}  $filters
     * @return CursorPaginator<int, Complaint>
     */
    public function page(User $member, array $filters): CursorPaginator
    {
        $status = $filters['status'] ?? 'open';
        $query = !empty($filters['waiting']) ? self::waiting() : Complaint::query();
        $query->with(['user:id,name,telegram_id', 'order:id,uuid,short_code,store_id', 'order.store:id,name', 'assignee:id,name']);

        match ($status) {
            'open' => $query->whereIn('support_status', ComplaintSupportStatus::openValues()),
            'all' => null,
            default => $query->where('support_status', $status),
        };

        $assigned = $filters['assigned'] ?? null;
        if ($assigned === 'me') {
            $query->where('assigned_to', $member->id);
        } elseif ($assigned === 'none') {
            $query->whereNull('assigned_to');
        } elseif (is_string($assigned) && ctype_digit($assigned)) {
            $query->where('assigned_to', (int) $assigned);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $code = CustomerFormat::like(ltrim($q, '#'));
            $like = '%' . CustomerFormat::like($q) . '%';
            $query->where(fn (Builder $where) => $where
                ->whereIn('order_id', \App\Models\Order::query()->select('id')->whereRaw('lower(short_code) like ?', [$code . '%']))
                ->orWhereRaw('lower(description) like ?', [$like]));
        }

        // The open ones oldest first (the longest waiting on top), everything else newest first
        $oldestFirst = in_array($status, ['open', 'new', 'in_progress'], true);

        $direction = $oldestFirst ? 'asc' : 'desc';

        return $query->orderBy('created_at', $direction)->orderBy('id', $direction)
            ->cursorPaginate(max(1, min(100, (int) ($filters['per_page'] ?? 30))))
            ->withQueryString();
    }

    /**
     * @return array<string, int> support status => count, and `open`, `waiting`
     */
    public function counts(): array
    {
        $rows = Complaint::query()->selectRaw('support_status, count(*) as total')->groupBy('support_status')->pluck('total', 'support_status');
        $counts = [];
        foreach (ComplaintSupportStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }
        $counts['open'] = $counts['new'] + $counts['in_progress'];
        $counts['waiting'] = self::waiting()->count();

        return $counts;
    }

    /**
     * @param  iterable<Complaint>  $complaints
     * @return list<array<string, mixed>>
     */
    public function rows(iterable $complaints): array
    {
        $now = CarbonImmutable::now();
        $hours = self::waitingHours();
        $rows = [];
        foreach ($complaints as $complaint) {
            $rows[] = $this->row($complaint, $now, $hours);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Complaint $complaint, CarbonImmutable $now, int $hours): array
    {
        $order = $complaint->order;
        $open = $complaint->support_status->isOpen();

        return [
            'uuid' => (string) $complaint->uuid,
            'type' => (string) $complaint->type,
            'type_label' => ComplaintLabels::type($complaint->type),
            'description' => CustomerFormat::short($complaint->description),
            'support_status' => $complaint->support_status->value,
            'support_status_label' => $complaint->support_status->label(),
            'store_flow' => (string) $complaint->status,
            'store_flow_label' => ComplaintLabels::storeFlow($complaint->status),
            'escalated' => $complaint->status === 'in_review',
            'store_replied' => filled($complaint->reply),
            'waiting' => $open && !in_array($complaint->status, ['owner_replied', 'closed'], true) && $complaint->created_at !== null && $complaint->created_at->lte($now->subHours($hours)),
            'customer' => $complaint->user ? ['id' => (int) $complaint->user->id, 'name' => (string) $complaint->user->name] : null,
            'order' => $order ? ['uuid' => (string) $order->uuid, 'short_code' => $order->short_code] : null,
            'store' => $order?->store ? ['id' => (int) $order->store->id, 'name' => (string) $order->store->name] : null,
            'assignee' => $complaint->assignee ? ['id' => (int) $complaint->assignee->id, 'name' => (string) $complaint->assignee->name] : null,
            'created_at' => CustomerFormat::iso($complaint->created_at),
            'answered_at' => CustomerFormat::iso($complaint->answered_at),
            'resolved_at' => CustomerFormat::iso($complaint->resolved_at),
        ];
    }

    /**
     * The complaint card: the row, the full text, the customer (phone through AdminPii), the order, the store's answer,
     * the support conversation, the resolution, the audit trail and the actions the member may take.
     *
     * @return array<string, mixed>
     */
    public function card(Complaint $complaint, User $member): array
    {
        $complaint->loadMissing(['user', 'order.store:id,name', 'assignee:id,name', 'resolver:id,name', 'replies.author:id,name']);
        $row = $this->row($complaint, CarbonImmutable::now(), self::waitingHours());
        $user = $complaint->user;
        $order = $complaint->order;
        $manage = $this->permissions->has($member, AdminPermission::SUPPORT_MANAGE);
        $resolved = $complaint->support_status === ComplaintSupportStatus::RESOLVED;

        return [
            ...$row,
            'description' => (string) $complaint->description,
            'customer' => $user ? [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'phone' => $this->pii->phone($member, $user->phone, 'user', (int) $user->id),
                'telegram_linked' => $user->hasTelegram(),
                'blocked' => $user->isBlocked(),
                'href' => '/users/' . $user->id,
            ] : null,
            'order' => $order ? [
                'uuid' => (string) $order->uuid,
                'short_code' => $order->short_code,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total' => CustomerFormat::money($order->total_price),
                'created_at' => CustomerFormat::iso($order->created_at),
                'href' => '/orders/' . $order->uuid,
            ] : null,
            'store_reply' => filled($complaint->reply) ? (string) $complaint->reply : null,
            'replies' => $complaint->replies->map(fn (ComplaintReply $reply) => [
                'id' => (int) $reply->id,
                'body' => (string) $reply->body,
                'author' => $reply->author ? ['id' => (int) $reply->author->id, 'name' => (string) $reply->author->name] : null,
                'channel' => (string) $reply->channel,
                'created_at' => CustomerFormat::iso($reply->created_at),
            ])->values()->all(),
            'resolution' => $resolved ? [
                'note' => $complaint->resolution_comment,
                'by' => $complaint->resolver ? ['id' => (int) $complaint->resolver->id, 'name' => (string) $complaint->resolver->name] : null,
                'at' => CustomerFormat::iso($complaint->resolved_at),
            ] : null,
            'history' => $this->history($complaint),
            'actions' => [
                'manage' => $manage,
                'reply' => $manage && !$resolved && $user !== null && $user->hasTelegram(),
                'resolve' => $manage && !$resolved,
                'reopen' => $manage && $resolved,
            ],
        ];
    }

    /**
     * @param  array{support_status?: string, assigned_to?: int|null}  $change
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     *
     * @throws CustomerAdminException
     */
    public function update(Complaint $complaint, User $member, array $change): array
    {
        return DB::transaction(function () use ($complaint, $member, $change) {
            $complaint = Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            $before = ['support_status' => $complaint->support_status->value, 'assigned_to' => $complaint->assigned_to !== null ? (int) $complaint->assigned_to : null];

            if (array_key_exists('assigned_to', $change)) {
                $assignee = $change['assigned_to'];
                if ($assignee !== null) {
                    $this->assertAssignable((int) $assignee);
                }
                $complaint->assigned_to = $assignee;
            }

            if (isset($change['support_status'])) {
                $to = ComplaintSupportStatus::from($change['support_status']);
                if ($to === ComplaintSupportStatus::RESOLVED) {
                    throw new CustomerAdminException("Hal qilish uchun izoh bilan «Hal qilindi» amalidan foydalaning.", 'use_resolve');
                }
                if ($to !== $complaint->support_status) {
                    if ($complaint->support_status === ComplaintSupportStatus::RESOLVED) {
                        // Reopened: the store flow is no longer closed, the customer's case is back with RIZQIM
                        $complaint->resolved_at = null;
                        if ($complaint->status === 'closed') {
                            $complaint->status = 'in_review';
                        }
                    }
                    $complaint->support_status = $to;
                    $complaint->support_status_changed_at = now();
                }
                if ($to === ComplaintSupportStatus::IN_PROGRESS && $complaint->assigned_to === null && !array_key_exists('assigned_to', $change)) {
                    $complaint->assigned_to = $member->id;
                }
            }

            $complaint->save();
            $after = ['support_status' => $complaint->support_status->value, 'assigned_to' => $complaint->assigned_to !== null ? (int) $complaint->assigned_to : null];

            return ['before' => $before, 'after' => $after];
        });
    }

    /**
     * @return array{reply: ComplaintReply, recipients: int}
     *
     * @throws CustomerAdminException
     */
    public function reply(Complaint $complaint, User $member, string $text, ?SupportCannedReply $canned): array
    {
        if ($complaint->support_status === ComplaintSupportStatus::RESOLVED) {
            throw new CustomerAdminException("Shikoyat hal qilingan. Javob yozish uchun avval uni qayta oching.", 'already_resolved', 409);
        }
        if (!$this->telegram->isConfigured()) {
            throw new CustomerAdminException("Telegram bot sozlanmagan: xabar yuborib bo'lmaydi.", 'telegram_not_configured', 503);
        }
        $complaint->loadMissing(['user', 'order:id,short_code']);
        $user = $complaint->user;
        if ($user === null || !$user->hasTelegram()) {
            throw new CustomerAdminException("Mijozning Telegram akkaunti yo'q: javobni yuborib bo'lmaydi.", 'no_recipient');
        }

        $text = trim($text);
        $code = $complaint->order?->short_code;

        $reply = DB::transaction(function () use ($complaint, $member, $text, $canned) {
            $reply = ComplaintReply::create([
                'complaint_id' => $complaint->id,
                'author_id' => $member->id,
                'body' => $text,
                'canned_reply_id' => $canned?->id,
                'channel' => 'telegram',
            ]);

            $complaint->forceFill([
                'support_status' => ComplaintSupportStatus::ANSWERED,
                'support_status_changed_at' => $complaint->support_status === ComplaintSupportStatus::ANSWERED ? $complaint->support_status_changed_at : now(),
                'answered_at' => now(),
                'assigned_to' => $complaint->assigned_to ?? $member->id,
            ])->save();

            return $reply;
        });

        // Plain text (no parse mode): whatever the member typed reaches the chat as it is; queued after the commit
        $this->telegram->sendMessage(
            (string) $user->telegram_id,
            "📣 RIZQIM yordam xizmati\nShikoyatingiz bo'yicha" . ($code ? " (buyurtma #{$code})" : '') . ":\n\n" . $text,
            [],
            ['user_id' => $user->id, 'order_id' => $complaint->order_id, 'type' => 'admin_complaint_reply'],
        );

        return ['reply' => $reply, 'recipients' => 1];
    }

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     *
     * @throws CustomerAdminException
     */
    public function resolve(Complaint $complaint, User $member, string $note): array
    {
        return DB::transaction(function () use ($complaint, $member, $note) {
            $complaint = Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            if ($complaint->support_status === ComplaintSupportStatus::RESOLVED) {
                throw new CustomerAdminException('Shikoyat allaqachon hal qilingan.', 'already_resolved', 409);
            }
            $before = ['support_status' => $complaint->support_status->value, 'status' => (string) $complaint->status];

            $complaint->forceFill([
                'support_status' => ComplaintSupportStatus::RESOLVED,
                'support_status_changed_at' => now(),
                'resolved_at' => now(),
                'assigned_to' => $complaint->assigned_to ?? $member->id,
                // The store flow closes too, as the old panel's "Hal qilish": the customer reads the note in the Mini App
                'status' => 'closed',
                'resolution_comment' => trim($note),
                'resolved_by' => $member->id,
            ])->save();

            return ['before' => $before, 'after' => ['support_status' => ComplaintSupportStatus::RESOLVED->value, 'status' => 'closed']];
        });
    }

    /**
     * @throws CustomerAdminException
     */
    private function assertAssignable(int $id): void
    {
        $member = User::query()->whereKey($id)->first();
        if ($member === null || !$member->isActivePlatformStaff() || !$this->permissions->has($member, AdminPermission::SUPPORT_VIEW)) {
            throw new CustomerAdminException("Bu xodimga shikoyat biriktirib bo'lmaydi.", 'assignee_invalid', 422);
        }
    }

    /**
     * The complaint's support trail from the audit log (who changed what, replied, resolved).
     *
     * @return list<array<string, mixed>>
     */
    private function history(Complaint $complaint): array
    {
        return AuditLog::query()->with('user:id,name')
            ->where('model_type', $complaint->getMorphClass())->where('model_id', $complaint->id)
            ->orderByDesc('id')->limit(30)->get()
            ->map(fn (AuditLog $log) => [
                'id' => (int) $log->id,
                'action' => $log->action,
                'actor' => $log->user ? ['id' => (int) $log->user->id, 'name' => (string) $log->user->name] : null,
                'before' => $log->before,
                'after' => $log->after,
                'created_at' => CustomerFormat::iso($log->created_at),
            ])->values()->all();
    }

    /**
     * The team members a complaint can be given to (active, with support.view), for the assignee picker.
     *
     * @return list<array{id: int, name: string}>
     */
    public function assignees(): array
    {
        return User::query()->whereNotNull('platform_role')->whereNull('platform_disabled_at')->orderBy('name')
            ->get(['id', 'name', 'platform_role', 'role', 'platform_disabled_at'])
            ->filter(fn (User $user) => $this->permissions->has($user, AdminPermission::SUPPORT_VIEW))
            ->map(fn (User $user) => ['id' => (int) $user->id, 'name' => (string) $user->name])
            ->values()->all();
    }
}
