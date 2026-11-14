<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Banquet\BanquetCatalog;
use App\Domain\Pos\Banquet\BanquetEventService;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetEventType;
use App\Domain\Pos\Banquet\BanquetPresenter;
use App\Domain\Pos\Banquet\BanquetSettings;
use App\Domain\Pos\Banquet\BanquetSlot;
use App\Domain\Pos\Booking\UzPhone;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\BanquetEvent;
use App\Models\BanquetExtra;
use App\Models\BanquetPackage;
use App\Models\Branch;
use App\Models\Hall;
use App\Models\Store;
use App\Support\StoreTimezone;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P6-51/P6-52: the banquet calendar and events for the till and the web panel (docs/API.md "Tadbirlar"). A terminal
 * PIN session or a normal staff sign-in; reading with banquets.manage or pos.take_payment (the cashier takes the
 * scheduled payments, P6-53), every change with banquets.manage. Everything in the caller's store, narrowed to the
 * branch they act in; another store's or branch's event: 404.
 */
#[Group('POS: banquets', "Tadbirlar taqvimi: zal × sana × vaqt, tadbir, paket va narx, muzlatish (P6-51, P6-52).")]
class BanquetController extends MenuStaffController
{
    public const MAX_CALENDAR_DAYS = 62;

    /**
     * Banquet calendar (halls × days × slots)
     *
     * The branch's halls (with their rent per slot) and the events between `from` and `to` (the store's dates, `to`
     * inclusive, at most 62 days; default this month). An event merging halls is listed under each of them. Cancelled
     * events only with `include_cancelled=1`. `slots`: the store's slot times.
     */
    #[QueryParameter('branch_id', 'The branch (default: the one the caller works in).', type: 'int')]
    #[QueryParameter('from', 'First day, Y-m-d (store time zone).', type: 'string')]
    #[QueryParameter('to', 'Last day, Y-m-d, inclusive.', type: 'string')]
    #[QueryParameter('include_cancelled', 'Also cancelled events.', type: 'bool')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{branch_id: int, from: string, to: string, timezone: string, slots: array{morning: array{start: string, end: string}, day: array{start: string, end: string}, evening: array{start: string, end: string}}, halls: list<array{id: int, name: string, is_active: bool, events: list<' . BanquetPresenter::SUMMARY . '>}>}}')]
    public function calendar(Request $request): JsonResponse
    {
        $branch = $this->readableBranch($request);
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'include_cancelled' => 'nullable|boolean',
        ], UzValidation::messages());
        [$tz, $from, $to] = $this->range($request, (int) $branch->store_id);
        $presenter = app(BanquetPresenter::class);

        $events = BanquetEvent::where('branch_id', $branch->id)
            ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            ->when(!$request->boolean('include_cancelled'), fn ($query) => $query->where('status', '!=', BanquetEventStatus::CANCELLED->value))
            ->with(['halls' => fn ($query) => $query->withTrashed(), 'store', 'payments'])
            ->orderBy('starts_at')
            ->limit(2000)
            ->get();

        $byHall = [];
        foreach ($events as $event) {
            $summary = $presenter->summary($event);
            foreach ($summary['hall_ids'] as $hallId) {
                $byHall[$hallId][] = $summary;
            }
        }
        $halls = Hall::where('branch_id', $branch->id)->orderBy('sort_order')->orderBy('id')->get()->map(fn (Hall $hall) => [
            'id' => (int) $hall->id,
            'name' => (string) $hall->name,
            'is_active' => (bool) $hall->is_active,
            'events' => $byHall[$hall->id] ?? [],
        ])->values()->all();

        return $this->success([
            'branch_id' => (int) $branch->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'timezone' => $tz,
            'slots' => app(BanquetSettings::class)->view((int) $branch->store_id)['slots'],
            'halls' => $halls,
        ]);
    }

    /**
     * Banquet events (list, search by phone)
     *
     * Without `phone`: the events between `from` and `to` (default this month), by start. With `phone` (at least 4
     * digits): the customer's events across dates, newest first. Filter by `status`. At most 200.
     */
    #[QueryParameter('branch_id', 'One branch (default: the caller\'s; the whole store for store-wide staff).', type: 'int')]
    #[QueryParameter('phone', 'Search by the customer phone (digits, at least 4).', type: 'string')]
    #[QueryParameter('status', 'An App\Domain\Pos\Banquet\BanquetEventStatus value.', type: 'string')]
    #[QueryParameter('from', 'First day, Y-m-d (store time zone).', type: 'string')]
    #[QueryParameter('to', 'Last day, Y-m-d, inclusive.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{events: list<' . BanquetPresenter::SUMMARY . '>}}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => UzValidation::rowId(),
            'phone' => 'nullable|string|max:32',
            'status' => ['nullable', 'string', Rule::enum(BanquetEventStatus::class)],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ], UzValidation::messages());

        $storeId = $this->storeId($request);
        $branch = $request->filled('branch_id') || $this->permissions->actingBranchId($request->user()) !== null
            ? $this->readableBranch($request)
            : null;
        if ($branch === null && !$this->mayRead($request, null)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        $query = BanquetEvent::where('store_id', $storeId)
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->with(['halls' => fn ($query) => $query->withTrashed(), 'store', 'payments']);

        if ($request->filled('phone')) {
            $digits = UzPhone::searchDigits($request->string('phone')->toString());
            if ($digits === null) {
                throw ValidationException::withMessages(['phone' => 'Kamida 4 ta raqam kiriting.']);
            }
            $query->where('customer_phone', 'like', '%' . $digits . '%')->orderByDesc('starts_at');
        } else {
            [, $from, $to] = $this->range($request, $storeId);
            $query->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])->orderBy('starts_at');
        }
        $presenter = app(BanquetPresenter::class);

        return $this->success([
            'events' => $query->limit(200)->get()->map(fn (BanquetEvent $event) => $presenter->summary($event))->values()->all(),
        ]);
    }

    /**
     * Banquet catalogue for the event editor
     *
     * The store's active packages and extras and the branch's halls with their rent per slot, plus the settings
     * (slot times, hold hours, warnings).
     */
    #[QueryParameter('branch_id', 'The branch (default: the one the caller works in).', type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{packages: list<' . BanquetCatalog::PACKAGE . '>, extras: list<' . BanquetCatalog::EXTRA . '>, halls: list<' . BanquetCatalog::HALL . '>, settings: ' . BanquetSettings::VIEW . ', event_types: list<array{value: string, label: string}>}}')]
    public function catalog(Request $request): JsonResponse
    {
        $branch = $this->readableBranch($request);
        $catalog = app(BanquetCatalog::class);
        $storeId = (int) $branch->store_id;

        return $this->success([
            'packages' => BanquetPackage::where('store_id', $storeId)->where('is_active', true)->with('items')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (BanquetPackage $package) => $catalog->packageView($package))->values()->all(),
            'extras' => BanquetExtra::where('store_id', $storeId)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (BanquetExtra $extra) => $catalog->extraView($extra))->values()->all(),
            'halls' => $catalog->halls($storeId, (int) $branch->id),
            'settings' => app(BanquetSettings::class)->view($storeId),
            'event_types' => array_map(fn (BanquetEventType $type) => ['value' => $type->value, 'label' => $type->label()], BanquetEventType::cases()),
        ]);
    }

    /**
     * Hold halls for an event
     *
     * One or several merged halls (`hall_ids`) of the branch on `event_date` in a `slot` (morning, day, evening with
     * the store's times; custom with `start_time`/`end_time` "HH:MM", an end before the start is the next day). The
     * event starts as a hold until `hold_expires_at` (released by itself unless the deposit is paid, P6-53). Priced
     * from the catalogue: package × max(guests_guaranteed, guests_minimum) + the halls' rent for the slot + `extras`
     * (a catalogue `extra_id` or a free `name` + `unit_price` + `per_person`; `quantity`). `guests_minimum` defaults to
     * the package's minimum. 409 `slot_taken` (`data.conflicts`) when a hall is held then; 409 `hall_has_bookings`
     * when a room inside a hall is booked then. Money in so'm; `quote_currency`/`quote_rate` (USD and the day's rate)
     * only for display.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Optional: the same key when retrying the request returns the first answer.', type: 'string')]
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . BanquetPresenter::DETAIL . '}')]
    #[ApiDocResponse(409, 'A hall is taken at that time', type: 'array{success: false, message: string, data: array{reason: string, conflicts: list<array{uuid: string, status: string, event_date: string, slot: string, starts_at: string, ends_at: string, hall_ids: list<int>}>}}')]
    public function store(Request $request): JsonResponse
    {
        $branch = $this->branch($request, Permission::BANQUETS_MANAGE);
        $data = $request->validate([
            'branch_id' => UzValidation::rowId(),
            'event_date' => 'required|date_format:Y-m-d',
            'slot' => ['required', 'string', Rule::enum(BanquetSlot::class)],
            'start_time' => ['nullable', 'required_if:slot,custom', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:slot,custom', 'date_format:H:i'],
            'hall_ids' => 'required|array|min:1|max:10',
            'hall_ids.*' => UzValidation::rowId(true),
            'event_type' => ['nullable', 'string', Rule::enum(BanquetEventType::class)],
            'title' => 'nullable|string|max:160',
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'nullable|string|max:32',
            'guests_guaranteed' => 'required|integer|min:0|max:10000',
            'guests_minimum' => 'nullable|integer|min:0|max:10000',
            'banquet_package_id' => UzValidation::rowId(),
            'extras' => 'nullable|array|max:50',
            'extras.*.extra_id' => UzValidation::rowId(),
            'extras.*.name' => 'nullable|required_without:extras.*.extra_id|string|max:255',
            'extras.*.unit_price' => 'nullable|required_without:extras.*.extra_id|numeric|min:0|max:9999999999|decimal:0,2',
            'extras.*.per_person' => 'nullable|boolean',
            'extras.*.quantity' => 'nullable|numeric|min:0.001|max:100000|decimal:0,3',
            'extras.*.note' => 'nullable|string|max:500',
            'quote_currency' => 'nullable|string|size:3|alpha|uppercase',
            'quote_rate' => 'nullable|required_with:quote_currency|numeric|min:0.0001|max:9999999999|decimal:0,4',
            'note' => 'nullable|string|max:2000',
        ], UzValidation::messages());

        $data = $this->normalized($data);
        $event = app(BanquetEventService::class)->create($branch, $data, $request->user());

        return $this->success(app(BanquetPresenter::class)->detail($event->refresh()), 'Zal band qilindi.', 201);
    }

    /**
     * A banquet event
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPresenter::DETAIL . '}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        return $this->success(app(BanquetPresenter::class)->detail($this->event($request, $uuid, false)));
    }

    /**
     * Change or move an event
     *
     * Only the fields sent change (a hold or a confirmed event). Moving it (`event_date`, `slot`, `start_time`/
     * `end_time`, `hall_ids`) keeps its payments and is checked for conflicts again (409 `slot_taken`,
     * `hall_has_bookings`). `extras` replaces the event's extras (a line kept by its `line_uuid` or catalogue
     * `extra_id`). On a frozen event the package cannot change (409 `event_frozen`); guests and extras can (frozen
     * prices, new lines at today's price). `version`: the one read (409 `stale_version`).
     */
    #[HeaderParameter('Idempotency-Key', description: 'Optional: the same key when retrying the request returns the first answer.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPresenter::DETAIL . '}')]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $event = $this->event($request, $uuid, true);
        $data = $request->validate([
            'event_date' => 'sometimes|date_format:Y-m-d',
            'slot' => ['sometimes', 'string', Rule::enum(BanquetSlot::class)],
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i',
            'hall_ids' => 'sometimes|array|min:1|max:10',
            'hall_ids.*' => UzValidation::rowId(true),
            'event_type' => ['sometimes', 'string', Rule::enum(BanquetEventType::class)],
            'title' => 'sometimes|nullable|string|max:160',
            'customer_name' => 'sometimes|string|max:120',
            'customer_phone' => 'sometimes|nullable|string|max:32',
            'guests_guaranteed' => 'sometimes|integer|min:0|max:10000',
            'guests_minimum' => 'sometimes|integer|min:0|max:10000',
            'banquet_package_id' => 'sometimes|nullable|integer|min:1|max:999999999999999999',
            'extras' => 'sometimes|nullable|array|max:50',
            'extras.*.line_uuid' => 'nullable|uuid',
            'extras.*.extra_id' => UzValidation::rowId(),
            'extras.*.name' => 'nullable|string|max:255',
            'extras.*.unit_price' => 'nullable|numeric|min:0|max:9999999999|decimal:0,2',
            'extras.*.per_person' => 'nullable|boolean',
            'extras.*.quantity' => 'nullable|numeric|min:0.001|max:100000|decimal:0,3',
            'extras.*.note' => 'nullable|string|max:500',
            'quote_currency' => 'sometimes|nullable|string|size:3|alpha|uppercase',
            'quote_rate' => 'sometimes|nullable|numeric|min:0.0001|max:9999999999|decimal:0,4',
            'note' => 'sometimes|nullable|string|max:2000',
            'version' => 'nullable|integer|min:1',
        ], UzValidation::messages());
        unset($data['version']);

        $event = app(BanquetEventService::class)->update($event, $this->normalized($data), $request->user(), $this->version($request));

        return $this->success(app(BanquetPresenter::class)->detail($event->refresh()), "Tadbir o'zgartirildi.");
    }

    /**
     * Freeze the prices (contract day)
     *
     * Stamps the current prices into the event's lines and the package snapshot; later catalogue edits do not change
     * the event. 409 `already_frozen`. Optional `version`.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Optional: the same key when retrying the request returns the first answer.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPresenter::DETAIL . '}')]
    public function freeze(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['version' => 'nullable|integer|min:1'], UzValidation::messages());
        $event = app(BanquetEventService::class)->freeze($this->event($request, $uuid, true), $request->user(), $this->version($request));

        return $this->success(app(BanquetPresenter::class)->detail($event->refresh()), 'Narxlar muzlatildi.');
    }

    /**
     * Unfreeze the prices
     *
     * banquets.manage and a `reason` (audited): the event is priced from the catalogue again. 409 `not_frozen`.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Optional: the same key when retrying the request returns the first answer.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPresenter::DETAIL . '}')]
    public function unfreeze(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:500', 'version' => 'nullable|integer|min:1'], UzValidation::messages());
        $event = app(BanquetEventService::class)->unfreeze($this->event($request, $uuid, true), $data['reason'], $request->user(), $this->version($request));

        return $this->success(app(BanquetPresenter::class)->detail($event->refresh()), 'Muzlatish bekor qilindi.');
    }

    /**
     * The caller's event: their store and the branch they act in (another one: 404, like a missing one).
     */
    private function event(Request $request, string $uuid, bool $write): BanquetEvent
    {
        $acting = $this->permissions->actingBranchId($request->user());
        $event = BanquetEvent::where('store_id', $this->storeId($request))
            ->where('uuid', $uuid)
            ->when($acting !== null, fn ($query) => $query->where('branch_id', $acting))
            ->first();
        if ($event === null) {
            throw new HttpResponseException($this->error('Tadbir topilmadi.', [], 404));
        }
        if ($write) {
            $this->authorizeFor($request, Permission::BANQUETS_MANAGE, $event);
        } elseif (!$this->mayRead($request, $event)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $event;
    }

    /**
     * Reading: banquets.manage or pos.take_payment on the branch.
     */
    private function readableBranch(Request $request): Branch
    {
        $user = $request->user();
        $branchId = $request->input('branch_id');
        if (($branchId === null || $branchId === '') && ($acting = $this->permissions->actingBranchId($user)) !== null) {
            $branchId = $acting;
        }
        if ($branchId === null || $branchId === '' || filter_var($branchId, FILTER_VALIDATE_INT) === false) {
            throw new HttpResponseException($this->error('Filialni tanlang (branch_id).', ['errors' => ['branch_id' => ['Filialni tanlang.']]], 422));
        }
        $branch = Branch::where('store_id', $this->storeId($request))->find((int) $branchId);
        if ($branch === null) {
            throw new HttpResponseException($this->error('Filial topilmadi.', [], 404));
        }
        if (!$this->mayRead($request, $branch)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $branch;
    }

    private function mayRead(Request $request, mixed $subject): bool
    {
        $user = $request->user();

        return $this->permissions->allows($user, Permission::BANQUETS_MANAGE, $subject)
            || $this->permissions->allows($user, Permission::POS_TAKE_PAYMENT, $subject);
    }

    private function version(Request $request): ?int
    {
        $version = $request->input('version');

        return is_numeric($version) ? (int) $version : null;
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon} time zone, first and last day (store time zone)
     */
    private function range(Request $request, int $storeId): array
    {
        $tz = StoreTimezone::of(Store::find($storeId));
        $from = $request->filled('from') ? Carbon::createFromFormat('Y-m-d', (string) $request->input('from'), $tz)->startOfDay() : Carbon::now($tz)->startOfMonth();
        $to = $request->filled('to') ? Carbon::createFromFormat('Y-m-d', (string) $request->input('to'), $tz)->startOfDay() : $from->copy()->endOfMonth()->startOfDay();
        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['to' => "Oxirgi kun birinchi kundan oldin bo'lmasin."]);
        }
        if ($from->diffInDays($to) + 1 > self::MAX_CALENDAR_DAYS) {
            throw ValidationException::withMessages(['to' => "Ko'pi bilan " . self::MAX_CALENDAR_DAYS . ' kunlik oraliq.']);
        }

        return [$tz, $from, $to];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data): array
    {
        unset($data['branch_id']);
        if (array_key_exists('customer_phone', $data)) {
            $phone = $data['customer_phone'];
            if ($phone !== null && trim((string) $phone) !== '') {
                $phone = UzPhone::normalize((string) $phone);
                if ($phone === null) {
                    throw ValidationException::withMessages(['customer_phone' => "Telefon raqami noto'g'ri: +998XXXXXXXXX yoki 9 xonali raqam."]);
                }
            } else {
                $phone = null;
            }
            $data['customer_phone'] = $phone;
        }
        foreach (['customer_name', 'title', 'note'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        if (array_key_exists('extras', $data)) {
            $data['extras'] = array_map(fn (array $extra) => array_filter([
                'line_uuid' => $extra['line_uuid'] ?? null,
                'extra_id' => isset($extra['extra_id']) ? (int) $extra['extra_id'] : null,
                'name' => $extra['name'] ?? null,
                'unit_price' => isset($extra['unit_price']) ? (string) $extra['unit_price'] : null,
                'per_person' => isset($extra['per_person']) ? (bool) $extra['per_person'] : null,
                'quantity' => isset($extra['quantity']) ? (string) $extra['quantity'] : null,
                'note' => $extra['note'] ?? null,
            ], fn ($value) => $value !== null), array_values($data['extras'] ?? []));
        }

        return $data;
    }
}
