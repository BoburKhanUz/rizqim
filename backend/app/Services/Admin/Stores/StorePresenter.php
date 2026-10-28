<?php

namespace App\Services\Admin\Stores;

use App\Domain\Fiscal\FiscalReadiness;
use App\Domain\Fiscal\FiscalSettings;
use App\Enums\AdminPermission;
use App\Enums\DeviceType;
use App\Enums\StoreHealthLevel;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Complaint;
use App\Models\Device;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\StoreCrmProfile;
use App\Models\StoreHealthScore;
use App\Models\StoreNote;
use App\Models\StoreStatusLog;
use App\Models\StoreTask;
use App\Models\StoreTransaction;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminPii;
use App\Services\Admin\Orders\StoreQualityService;
use App\Services\Admin\Today\PlatformStats;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * P4-22: how the Ovqatxonalar section shows stores, applications, notes and tasks. Phones and card numbers through
 * AdminPii (masked unless admin.pii.view; every reveal is audited), money as decimal strings, times ISO 8601 in
 * Asia/Tashkent. List rows carry no phone (as the order list); the card does.
 */
class StorePresenter
{
    /** A terminal is online when it was seen this recently (the Monitoring rule). */
    private const ONLINE_MINUTES = 10;

    public function __construct(
        private readonly AdminPii $pii,
        private readonly AdminPermissionService $permissions,
        private readonly StorePlanReader $plans,
        private readonly StoreHealthCalculator $health,
        private readonly StoreQualityService $quality,
        private readonly ApplicationPipeline $pipeline,
        private readonly LedgerService $ledger,
        private readonly FiscalReadiness $fiscal,
    ) {}

    /**
     * @param  iterable<Store>  $stores  loaded with branches.region, healthScore, crmProfile and branches_count
     * @return list<array<string, mixed>>
     */
    public function rows(iterable $stores): array
    {
        $stores = collect($stores);
        $plans = $this->plans->forStores($stores->map(fn (Store $store) => (int) $store->id)->all());
        $health = StoreHealthScore::query()->whereIn('store_id', $stores->pluck('id'))->get()->keyBy('store_id');
        $profiles = StoreCrmProfile::query()->whereIn('store_id', $stores->pluck('id'))->get()->keyBy('store_id');
        $owners = User::query()->whereIn('store_id', $stores->pluck('id'))->where('role', UserRole::OWNER->value)
            ->orderBy('id')->get(['id', 'store_id', 'name', 'telegram_id'])->unique('store_id')->keyBy('store_id');

        return $stores->map(function (Store $store) use ($plans, $health, $profiles, $owners) {
            $branch = $store->branches->sortBy('id')->first();
            /** @var StoreHealthScore|null $score */
            $score = $health->get($store->id);
            /** @var StoreCrmProfile|null $profile */
            $profile = $profiles->get($store->id);
            /** @var User|null $owner */
            $owner = $owners->get($store->id);

            return [
                'id' => (int) $store->id,
                'uuid' => (string) $store->uuid,
                'name' => (string) $store->name,
                'status' => $store->status->value,
                'status_label' => StoreLabels::status($store->status),
                'is_active' => (bool) $store->is_active,
                'is_premium' => (bool) $store->is_premium,
                'is_featured' => (bool) $store->is_featured,
                'region' => $branch?->region ? ['id' => (int) $branch->region->id, 'name' => (string) $branch->region->name_uz] : null,
                'branches_count' => (int) ($store->branches_count ?? $store->branches->count()),
                'owner' => $owner ? ['id' => (int) $owner->id, 'name' => (string) $owner->name, 'telegram_linked' => StoreActions::telegramChat($owner) !== null] : null,
                'plan' => $plans[(int) $store->id] ?? null,
                'debt_balance' => Money::of($store->debt_balance)->toString(),
                // P4-21: debt = subscription debt (unpaid invoices) + commission debt; the credit limit counts the latter
                'subscription_debt' => Money::of($store->subscription_debt)->toString(),
                'commission_debt' => $store->commissionDebt()->toString(),
                'credit_limit' => Money::of($store->credit_limit)->abs()->toString(),
                'over_limit' => $store->exceedsCreditLimit(),
                'health' => $score ? $this->healthBrief($score) : null,
                'next_contact_on' => $profile?->next_contact_on?->format('Y-m-d'),
                'created_at' => $this->iso($store->created_at),
            ];
        })->values()->all();
    }

    /**
     * The 360° card.
     *
     * @return array<string, mixed>
     */
    public function card(Store $store, User $member): array
    {
        $store->loadMissing(['branches.region', 'branches.district']);
        $now = PlatformStats::now();
        $health = $this->health->forStore($store, $now);
        $application = StoreApplication::query()->where('store_id', $store->id)->latest('id')->first();
        $owner = StoreActions::owner($store);
        $canManage = $this->permissions->has($member, AdminPermission::STORES_MANAGE);

        return [
            'profile' => [
                'id' => (int) $store->id,
                'uuid' => (string) $store->uuid,
                'name' => (string) $store->name,
                'status' => $store->status->value,
                'status_label' => StoreLabels::status($store->status),
                'is_active' => (bool) $store->is_active,
                'is_open' => (bool) $store->getRawOriginal('is_open'),
                'is_premium' => (bool) $store->is_premium,
                'is_featured' => (bool) $store->is_featured,
                'is_order_locked' => (bool) $store->is_order_locked,
                'phone' => $this->pii->phone($member, $store->phone, 'store', (int) $store->id),
                'address' => $store->address,
                'description' => $store->description,
                'logo_url' => $store->logo_url,
                'tin' => $store->tin ?: $store->tax_number,
                'legal_name' => $store->legal_name,
                'tax_regime' => $store->tax_regime,
                'payment_account_type' => (string) $store->payment_account_type,
                'commission_rate' => (string) $store->commissionRate(),
                'rating' => (string) $store->rating,
                'review_count' => (int) $store->review_count,
                'created_at' => $this->iso($store->created_at),
                // P4-32: whether the owner can sign in by SMS code, and who proved the number (sms: the owner, vouched: RIZQIM)
                'owner_login_phone' => [
                    'set' => $owner !== null && \App\Services\Auth\StaffPhoneLoginService::loginPhone($owner) !== null,
                    'via' => $owner !== null && \App\Services\Auth\StaffPhoneLoginService::loginPhone($owner) !== null ? $owner->phone_verified_via : null,
                ],
                'telegram' => [
                    'store_chat_linked' => filled($store->telegram_admin_chat_id),
                    'owner_linked' => $owner !== null && StoreActions::telegramChat($owner) !== null,
                    'owner_invite_pending' => $owner !== null && filled($owner->telegram_invite_token),
                ],
            ],
            'application' => $application ? $this->applicationRow($application) : null,
            'contacts' => $this->contacts($store, $member, $application),
            'branches' => $store->branches->sortBy('id')->values()->map(fn (Branch $branch) => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
                'address' => $branch->address,
                'phone' => $this->pii->phone($member, $branch->phone, 'branch', (int) $branch->id),
                'region' => $branch->region ? ['id' => (int) $branch->region->id, 'name' => (string) $branch->region->name_uz] : null,
                'district' => $branch->district ? ['id' => (int) $branch->district->id, 'name' => (string) $branch->district->name_uz] : null,
                'is_active' => (bool) $branch->is_active,
                'open_time' => $branch->open_time,
                'close_time' => $branch->close_time,
            ])->all(),
            'status_history' => StoreStatusLog::query()->with('admin:id,name')->where('store_id', $store->id)
                ->orderByDesc('id')->limit(30)->get()
                ->map(fn (StoreStatusLog $log) => [
                    'from_status' => $log->from_status,
                    'from_label' => $log->from_status !== null ? StoreLabels::status($log->from_status) : null,
                    'to_status' => $log->to_status,
                    'to_label' => StoreLabels::status($log->to_status),
                    'by' => $log->admin ? ['id' => (int) $log->admin->id, 'name' => (string) $log->admin->name] : null,
                    'comment' => $log->comment,
                    'created_at' => $this->iso($log->created_at),
                ])->values()->all(),
            'plan' => $this->plans->card($store),
            'devices' => $this->devices($store, $now),
            'fiscal' => $this->fiscalState($store, $now),
            'finance' => $this->finance($store, $member),
            'orders' => $this->orders($store),
            'complaints' => $this->complaints($store),
            'health' => [
                'level' => $health['level']->value,
                'label' => $health['level']->label(),
                'score' => $health['score'],
                'reasons' => $health['reasons'],
                'metrics' => $health['metrics'],
                'computed_at' => $health['computed_at'],
            ],
            'crm' => $this->crm($store, null),
            'audit' => $this->audit($store),
            'actions' => [
                'manage' => $canManage,
                'suspend' => $canManage && !($store->status === StoreStatus::SUSPENDED && !$store->is_active),
                'activate' => $canManage && !in_array($store->status, [StoreStatus::PAYMENT_REQUIRED, StoreStatus::FINANCE_HOLD], true)
                    && !($store->status === StoreStatus::ACTIVE && $store->is_active)
                    && StoreActions::openApplication($store) === null,
                'credit_limit' => $canManage,
                'display' => $canManage,
                // An owner already on Telegram is never relinked (review F3-1): the action is for an owner still to join
                'telegram_link' => $canManage && $owner !== null && StoreActions::telegramChat($owner) === null,
                'message_owner' => $canManage && $owner !== null && StoreActions::telegramChat($owner) !== null,
                // P4-32
                'payment_account' => $canManage,
                'owner_login_phone' => $canManage && $owner !== null && $this->permissions->has($member, AdminPermission::STORES_IMPERSONATE),
                // P4-21's endpoint (admin.subscriptions.manage); the POS va obunalar screens use it in stage B
                'commission_override' => $this->permissions->has($member, AdminPermission::SUBSCRIPTIONS_MANAGE),
            ],
            'links' => [
                'orders' => '/orders?store_id=' . $store->id,
                'audit' => '/audit?store_id=' . $store->id,
                'subscription' => '/pos?store=' . $store->id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applicationRow(StoreApplication $application, ?CarbonInterface $now = null): array
    {
        $application->loadMissing(['region:id,name_uz', 'assignee:id,name', 'store:id,name,status']);

        return [
            'id' => (int) $application->id,
            'uuid' => (string) $application->uuid,
            'name' => (string) $application->name,
            'format' => $application->format->value,
            'format_label' => $application->format->label(),
            'region' => $application->region ? ['id' => (int) $application->region->id, 'name' => (string) $application->region->name_uz] : null,
            'city' => $application->city,
            'owner_name' => (string) $application->owner_name,
            'source' => $application->source,
            'stage' => $application->stage->value,
            'stage_label' => $application->stage->label(),
            'stage_changed_at' => $this->iso($application->stage_changed_at),
            'assignee' => $application->assignee ? ['id' => (int) $application->assignee->id, 'name' => (string) $application->assignee->name] : null,
            'due_on' => $application->due_on?->format('Y-m-d'),
            'overdue' => ApplicationPipeline::isOverdue($application),
            'store' => $application->store ? ['id' => (int) $application->store->id, 'name' => (string) $application->store->name, 'status' => $application->store->status->value] : null,
            'created_at' => $this->iso($application->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applicationCard(StoreApplication $application, User $member): array
    {
        return [
            ...$this->applicationRow($application),
            'phone' => $this->pii->phone($member, $application->phone, 'store_application', (int) $application->id),
            'tin' => $application->tin,
            'comment' => $application->comment,
            'rejected_reason' => $application->rejected_reason,
            'rejected_at' => $this->iso($application->rejected_at),
            'converted_at' => $this->iso($application->converted_at),
            'activated_at' => $this->iso($application->activated_at),
            'checklist' => $this->pipeline->checklist($application),
            'crm' => $this->crm(null, $application),
            'audit' => AuditLog::query()->with('user:id,name,role,platform_role')
                ->where('model_type', $application->getMorphClass())->where('model_id', $application->id)
                ->orderByDesc('id')->limit(50)->get()->map(fn (AuditLog $log) => $this->auditEntry($log))->values()->all(),
            'actions' => [
                'manage' => $this->permissions->has($member, AdminPermission::STORES_MANAGE),
                'convert' => $application->store_id === null && $application->stage->isOpen(),
                'reject' => !in_array($application->stage->value, ['active', 'rejected'], true),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function note(StoreNote $note): array
    {
        $note->loadMissing('author:id,name');

        return [
            'id' => (int) $note->id,
            'body' => (string) $note->body,
            'author' => $note->author ? ['id' => (int) $note->author->id, 'name' => (string) $note->author->name] : null,
            'store_id' => $note->store_id !== null ? (int) $note->store_id : null,
            'application_id' => $note->application_id !== null ? (int) $note->application_id : null,
            'created_at' => $this->iso($note->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function task(StoreTask $task): array
    {
        $task->loadMissing(['assignee:id,name', 'creator:id,name', 'store:id,name', 'application:id,uuid,name']);
        $today = PlatformStats::dayStart(PlatformStats::now())->format('Y-m-d');
        $due = $task->due_on->format('Y-m-d');

        return [
            'id' => (int) $task->id,
            'title' => (string) $task->title,
            'due_on' => $due,
            'state' => $task->done_at !== null ? 'done' : ($due < $today ? 'overdue' : ($due === $today ? 'today' : 'upcoming')),
            'assignee' => $task->assignee ? ['id' => (int) $task->assignee->id, 'name' => (string) $task->assignee->name] : null,
            'created_by' => $task->creator ? ['id' => (int) $task->creator->id, 'name' => (string) $task->creator->name] : null,
            'store' => $task->store ? ['id' => (int) $task->store->id, 'name' => (string) $task->store->name] : null,
            'application' => $task->application ? ['uuid' => (string) $task->application->uuid, 'name' => (string) $task->application->name] : null,
            'done_at' => $this->iso($task->done_at),
            'created_at' => $this->iso($task->created_at),
        ];
    }

    /**
     * @return array{level: string, label: string, score: int, reasons: int, top_reason: string|null, computed_at: string|null}
     */
    private function healthBrief(StoreHealthScore $score): array
    {
        $reasons = $score->reasons;

        return [
            'level' => $score->level->value,
            'label' => $score->level->label(),
            'score' => (int) $score->score,
            'reasons' => count($reasons),
            'top_reason' => isset($reasons[0]['label']) ? (string) $reasons[0]['label'] : null,
            'computed_at' => $this->iso($score->computed_at),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contacts(Store $store, User $member, ?StoreApplication $application): array
    {
        $staff = User::query()->where('store_id', $store->id)
            ->whereIn('role', [UserRole::OWNER->value, UserRole::MANAGER->value])
            ->orderByRaw("case when role = 'owner' then 0 else 1 end")->orderBy('id')
            ->get(['id', 'name', 'role', 'phone', 'telegram_id', 'branch_id']);

        $contacts = $staff->map(fn (User $user) => [
            'kind' => 'staff',
            'user_id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => $user->role->value,
            'role_label' => $user->role->labelUz(),
            'phone' => $this->pii->phone($member, $user->phone, 'user', (int) $user->id),
            'telegram_linked' => StoreActions::telegramChat($user) !== null,
            'branch_id' => $user->branch_id !== null ? (int) $user->branch_id : null,
        ])->values()->all();

        if ($application !== null) {
            $contacts[] = [
                'kind' => 'application',
                'user_id' => null,
                'name' => (string) $application->owner_name,
                'role' => 'applicant',
                'role_label' => 'Ariza qoldirgan',
                'phone' => $this->pii->phone($member, $application->phone, 'store_application', (int) $application->id),
                'telegram_linked' => false,
                'branch_id' => null,
            ];
        }

        return $contacts;
    }

    /**
     * @return array<string, mixed>
     */
    private function devices(Store $store, CarbonInterface $now): array
    {
        $online = $now->copy()->subMinutes(self::ONLINE_MINUTES);
        $devices = Device::query()->with('branch:id,name')->where('store_id', $store->id)->whereNull('revoked_at')
            ->orderByRaw("case when type in ('pos', 'kds') then 0 else 1 end")->orderByDesc('last_seen_at')
            ->limit(50)->get();

        $rows = $devices->map(fn (Device $device) => [
            'uuid' => (string) $device->uuid,
            'name' => (string) $device->name,
            'type' => $device->type->value,
            'type_label' => $device->type->label(),
            'terminal' => $device->type->isTerminal(),
            'branch' => $device->branch ? ['id' => (int) $device->branch->id, 'name' => (string) $device->branch->name] : null,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'last_seen_at' => $this->iso($device->last_seen_at),
            'online' => $device->last_seen_at !== null && $device->last_seen_at->greaterThanOrEqualTo($online),
            'paired_at' => $this->iso($device->created_at),
        ])->values()->all();

        $terminals = array_values(array_filter($rows, fn (array $row) => $row['terminal']));

        return [
            'terminals' => count($terminals),
            'terminals_online' => count(array_filter($terminals, fn (array $row) => $row['online'])),
            'by_type' => [
                DeviceType::POS->value => count(array_filter($terminals, fn (array $row) => $row['type'] === DeviceType::POS->value)),
                DeviceType::KDS->value => count(array_filter($terminals, fn (array $row) => $row['type'] === DeviceType::KDS->value)),
            ],
            'items' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fiscalState(Store $store, CarbonInterface $now): array
    {
        $settings = FiscalSettings::of($store);
        $receipts = \App\Models\FiscalReceipt::query()->where('store_id', $store->id);

        return [
            'enabled' => $settings->enabled(),
            'driver' => $settings->enabled() ? $settings->driver() : null,
            'tax_regime' => $store->tax_regime,
            'failed_7d' => (clone $receipts)->where('status', 'failed')->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'pending' => (clone $receipts)->where('status', 'pending')->count(),
            'missing_mxik' => $settings->enabled() ? count($this->fiscal->missingMxik($store)) : 0,
            'last_failed' => (clone $receipts)->where('status', 'failed')->orderByDesc('id')->limit(5)
                ->get(['uuid', 'error', 'total', 'created_at'])
                ->map(fn ($receipt) => [
                    'uuid' => (string) $receipt->uuid,
                    'error' => $receipt->error !== null ? mb_substr((string) $receipt->error, 0, 200) : null,
                    'total' => Money::of($receipt->total)->toString(),
                    'created_at' => $this->iso($receipt->created_at),
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finance(Store $store, User $member): array
    {
        return [
            // P4-21: debt = unpaid subscription invoices (never stop Mini App orders) + commission debt (the credit
            // limit counts this part only); the same numbers as `plan.debt`
            'debt_balance' => Money::of($store->debt_balance)->toString(),
            'subscription_debt' => Money::of($store->subscription_debt)->toString(),
            'commission_debt' => $store->commissionDebt()->toString(),
            'wallet_balance' => Money::of($store->wallet_balance)->toString(),
            'credit_limit' => Money::of($store->credit_limit)->abs()->toString(),
            'over_limit' => $store->exceedsCreditLimit(),
            'min_payout_reserve' => Money::of($store->min_payout_reserve)->toString(),
            'available_for_payout' => $this->ledger->availableForPayout($store)->toString(),
            'payouts' => PayoutRequest::query()->where('store_id', $store->id)->orderByDesc('id')->limit(10)->get()
                ->map(fn (PayoutRequest $payout) => [
                    'id' => (int) $payout->id,
                    'amount' => Money::of($payout->amount)->toString(),
                    'card' => $this->pii->card($member, (string) $payout->card_number, 'payout_request', (int) $payout->id),
                    'status' => (string) $payout->status,
                    'created_at' => $this->iso($payout->created_at),
                    'processed_at' => $this->iso($payout->processed_at),
                ])->values()->all(),
            'ledger' => StoreTransaction::query()->where('store_id', $store->id)->orderByDesc('id')->limit(10)->get()
                ->map(fn (StoreTransaction $entry) => [
                    'id' => (int) $entry->id,
                    'type' => $entry->type->value,
                    'amount' => Money::of($entry->amount)->toString(),
                    'balance_after' => Money::of($entry->balance_after)->toString(),
                    'notes' => $entry->notes !== null ? mb_substr((string) $entry->notes, 0, 200) : null,
                    'created_at' => $this->iso($entry->created_at),
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orders(Store $store): array
    {
        $since = now()->subDays(30);
        $base = Order::query()->where('store_id', $store->id);
        $byChannel = \Illuminate\Support\Facades\DB::table('orders')->where('store_id', $store->id)->whereNull('deleted_at')
            ->where('created_at', '>=', $since)->whereNotIn('status', ['canceled', 'rejected'])
            ->groupBy('channel')->selectRaw('channel, count(*) as orders, coalesce(sum(total_price), 0) as total')->get();

        return [
            'quality' => $this->quality->report((int) $store->id)['stores'][0] ?? null,
            'last_30_days' => $byChannel->map(fn ($row) => [
                'channel' => (string) $row->channel,
                'orders' => (int) $row->orders,
                'total' => Money::of($row->total)->toString(),
            ])->values()->all(),
            'recent' => (clone $base)->orderByDesc('id')->limit(10)
                ->get(['id', 'uuid', 'short_code', 'status', 'channel', 'total_price', 'created_at'])
                ->map(fn (Order $order) => [
                    'uuid' => (string) $order->uuid,
                    'short_code' => $order->short_code,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'channel' => $order->channel->value,
                    'marketplace' => in_array($order->channel->value, PlatformStats::marketplaceChannels(), true),
                    'total' => Money::of($order->total_price)->toString(),
                    'created_at' => $this->iso($order->created_at),
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function complaints(Store $store): array
    {
        $query = Complaint::query()->whereHas('order', fn ($order) => $order->where('store_id', $store->id));

        return [
            'open' => (clone $query)->where('status', '!=', 'closed')->count(),
            'last_30_days' => (clone $query)->where('created_at', '>=', now()->subDays(30))->count(),
            'items' => (clone $query)->with('order:id,uuid,short_code')->orderByDesc('id')->limit(10)->get()
                ->map(fn (Complaint $complaint) => [
                    'uuid' => (string) $complaint->uuid,
                    'type' => (string) $complaint->type,
                    'status' => (string) $complaint->status,
                    'description' => mb_substr((string) $complaint->description, 0, 200),
                    'order' => $complaint->order ? ['uuid' => (string) $complaint->order->uuid, 'short_code' => $complaint->order->short_code] : null,
                    'created_at' => $this->iso($complaint->created_at),
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function crm(?Store $store, ?StoreApplication $application): array
    {
        $scope = function ($query) use ($store, $application) {
            if ($store !== null) {
                $query->where('store_id', $store->id);
            } else {
                $query->where('application_id', $application?->id);
            }
        };

        $notes = StoreNote::query()->with('author:id,name')->where($scope)->orderByDesc('id')->limit(30)->get();
        $open = StoreTask::query()->where($scope)->whereNull('done_at')->orderBy('due_on')->orderBy('id')->limit(50)->get();
        $done = StoreTask::query()->where($scope)->whereNotNull('done_at')->orderByDesc('done_at')->limit(10)->get();
        $profile = $store !== null ? StoreCrmProfile::query()->find($store->id) : null;

        return [
            'next_contact_on' => $profile?->next_contact_on?->format('Y-m-d'),
            'notes' => $notes->map(fn (StoreNote $note) => $this->note($note))->values()->all(),
            'tasks' => [...$open->map(fn (StoreTask $task) => $this->task($task))->all(), ...$done->map(fn (StoreTask $task) => $this->task($task))->all()],
        ];
    }

    /**
     * The last 50 audit entries of the store: its own staff's, the team's about it.
     *
     * @return list<array<string, mixed>>
     */
    private function audit(Store $store): array
    {
        return AuditLog::query()->with('user:id,name,role,platform_role')
            ->where(fn ($query) => $query->where('store_id', $store->id)
                ->orWhere(fn ($subject) => $subject->where('model_type', $store->getMorphClass())->where('model_id', $store->id)))
            ->orderByDesc('id')->limit(50)->get()
            ->map(fn (AuditLog $log) => $this->auditEntry($log))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditEntry(AuditLog $log): array
    {
        $meta = $log->payload_json;

        return [
            'id' => (int) $log->id,
            'action' => $log->action,
            'actor' => $log->user ? [
                'id' => (int) $log->user->id,
                'name' => (string) $log->user->name,
                'team' => $log->user->platform_role !== null,
            ] : null,
            'reason' => is_array($meta) && is_string($meta['reason'] ?? null) ? $meta['reason'] : null,
            'before' => $log->before,
            'after' => $log->after,
            'created_at' => $this->iso($log->created_at),
        ];
    }

    private function iso(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(PlatformStats::TIMEZONE)->toIso8601String();
    }

    /**
     * Levels as the list filter counts them.
     *
     * @return list<string>
     */
    public static function healthLevels(): array
    {
        return StoreHealthLevel::values();
    }
}
