<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Billing\PosFiscalReceipt;
use App\Domain\Pos\Booking\BookingDepositService;
use App\Domain\Pos\Booking\BookingEventType;
use App\Domain\Pos\Booking\BookingItemService;
use App\Domain\Pos\Booking\BookingPresenter;
use App\Domain\Pos\Booking\BookingService;
use App\Domain\Pos\Booking\BookingSource;
use App\Domain\Pos\Booking\BookingStatus;
use App\Domain\Pos\Booking\DiningTables;
use App\Domain\Pos\Booking\UzPhone;
use App\Domain\Pos\Contracts\PosTender;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Auth\PermissionService;
use App\Support\Money;
use App\Support\RowId;
use App\Support\StoreTimezone;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P3-17: the booking book for the till and the web panel (docs/API.md "Bron va nasiya"): calendar, bookings with the
 * conflict check, states, pre-ordered dishes, deposits and "Keldi". A terminal PIN session or a normal staff sign-in
 * with bookings.manage; everything in the caller's store, narrowed to the branch they act in (a branch-bound staff
 * member, a PIN session: the terminal's branch). Another store's or another branch's booking: 404.
 */
#[Group('POS: bookings', "Bron daftari: taqvim, bron, oldindan buyurtma, avans va \"Keldi\" (P3-17).")]
class BookingController extends MenuStaffController
{
    public function __construct(
        PermissionService $permissions,
        protected BookingService $bookings,
        protected BookingItemService $items,
        protected BookingDepositService $deposits,
        protected BookingPresenter $presenter,
        protected DiningTables $tables,
    ) {
        parent::__construct($permissions);
    }

    /**
     * Booking calendar (rooms × bookings)
     *
     * The branch's rooms and tables (floor order), each with the bookings that occupy it between `from` and `to`
     * (dates in the store's time zone, `to` inclusive, at most 31 days; default today). A banquet merging rooms is
     * listed under each of them; bookings without a room are in `unassigned`. Canceled bookings only with
     * `include_canceled=1`.
     */
    #[QueryParameter('branch_id', 'The branch (default: the one the caller works in).', type: 'int')]
    #[QueryParameter('from', 'First day, Y-m-d (store time zone).', type: 'string')]
    #[QueryParameter('to', 'Last day, Y-m-d, inclusive.', type: 'string')]
    #[QueryParameter('include_canceled', 'Also canceled bookings.', type: 'bool')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{branch_id: int, from: string, to: string, timezone: string, rooms: list<array{id: int, name: string, kind: string, seats: int|null, hall_id: int, hall_name: string|null, is_active: bool, min_spend: string|null, bookings: list<' . BookingPresenter::SUMMARY . '>}>, unassigned: list<' . BookingPresenter::SUMMARY . '>}}')]
    public function calendar(Request $request): JsonResponse
    {
        $branch = $this->branch($request, Permission::BOOKINGS_MANAGE);
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'include_canceled' => 'nullable|boolean',
        ], UzValidation::messages());

        [$tz, $from, $to] = $this->range($request, (int) $branch->store_id);

        $bookings = Booking::where('branch_id', $branch->id)
            ->where('starts_at', '<', $to->copy()->addDay()->startOfDay()->utc())
            ->where('ends_at', '>', $from->copy()->startOfDay()->utc())
            ->when(!$request->boolean('include_canceled'), fn ($query) => $query->where('status', '!=', BookingStatus::CANCELED->value))
            ->with(['tables', 'payments'])
            ->withCount('items')
            ->orderBy('starts_at')
            ->limit(2000)
            ->get();

        $byTable = [];
        $unassigned = [];
        foreach ($bookings as $booking) {
            $summary = $this->presenter->summary($booking);
            if ($summary['table_ids'] === []) {
                $unassigned[] = $summary;
            }
            foreach ($summary['table_ids'] as $tableId) {
                $byTable[$tableId][] = $summary;
            }
        }

        $rooms = $this->tables->ofBranch((int) $branch->id)->values()->map(fn (object $table) => [
            'id' => $table->id,
            'name' => (string) $table->name,
            'kind' => (string) $table->kind,
            'seats' => $table->seats,
            'hall_id' => $table->hall_id,
            'hall_name' => $table->hall_name,
            'is_active' => $table->is_active,
            'min_spend' => $table->min_spend !== null ? Money::of($table->min_spend)->toString() : null,
            'bookings' => $byTable[$table->id] ?? [],
        ])->all();

        return $this->success([
            'branch_id' => (int) $branch->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'timezone' => $tz,
            'rooms' => $rooms,
            'unassigned' => $unassigned,
        ]);
    }

    /**
     * Bookings (list, search by phone)
     *
     * Without `phone`: the bookings of the branch(es) between `from` and `to` (default today), by start time. With
     * `phone` (at least 4 digits, any form: "90 123 45 67"): the guest's or group's history across dates, newest
     * first. Filter by `status`. At most 200.
     */
    #[QueryParameter('branch_id', 'One branch (default: the caller\'s; the whole store for store-wide staff).', type: 'int')]
    #[QueryParameter('phone', 'Search by the contact phone (digits, at least 4).', type: 'string')]
    #[QueryParameter('status', 'requested, confirmed, arrived, completed, canceled or no_show.', type: 'string')]
    #[QueryParameter('from', 'First day, Y-m-d (store time zone).', type: 'string')]
    #[QueryParameter('to', 'Last day, Y-m-d, inclusive.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{bookings: list<' . BookingPresenter::SUMMARY . '>}}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => UzValidation::rowId(),
            'phone' => 'nullable|string|max:32',
            'status' => ['nullable', 'string', Rule::in(BookingStatus::values())],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ], UzValidation::messages());

        $user = $request->user();
        $storeId = $this->storeId($request);
        $branch = $request->filled('branch_id') || $this->permissions->actingBranchId($user) !== null
            ? $this->branch($request, Permission::BOOKINGS_MANAGE)
            : null;

        $query = Booking::where('store_id', $storeId)
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->with(['tables', 'payments'])
            ->withCount('items');

        if ($request->filled('phone')) {
            $digits = UzPhone::searchDigits($request->string('phone')->toString());
            if ($digits === null) {
                throw ValidationException::withMessages(['phone' => "Kamida 4 ta raqam kiriting."]);
            }
            $query->where('contact_phone', 'like', '%' . $digits . '%')->orderByDesc('starts_at');
        } else {
            [, $from, $to] = $this->range($request, $storeId);
            $query->where('starts_at', '<', $to->copy()->addDay()->startOfDay()->utc())
                ->where('ends_at', '>', $from->copy()->startOfDay()->utc())
                ->orderBy('starts_at');
        }

        return $this->success([
            'bookings' => $query->limit(200)->get()->map(fn (Booking $booking) => $this->presenter->summary($booking))->values()->all(),
        ]);
    }

    /**
     * Create a booking
     *
     * A room or table of the branch (`dining_table_id`; a banquet adds the merged rooms in `extra_table_ids`) for
     * `starts_at`–`ends_at` (ISO 8601; without an offset: the store's time zone; without `ends_at`: the branch's default
     * slot, 5 hours). 409 `booking_conflict` with `data.conflicts` when a room is already booked then. `confirm: true`
     * creates it confirmed. The phone is stored as +998XXXXXXXXX.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    #[ApiDocResponse(409, 'A room is booked at that time', type: 'array{success: false, message: string, data: array{reason: string, conflicts: list<array{uuid: string, status: string, starts_at: string, ends_at: string, table_ids: list<int>}>}}')]
    public function store(Request $request): JsonResponse
    {
        $branch = $this->branch($request, Permission::BOOKINGS_MANAGE);
        $data = $this->normalized($request->validate([
            'branch_id' => UzValidation::rowId(),
            'dining_table_id' => UzValidation::rowId(),
            'extra_table_ids' => 'nullable|array|max:19',
            'extra_table_ids.*' => UzValidation::rowId(true),
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date',
            'guests_planned' => 'nullable|integer|min:1|max:1000',
            'event_type' => ['nullable', 'string', Rule::in(BookingEventType::values())],
            'contact_name' => 'required|string|max:120',
            'contact_phone' => 'nullable|string|max:32',
            'source' => ['nullable', 'string', Rule::in(BookingSource::values())],
            'min_spend' => 'nullable|numeric|min:0|max:999999999|decimal:0,2',
            'note' => 'nullable|string|max:1000',
            'confirm' => 'nullable|boolean',
            'version' => 'nullable|integer|min:1',
        ], UzValidation::messages()), $branch, true);

        $booking = $this->bookings->create($branch, $data, $request->user());

        return $this->success($this->presenter->detail($booking->refresh()), 'Bron saqlandi.', 201);
    }

    /**
     * A booking
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        return $this->success($this->presenter->detail($this->booking($request, $uuid)));
    }

    /**
     * Change a booking
     *
     * Only the fields sent change (a requested or confirmed booking). Moving it or changing its rooms is checked for
     * conflicts again (409). `version` (optional): the one read; another device changed it meanwhile: 409.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->booking($request, $uuid);
        $data = $this->normalized($request->validate([
            'dining_table_id' => UzValidation::rowId(),
            'extra_table_ids' => 'nullable|array|max:19',
            'extra_table_ids.*' => UzValidation::rowId(true),
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date',
            'guests_planned' => 'nullable|integer|min:1|max:1000',
            'event_type' => ['nullable', 'string', Rule::in(BookingEventType::values())],
            'contact_name' => 'sometimes|string|max:120',
            'contact_phone' => 'nullable|string|max:32',
            'source' => ['nullable', 'string', Rule::in(BookingSource::values())],
            'min_spend' => 'nullable|numeric|min:0|max:999999999|decimal:0,2',
            'note' => 'nullable|string|max:1000',
            'version' => 'nullable|integer|min:1',
        ], UzValidation::messages()), $booking->branch()->firstOrFail(), false);

        $booking = $this->bookings->update($booking, $data, $request->user(), $this->version($request));

        return $this->success($this->presenter->detail($booking->refresh()), "Bron o'zgartirildi.");
    }

    /**
     * Confirm a booking
     *
     * Optional `version` (the booking's, 409 when stale).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    public function confirm(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['version' => 'nullable|integer|min:1'], UzValidation::messages());
        $booking = $this->bookings->confirm($this->booking($request, $uuid), $request->user(), $this->version($request));

        return $this->success($this->presenter->detail($booking->refresh()), 'Bron tasdiqlandi.');
    }

    /**
     * Cancel a booking
     *
     * `reason` is required. Taken deposits stay: refund or retain them afterwards (`data.deposit_unsettled`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500', 'version' => 'nullable|integer|min:1'], UzValidation::messages());
        $booking = $this->bookings->cancel($this->booking($request, $uuid), $data['reason'], $request->user(), $this->version($request));

        return $this->success($this->presenter->detail($booking->refresh()), 'Bron bekor qilindi.');
    }

    /**
     * Mark a no-show
     *
     * A confirmed booking whose start time has passed. Deposits: refund or retain afterwards.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    public function noShow(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['version' => 'nullable|integer|min:1'], UzValidation::messages());
        $booking = $this->bookings->noShow($this->booking($request, $uuid), $request->user(), $this->version($request));

        return $this->success($this->presenter->detail($booking->refresh()), '"Kelmadi" deb belgilandi.');
    }

    /**
     * "Keldi": the guests arrived
     *
     * Opens the order of the booked room (channel pos_hall) with `guests` (default: the planned number) and the
     * pre-ordered dishes on hold with their due times; the deposits become the order's prepayment. `dining_table_id`
     * seats them in another room of the branch. 409 when the order is already open (`data.order_uuid`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{booking: ' . BookingPresenter::DETAIL . ', order: array{uuid: string, status: string}}}')]
    public function arrive(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'guests' => 'nullable|integer|min:1|max:1000',
            'dining_table_id' => UzValidation::rowId(),
            'version' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $user = $request->user();
        $booking = $this->bookings->arrive(
            $this->booking($request, $uuid),
            $user,
            $this->permissions->terminalOf($user)?->id,
            isset($data['guests']) ? (int) $data['guests'] : null,
            isset($data['dining_table_id']) ? (int) $data['dining_table_id'] : null,
            $this->version($request),
        );
        $order = Order::findOrFail($booking->order_id);

        return $this->success([
            'booking' => $this->presenter->detail($booking->refresh()),
            'order' => ['uuid' => (string) $order->uuid, 'status' => $order->status->value],
        ], 'Mehmonlar keldi: buyurtma ochildi.');
    }

    /**
     * Add a pre-ordered dish
     *
     * `product_variant_id` (or P3-26 `product_variant_uuid`, the variant's uuid as POS order lines take it), `quantity`
     * (kg dishes in their steps: 1.5), `modifiers` [{modifier_id, quantity}],
     * `note`, `due_at` (when it must be ready; default the booking's start). Only while the booking is requested or
     * confirmed.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    public function addItem(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->booking($request, $uuid);
        $data = $this->itemData($request->validate([
            'product_variant_id' => 'required_without:product_variant_uuid|nullable|integer|min:1|max:999999999999999999',
            // P3-26: the variant's uuid, as the POS order lines take it (variant_id)
            'product_variant_uuid' => 'required_without:product_variant_id|nullable|uuid',
            'quantity' => 'required|numeric|gt:0|max:100000',
            'modifiers' => 'nullable|array|max:50',
            'modifiers.*.modifier_id' => UzValidation::rowId(true),
            'modifiers.*.quantity' => 'nullable|integer|min:1|max:100',
            'note' => 'nullable|string|max:500',
            'due_at' => 'nullable|date',
            'version' => 'nullable|integer|min:1',
        ], UzValidation::messages()), $booking);
        $this->items->add($booking, $data, $request->user(), $this->version($request));

        return $this->success($this->presenter->detail($booking->refresh()), "Taom qo'shildi.", 201);
    }

    /**
     * Change a pre-ordered dish
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    public function updateItem(Request $request, string $uuid, string $item): JsonResponse
    {
        $booking = $this->booking($request, $uuid);
        $line = $this->item($booking, $item);
        $data = $this->itemData($request->validate([
            'product_variant_id' => 'sometimes|nullable|integer|min:1|max:999999999999999999',
            'product_variant_uuid' => 'sometimes|nullable|uuid', // P3-26
            'quantity' => 'sometimes|numeric|gt:0|max:100000',
            'modifiers' => 'nullable|array|max:50',
            'modifiers.*.modifier_id' => UzValidation::rowId(true),
            'modifiers.*.quantity' => 'nullable|integer|min:1|max:100',
            'note' => 'nullable|string|max:500',
            'due_at' => 'nullable|date',
            'version' => 'nullable|integer|min:1',
        ], UzValidation::messages()), $booking);
        $this->items->update($booking, $line, $data, $request->user(), $this->version($request));

        return $this->success($this->presenter->detail($booking->refresh()), "Taom o'zgartirildi.");
    }

    /**
     * Remove a pre-ordered dish
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingPresenter::DETAIL . '}')]
    public function removeItem(Request $request, string $uuid, string $item): JsonResponse
    {
        $booking = $this->booking($request, $uuid);
        $this->items->remove($booking, $this->item($booking, $item), $request->user(), $this->version($request));

        return $this->success($this->presenter->detail($booking->refresh()), "Taom olib tashlandi.");
    }

    /**
     * Take a deposit
     *
     * `amount`, `tender` cash|card|qr (`card_type`, `transaction_id` for card and QR). Issues an ADVANCE receipt
     * (`fiscal_receipt_uuid`, `fiscal_status`). The `Idempotency-Key` header is required: a retry returns the same
     * deposit and never a second receipt. A requested booking becomes confirmed.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{booking: ' . BookingPresenter::DETAIL . ', payment: ' . BookingPresenter::PAYMENT . ', fiscal: ' . PosFiscalReceipt::FISCAL . '}}')]
    public function takeDeposit(Request $request, string $uuid): JsonResponse
    {
        $key = $this->idempotencyKey($request);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:999999999|decimal:0,2',
            'tender' => ['required', 'string', Rule::in([PosTender::CASH->value, PosTender::CARD->value, PosTender::QR->value])],
            'card_type' => 'nullable|string|max:32',
            'transaction_id' => 'nullable|string|max:100',
        ], UzValidation::messages());

        $user = $request->user();
        $booking = $this->booking($request, $uuid);
        $payment = $this->deposits->take(
            $booking,
            Money::of((string) $data['amount']),
            PosTender::from($data['tender']),
            $data['card_type'] ?? null,
            $data['transaction_id'] ?? null,
            $key,
            $user,
            $this->permissions->terminalOf($user)?->id,
        );

        return $this->success([
            'booking' => $this->presenter->detail($booking->refresh()),
            'payment' => $this->presenter->payment($payment),
            // P3-26: the ADVANCE receipt as registered, for the till to print
            'fiscal' => PosFiscalReceipt::fiscal($payment->fiscal_status, $payment->fiscal_receipt_uuid, null, (int) $booking->store_id),
        ], 'Avans qabul qilindi.', 201);
    }

    /**
     * Refund a deposit
     *
     * What is left of it goes back to the guest. `reason` required. Needs pos.refund: a cashier sends a manager's
     * one-time `approval_token` (POST /pos/approvals). By default no receipt is issued (the refund is documented with
     * a refund act; a store may switch on a REFUND receipt). Not while the guests are seated (arrived).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{booking: ' . BookingPresenter::DETAIL . ', payment: ' . BookingPresenter::PAYMENT . ', fiscal: ' . PosFiscalReceipt::FISCAL . '}}')]
    public function refundDeposit(Request $request, string $uuid, string $payment): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
            'approval_token' => 'nullable|string|max:255',
        ], UzValidation::messages());

        return $this->settleDeposit($request, $data, $uuid, $payment, true);
    }

    /**
     * Retain a deposit
     *
     * After a cancellation or a no-show (or what is left once the bill is closed) the deposit is kept. `reason`
     * required. Needs orders.cancel: a cashier sends a manager's `approval_token`. Issues a SALE receipt for the booking
     * service paid from the advance (a store may switch it off).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{booking: ' . BookingPresenter::DETAIL . ', payment: ' . BookingPresenter::PAYMENT . ', fiscal: ' . PosFiscalReceipt::FISCAL . '}}')]
    public function retainDeposit(Request $request, string $uuid, string $payment): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
            'approval_token' => 'nullable|string|max:255',
        ], UzValidation::messages());

        return $this->settleDeposit($request, $data, $uuid, $payment, false);
    }

    /**
     * @param  array{reason: string, approval_token?: string|null}  $data
     */
    private function settleDeposit(Request $request, array $data, string $uuid, string $paymentUuid, bool $refund): JsonResponse
    {
        $user = $request->user();
        $booking = $this->booking($request, $uuid);
        $payment = BookingPayment::where('booking_id', $booking->id)->where('uuid', $paymentUuid)->first();
        if ($payment === null) {
            throw new HttpResponseException($this->error('Avans topilmadi.', [], 404));
        }

        $deviceId = $this->permissions->terminalOf($user)?->id;
        $payment = $refund
            ? $this->deposits->refund($booking, $payment, $data['reason'], $data['approval_token'] ?? null, $user, $deviceId)
            : $this->deposits->retain($booking, $payment, $data['reason'], $data['approval_token'] ?? null, $user, $deviceId);

        return $this->success([
            'booking' => $this->presenter->detail($booking->refresh()),
            'payment' => $this->presenter->payment($payment),
            // P3-26: the receipt this settlement issued (REFUND or SALE; none when the store does not issue one)
            'fiscal' => PosFiscalReceipt::fiscal($payment->settle_fiscal_status, $payment->settle_receipt_uuid, null, (int) $booking->store_id),
        ], $refund ? 'Avans qaytarildi.' : 'Avans ushlab qolindi.');
    }

    /**
     * The caller's booking: their store, and the branch they act in (another one: 404, like a missing one).
     */
    private function booking(Request $request, string $uuid): Booking
    {
        $acting = $this->permissions->actingBranchId($request->user());
        $booking = Booking::where('store_id', $this->storeId($request))
            ->where('uuid', $uuid)
            ->when($acting !== null, fn ($query) => $query->where('branch_id', $acting))
            ->first();

        if ($booking === null) {
            throw new HttpResponseException($this->error('Bron topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::BOOKINGS_MANAGE, $booking);

        return $booking;
    }

    private function item(Booking $booking, string $id): BookingItem
    {
        $itemId = RowId::parse($id);
        $item = $itemId !== null ? BookingItem::where('booking_id', $booking->id)->find($itemId) : null;
        if ($item === null) {
            throw new HttpResponseException($this->error('Taom topilmadi.', [], 404));
        }

        return $item;
    }

    private function version(Request $request): ?int
    {
        $version = $request->input('version');

        return is_numeric($version) ? (int) $version : null;
    }

    private function idempotencyKey(Request $request): string
    {
        $key = $request->headers->get('Idempotency-Key');
        if ($key === null || trim($key) === '') {
            throw new HttpResponseException($this->error("Idempotency-Key sarlavhasi majburiy: pul bilan bog'liq so'rov qayta yuborilganda ikki marta yozilmasligi uchun.", ['reason' => 'idempotency_key_required'], 400));
        }

        return $key;
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon} time zone, first and last day (start of day, store time zone)
     */
    private function range(Request $request, int $storeId): array
    {
        $tz = StoreTimezone::of(Store::find($storeId));
        $from = $request->filled('from') ? Carbon::createFromFormat('Y-m-d', (string) $request->input('from'), $tz)->startOfDay() : Carbon::now($tz)->startOfDay();
        $to = $request->filled('to') ? Carbon::createFromFormat('Y-m-d', (string) $request->input('to'), $tz)->startOfDay() : $from->copy();

        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['to' => "Oxirgi kun birinchi kundan oldin bo'lmasin."]);
        }
        $max = (int) config('pos_bookings.calendar_max_days', 31);
        if ($from->diffInDays($to) + 1 > $max) {
            throw ValidationException::withMessages(['to' => "Ko'pi bilan {$max} kunlik oraliq."]);
        }

        return [$tz, $from, $to];
    }

    /**
     * The validated booking fields of a create or an update (only what was sent), as BookingService takes them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data, Branch $branch, bool $creating): array
    {
        $tz = StoreTimezone::of(Store::find($branch->store_id));
        $out = [];
        foreach (['starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $out[$field] = $data[$field] !== null ? Carbon::parse((string) $data[$field], $tz)->utc() : null;
            }
        }
        foreach (['dining_table_id', 'guests_planned'] as $field) {
            if (array_key_exists($field, $data)) {
                $out[$field] = $data[$field] !== null ? (int) $data[$field] : null;
            }
        }
        if (array_key_exists('extra_table_ids', $data)) {
            $out['extra_table_ids'] = array_map('intval', $data['extra_table_ids'] ?? []);
        }
        foreach (['event_type', 'source'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $out[$field] = $data[$field];
            }
        }
        foreach (['contact_name', 'note', 'min_spend'] as $field) {
            if (array_key_exists($field, $data)) {
                $out[$field] = $data[$field] !== null ? (is_string($data[$field]) ? trim($data[$field]) : (string) $data[$field]) : null;
            }
        }
        if (array_key_exists('contact_phone', $data)) {
            $phone = $data['contact_phone'];
            if ($phone !== null && trim((string) $phone) !== '') {
                $phone = UzPhone::normalize((string) $phone);
                if ($phone === null) {
                    throw ValidationException::withMessages(['contact_phone' => "Telefon raqami noto'g'ri: +998XXXXXXXXX yoki 9 xonali raqam."]);
                }
            } else {
                $phone = null;
            }
            $out['contact_phone'] = $phone;
        }
        if ($creating) {
            $out['confirm'] = (bool) ($data['confirm'] ?? false);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function itemData(array $data, Booking $booking): array
    {
        $out = [];
        if (isset($data['product_variant_uuid'])) {
            // P3-26: by uuid, within the booking's store only
            $variantId = ProductVariant::where('uuid', (string) $data['product_variant_uuid'])
                ->whereHas('product', fn ($query) => $query->where('store_id', $booking->store_id))
                ->value('id');
            if ($variantId === null) {
                throw ValidationException::withMessages(['product_variant_uuid' => 'Taom topilmadi.']);
            }
            $out['product_variant_id'] = (int) $variantId;
        } elseif (isset($data['product_variant_id'])) {
            $out['product_variant_id'] = (int) $data['product_variant_id'];
        }
        if (array_key_exists('quantity', $data)) {
            $out['quantity'] = (string) $data['quantity'];
        }
        if (array_key_exists('modifiers', $data)) {
            $out['modifiers'] = array_map(fn (array $modifier) => [
                'modifier_id' => (int) $modifier['modifier_id'],
                'quantity' => (int) ($modifier['quantity'] ?? 1),
            ], $data['modifiers'] ?? []);
        }
        if (array_key_exists('note', $data)) {
            $out['note'] = $data['note'];
        }
        if (array_key_exists('due_at', $data)) {
            $out['due_at'] = $data['due_at'] !== null ? Carbon::parse((string) $data['due_at'], StoreTimezone::of(Store::find($booking->store_id)))->utc() : null;
        }

        return $out;
    }
}
