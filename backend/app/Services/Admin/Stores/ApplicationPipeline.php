<?php

namespace App\Services\Admin\Stores;

use App\Domain\Fiscal\FiscalSettings;
use App\Enums\DeviceType;
use App\Enums\StoreApplicationStage;
use App\Enums\StoreFormat;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\User;
use App\Services\Admin\Today\PlatformStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P4-22: store applications and the onboarding pipeline (Ovqatxonalar → Ulash jarayoni, docs/PHASE4_PLAN.md §4.3).
 *
 *  - submit: the public form (POST /api/v1/store-applications). The same phone within 24 hours updates nothing and
 *    answers the application it already left (no duplicates from double clicks or bots);
 *  - stages: forward only with the required checklist items of every stage passed done (ticked by hand, or detected
 *    from the store: products, a paired till or KDS, fiscal mode, the owner signed in, a first order); `menu` and
 *    later need the store (convert); `active` activates the store (status active, visible); backward is free except
 *    from `active`; a rejected application reopens at `application` or `review` only;
 *  - entering a stage sets its due date (today + the stage's default days, or what the member gives); an open
 *    application past its due date is overdue ("Diqqat talab qiladi");
 *  - convert: creates the store through StoreProvisioner (the Filament wizard's path) as `pending` and hidden, with
 *    the application's name, owner and phone; the stage does not move by itself;
 *  - reject: with a reason; a converted application's store becomes `rejected` and hidden.
 */
class ApplicationPipeline
{
    public function __construct(
        private readonly StoreProvisioner $provisioner,
        private readonly StoreActions $actions,
    ) {}

    /**
     * @param  array{name: string, format: string, region_id?: int|null, city?: string|null, owner_name: string, phone: string, tin?: string|null, comment?: string|null}  $data
     */
    public function submit(array $data, string $source, ?string $ip): StoreApplication
    {
        $phone = (string) self::normalizePhone($data['phone']);
        $recent = StoreApplication::query()
            ->where('phone', $phone)
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->first();
        if ($recent !== null) {
            return $recent;
        }

        return $this->create($data, $source, null, $ip);
    }

    /**
     * @param  array{name: string, format: string, region_id?: int|null, city?: string|null, owner_name: string, phone: string, tin?: string|null, comment?: string|null, assignee_id?: int|null, due_on?: string|null}  $data
     */
    public function create(array $data, string $source, ?User $member, ?string $ip = null): StoreApplication
    {
        $now = PlatformStats::now();

        return StoreApplication::create([
            'name' => trim($data['name']),
            'format' => StoreFormat::from($data['format'])->value,
            'region_id' => $data['region_id'] ?? null,
            'city' => isset($data['city']) ? trim((string) $data['city']) : null,
            'owner_name' => trim($data['owner_name']),
            'phone' => self::normalizePhone($data['phone']),
            'tin' => $data['tin'] ?? null,
            'comment' => isset($data['comment']) ? trim((string) $data['comment']) : null,
            'source' => $source,
            'stage' => StoreApplicationStage::APPLICATION->value,
            'stage_changed_at' => $now,
            'assignee_id' => $data['assignee_id'] ?? null,
            'due_on' => $data['due_on'] ?? self::dueOn(StoreApplicationStage::APPLICATION, $now),
            'created_by' => $member?->id,
            'ip' => $ip,
        ]);
    }

    /**
     * Edit the application's fields, assignee or due date. Returns what changed (before, after).
     *
     * @param  array<string, mixed>  $fields
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function update(StoreApplication $application, array $fields): array
    {
        if (array_key_exists('phone', $fields) && $fields['phone'] !== null) {
            $fields['phone'] = self::normalizePhone((string) $fields['phone']);
        }

        $application->fill($fields);
        $dirty = array_keys($application->getDirty());
        $before = [];
        foreach ($dirty as $key) {
            $before[$key] = $this->auditValue($application->getOriginal($key));
        }
        $application->save();

        $after = [];
        foreach ($dirty as $key) {
            $after[$key] = $this->auditValue($application->getAttribute($key));
        }

        return ['before' => $before, 'after' => $after];
    }

    /**
     * @throws StoreAdminException
     */
    public function move(StoreApplication $application, StoreApplicationStage $to, User $member, ?string $note = null, ?string $dueOn = null): StoreApplication
    {
        return DB::transaction(function () use ($application, $to, $member, $note, $dueOn) {
            $application = StoreApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $from = $application->stage;

            if ($to === StoreApplicationStage::REJECTED) {
                throw new StoreAdminException('Rad etish uchun «Rad etish» amalidan foydalaning (sabab bilan).', 'use_reject');
            }
            if ($from === $to) {
                throw new StoreAdminException("Ariza allaqachon «{$to->label()}» bosqichida.", 'unchanged', 409);
            }
            if ($from === StoreApplicationStage::ACTIVE) {
                throw new StoreAdminException("Faol ovqatxonani ulash jarayoniga qaytarib bo'lmaydi: kerak bo'lsa, ovqatxonani to'xtating.", 'already_active');
            }
            if ($from === StoreApplicationStage::REJECTED && !in_array($to, [StoreApplicationStage::APPLICATION, StoreApplicationStage::REVIEW], true)) {
                throw new StoreAdminException("Rad etilgan ariza faqat «Ariza» yoki «Tekshiruv» bosqichida qayta ochiladi.", 'reopen_only');
            }
            if ($to->needsStore() && $application->store_id === null) {
                throw new StoreAdminException("«{$to->label()}» bosqichi uchun avval ovqatxonani yarating (arizadan ovqatxona yaratish).", 'store_required');
            }

            if ($from !== StoreApplicationStage::REJECTED && $to->position() > $from->position()) {
                $store = $application->store;
                $detected = $this->detected($store);
                foreach (StoreApplicationStage::pipeline() as $stage) {
                    if ($stage->position() < $from->position() || $stage->position() >= $to->position()) {
                        continue;
                    }
                    $missing = $this->missing($application, $stage, $detected);
                    if ($missing !== []) {
                        throw new StoreAdminException(
                            "«{$stage->label()}» bosqichining ro'yxati tugallanmagan: " . implode(', ', array_column($missing, 'label')) . '.',
                            'checklist_incomplete',
                            422,
                            ['stage' => $stage->value, 'missing' => array_column($missing, 'key')],
                        );
                    }
                }
            }

            $now = PlatformStats::now();
            $update = [
                'stage' => $to->value,
                'stage_changed_at' => $now,
                'due_on' => $dueOn ?? self::dueOn($to, $now),
            ];
            if ($from === StoreApplicationStage::REJECTED) {
                $update['rejected_at'] = null;
                $update['rejected_reason'] = null;
            }

            if ($to === StoreApplicationStage::ACTIVE) {
                /** @var Store $store */
                $store = $application->store;
                $this->actions->activate($store, $member, 'Ulash jarayoni yakunlandi' . ($note !== null && trim($note) !== '' ? ': ' . trim($note) : ''), true);
                $update['activated_at'] = $now;
                $update['due_on'] = null;
            }

            $application->forceFill($update)->save();

            return $application->refresh();
        });
    }

    /**
     * Tick (or untick) a checklist item of any stage by hand.
     *
     * @throws StoreAdminException
     */
    public function tick(StoreApplication $application, string $item, bool $done, User $member): StoreApplication
    {
        $known = false;
        foreach (StoreApplicationStage::pipeline() as $stage) {
            if (in_array($item, array_column($stage->checklist(), 'key'), true)) {
                $known = true;
            }
        }
        if (!$known) {
            throw new StoreAdminException("Bunday ro'yxat bandi yo'q.", 'unknown_item');
        }

        return DB::transaction(function () use ($application, $item, $done, $member) {
            $application = StoreApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $checklist = $application->checklist ?? [];
            if ($done) {
                $checklist[$item] = ['done_at' => PlatformStats::now()->toIso8601String(), 'by' => (int) $member->id];
            } else {
                unset($checklist[$item]);
            }
            $application->forceFill(['checklist' => $checklist === [] ? null : $checklist])->save();

            return $application;
        });
    }

    /**
     * Create the application's store (StoreProvisioner): pending and hidden until the `faol` stage.
     *
     * @param  array<string, mixed>  $data  the store fields (StoreProvisioner::create), the application's name, owner and phone by default
     * @return array{application: StoreApplication, store: Store, invite_link: string}
     *
     * @throws StoreAdminException
     */
    public function convert(StoreApplication $application, array $data): array
    {
        return DB::transaction(function () use ($application, $data) {
            $application = StoreApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->store_id !== null) {
                throw new StoreAdminException('Bu arizadan ovqatxona allaqachon yaratilgan.', 'already_converted', 409, ['store_id' => (int) $application->store_id]);
            }
            if (!$application->stage->isOpen()) {
                throw new StoreAdminException("Yopilgan arizadan ovqatxona yaratib bo'lmaydi.", 'application_closed');
            }

            $created = $this->provisioner->create([
                'name' => $data['name'] ?? $application->name,
                'phone' => $data['phone'] ?? null,
                'commission_rate' => $data['commission_rate'] ?? null,
                'payment_account_type' => $data['payment_account_type'] ?? null,
                'status' => StoreStatus::PENDING,
                'is_active' => false,
                'tin' => $data['tin'] ?? $application->tin,
                'legal_name' => $data['legal_name'] ?? null,
                'owner_name' => $data['owner_name'] ?? $application->owner_name,
                'owner_phone' => $data['owner_phone'] ?? $application->phone,
                'branch_region_id' => (int) ($data['branch_region_id'] ?? $application->region_id),
                'branch_district_id' => (int) $data['branch_district_id'],
                'branch_address' => (string) $data['branch_address'],
                'branch_phone' => $data['branch_phone'] ?? null,
                'branch_lat' => $data['branch_lat'],
                'branch_lng' => $data['branch_lng'],
                'branch_radius' => $data['branch_radius'] ?? 5,
                'branch_open_time' => $data['branch_open_time'] ?? null,
                'branch_close_time' => $data['branch_close_time'] ?? null,
            ], "Boshqaruv markazi, ariza #{$application->id}");

            $application->forceFill(['store_id' => $created['store']->id, 'converted_at' => PlatformStats::now()])->save();

            return ['application' => $application->refresh(), 'store' => $created['store'], 'invite_link' => $created['invite_link']];
        });
    }

    /**
     * @throws StoreAdminException
     */
    public function reject(StoreApplication $application, User $member, string $reason): StoreApplication
    {
        return DB::transaction(function () use ($application, $member, $reason) {
            $application = StoreApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->stage === StoreApplicationStage::REJECTED) {
                throw new StoreAdminException('Ariza allaqachon rad etilgan.', 'already_rejected', 409);
            }
            if ($application->stage === StoreApplicationStage::ACTIVE) {
                throw new StoreAdminException("Faol ovqatxonaning arizasini rad etib bo'lmaydi: kerak bo'lsa, ovqatxonani to'xtating.", 'already_active');
            }

            $store = $application->store;
            if ($store !== null && $store->status !== StoreStatus::REJECTED) {
                $this->actions->setStatus($store, StoreStatus::REJECTED, false, $member, 'Ariza rad etildi: ' . $reason);
            }

            $application->forceFill([
                'stage' => StoreApplicationStage::REJECTED->value,
                'stage_changed_at' => PlatformStats::now(),
                'due_on' => null,
                'rejected_reason' => trim($reason),
                'rejected_at' => PlatformStats::now(),
            ])->save();

            return $application->refresh();
        });
    }

    /**
     * Every stage's checklist with each item's state.
     *
     * @return list<array{stage: string, label: string, current: bool, passed: bool, items: list<array{key: string, label: string, required: bool, detect: bool, done: bool, ticked: bool, detected: bool, done_at: string|null, done_by: int|null}>}>
     */
    public function checklist(StoreApplication $application): array
    {
        $detected = $this->detected($application->store);
        $ticked = $application->checklist ?? [];
        $current = $application->stage;
        $result = [];

        foreach (StoreApplicationStage::pipeline() as $stage) {
            if ($stage->checklist() === []) {
                continue;
            }
            $items = [];
            foreach ($stage->checklist() as $item) {
                $tick = $ticked[$item['key']] ?? null;
                $isDetected = $item['detect'] && ($detected[$item['key']] ?? false);
                $items[] = [
                    ...$item,
                    'done' => $tick !== null || $isDetected,
                    'ticked' => $tick !== null,
                    'detected' => $isDetected,
                    'done_at' => is_array($tick) ? (string) $tick['done_at'] : null,
                    'done_by' => is_array($tick) && isset($tick['by']) ? (int) $tick['by'] : null,
                ];
            }
            $result[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'current' => $stage === $current,
                'passed' => $current !== StoreApplicationStage::REJECTED && $current->position() > $stage->position(),
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * Whether an open application is past its stage's due date (Asia/Tashkent).
     */
    public static function isOverdue(StoreApplication $application, ?CarbonImmutable $now = null): bool
    {
        $today = PlatformStats::dayStart($now ?? PlatformStats::now())->format('Y-m-d');

        return $application->stage->isOpen() && $application->due_on !== null && $application->due_on->format('Y-m-d') < $today;
    }

    /**
     * A Uzbek mobile number as +998 and nine digits (spaces, dashes, brackets and a missing +998 allowed); null when
     * it is not one.
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        }

        return preg_match('/^998\d{9}$/', $digits) ? '+' . $digits : null;
    }

    public static function dueOn(StoreApplicationStage $stage, CarbonImmutable $now): ?string
    {
        $days = $stage->defaultDays();

        return $days !== null ? PlatformStats::dayStart($now)->addDays($days)->format('Y-m-d') : null;
    }

    /**
     * @param  array<string, bool>  $detected
     * @return list<array{key: string, label: string, required: bool, detect: bool}>
     */
    private function missing(StoreApplication $application, StoreApplicationStage $stage, array $detected): array
    {
        $ticked = $application->checklist ?? [];

        return array_values(array_filter($stage->checklist(), fn (array $item) => $item['required']
            && !isset($ticked[$item['key']])
            && !($item['detect'] && ($detected[$item['key']] ?? false))));
    }

    /**
     * What the store already shows, for the `detect` items.
     *
     * @return array<string, bool>
     */
    private function detected(?Store $store): array
    {
        if ($store === null) {
            return [];
        }

        $terminals = DB::table('devices')->where('store_id', $store->id)->whereNull('revoked_at')
            ->whereIn('type', [DeviceType::POS->value, DeviceType::KDS->value])
            ->pluck('type')->all();
        $staff = User::query()->where('store_id', $store->id)->whereIn('role', [UserRole::OWNER->value, UserRole::MANAGER->value]);

        return [
            'menu_entered' => DB::table('products')->where('store_id', $store->id)->whereNull('deleted_at')->exists(),
            'pos_paired' => in_array(DeviceType::POS->value, $terminals, true),
            'kds_paired' => in_array(DeviceType::KDS->value, $terminals, true),
            'fiscal' => FiscalSettings::of($store)->enabled(),
            'owner_panel' => DB::table('personal_access_tokens')
                ->where('tokenable_type', (new User())->getMorphClass())
                ->whereIn('tokenable_id', (clone $staff)->select('id'))
                ->exists(),
            'first_order' => DB::table('orders')->where('store_id', $store->id)->whereNull('deleted_at')
                ->whereNotIn('status', ['canceled', 'rejected'])->exists(),
        ];
    }

    private function auditValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    }
}
