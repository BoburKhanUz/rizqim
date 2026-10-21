<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Actions\Order\CreateOrderAction;
use App\Domain\Delivery\DeliveryUnavailableException;
use App\Enums\CancelReason;
use App\Enums\DeviceType;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Models\Device;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Support\Facades\DB;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Services\Notifications\NotificationFeedService;
use App\Services\Notifications\OrderAlertService;
use App\Services\Order\CourierListService;
use App\Services\Order\OrderQueryService;
use App\Services\Order\OrderReportService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Order\PaymentLinkService;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Telegram\TelegramMessenger;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Group('Orders', 'Buyurtmalar: mijoz, ega, menejer va kuryer uchun.')]
class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CreateOrderAction $createOrderAction,
        protected OrderStatusService $orderStatus,
        protected OrderQueryService $orders,
        protected OrderAlertService $alerts,
        protected PaymentLinkService $paymentLinks,
        protected IdempotencyService $idempotency,
    ) {}

    /**
     * The channel of an order placed through POST /orders (P1-34, docs/MENU.md §7): `mobile` when the access token is
     * bound to a customer mobile app device (POST /auth/telegram or /auth/phone/verify with `device.type =
     * customer_mobile`, docs/AUTH.md), else `telegram` (the Mini App's initData token has no device).
     */
    private function channelOf(Request $request): OrderChannel
    {
        // A TransientToken (session, tests) has no device
        $deviceId = data_get($request->user()?->currentAccessToken(), 'device_id');
        $device = $deviceId ? Device::whereKey($deviceId)->whereNull('revoked_at')->first(['id', 'type']) : null;

        return $device?->type === DeviceType::CUSTOMER_MOBILE ? OrderChannel::MOBILE : OrderChannel::TELEGRAM;
    }

    /**
     * List orders
     *
     * The orders the user may see: a customer's own, a courier's deliveries, the store's (owner) or the branch's
     * (manager), newest first, with the relations the apps show. Without `scope`: every order, unpaginated (kept for
     * app versions from before scopes). `scope=active`: the orders still in progress (at most 200; `meta.truncated`
     * says more exist). `scope=history`: finished orders (delivered, canceled, rejected, refunded), keyset-paginated:
     * send `meta.next_cursor` back as `cursor` until it is null.
     */
    #[QueryParameter('scope', description: 'Omit for the full legacy list.', type: "'active'|'history'")]
    #[QueryParameter('per_page', description: 'Only with `scope=history`: page size, 1-50 (larger values are capped at 50).', type: 'int', default: OrderQueryService::HISTORY_DEFAULT_PER_PAGE)]
    #[QueryParameter('cursor', description: 'Only with `scope=history`: `meta.next_cursor` of the previous page.', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $scope = $request->query('scope');

        // Without a scope: the full, unpaginated list that app versions from before scopes still expect
        if ($scope === null) {
            return $this->success($this->orders->visibleTo($user));
        }

        $invalid = fn (string $field, string $message) => response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
            'errors' => [$field => [$message]],
        ], 422);

        if ($scope === 'active') {
            $active = $this->orders->activeFor($user);

            return response()->json([
                'success' => true,
                'message' => '',
                'data' => $active['orders'],
                'meta' => ['truncated' => $active['truncated']],
            ]);
        }

        if ($scope !== 'history') {
            return $invalid('scope', "Noto'g'ri scope qiymati. Ruxsat etilgan qiymatlar: active, history.");
        }

        $perPage = $request->query('per_page') ?? (string) OrderQueryService::HISTORY_DEFAULT_PER_PAGE;
        if (!is_string($perPage) || !ctype_digit($perPage) || (int) $perPage < 1) {
            return $invalid('per_page', "per_page musbat butun son bo'lishi kerak.");
        }
        $perPage = min((int) $perPage, OrderQueryService::HISTORY_MAX_PER_PAGE);

        $after = null;
        $cursor = $request->query('cursor');
        if ($cursor !== null) {
            $after = is_string($cursor) ? $this->orders->decodeCursor($cursor) : null;
            if ($after === null) {
                return $invalid('cursor', "Noto'g'ri yoki eskirgan cursor. Ro'yxatni boshidan yuklang.");
            }
        }

        $history = $this->orders->historyFor($user, $perPage, $after);

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $history['orders'],
            'meta' => ['next_cursor' => $history['next_cursor'], 'per_page' => $perPage],
        ]);
    }

    /**
     * Mark an order delivered (courier)
     *
     * 403/422 when the order's status or the user's role does not allow it.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: Order}')]
    public function complete(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $order = $this->orders->findVisible($user, $uuid);
        if (!$order) {
            return $this->error('Buyurtma topilmadi yoki sizga biriktirilmagan.', [], 404);
        }

        if (Gate::forUser($user)->denies('complete', $order)) {
            return $this->error('Ruxsat etilmagan.', [], 403);
        }

        try {
            $result = $this->orderStatus->transition($order, OrderStatus::DELIVERED, $user, [
                'notes' => "Order completed/delivered by Courier: {$user->name}",
            ]);
        } catch (OrderTransitionException $e) {
            return $this->error($e->getMessage(), [], $e->status);
        }

        if ($result->changed) {
            // Still in the response, as when the delivered alert (now the listener's) was sent from here and loaded it
            $result->order->loadMissing('user');
        }

        return $this->success($result->order, 'Order delivered successfully.');
    }

    /**
     * Place an order (checkout)
     *
     * Prices, stock, the promo code and the delivery fee are checked on the server. `payment_url` is set for online
     * payment methods (Click, Payme): open it to pay; the order is cancelled when it stays unpaid. 400 with the reason
     * when the order cannot be placed (store closed, item unavailable, minimum not reached...).
     *
     * P1-21 (docs/MENU.md): `items.*.quantity` may be a fraction for items sold by weight (kg/l, at least
     * `min_quantity`, in `quantity_step` steps); `items.*.modifiers` lists the chosen modifiers as uuids or
     * `{id: uuid, quantity}`; prices always come from the server. 422 when a modifier rule is broken, the item is on
     * the branch's stop-list, its batch is sold out ("tugadi") or it is hidden on the channel. The channel is decided by
     * the server from the sign-in (P1-34): `mobile` for a token of a customer mobile app device, else `telegram`; a
     * `channel` in the body is accepted for older apps but ignored. `type=dine_in` is refused with 422 (hall orders
     * are taken by staff on the POS). An `Idempotency-Key` header makes retries safe: the same key with the same body
     * within 24 hours returns the first response (header `Idempotent-Replayed: true`), with another body 409.
     *
     * P1-32 (docs/ZONES.md): when the store has delivery zones, a `delivery` order is routed and priced by the zone
     * that covers `latitude`/`longitude`, exactly as `GET /delivery/quote` prices it: the zone's fee (free past its
     * threshold) and minimum order replace the store-wide ones, any fee the client sends is ignored. Without
     * `branch_id` the branch is picked automatically (the zone's branch, else the nearest open one); with it, that
     * branch must serve a zone covering the point. 422 with `data.reason`: `outside_zone`, `branch_outside_zone`,
     * `branch_closed`, or `below_minimum_order` (with `data.min_order_amount` and `data.subtotal`). Stores without
     * zones keep the branch radius rule and the store's delivery fee.
     */
    #[ApiDocResponse(422, type: 'array{success: false, message: string, data: array{reason: string, min_order_amount?: string, subtotal?: string}}', description: "Yetkazib berish hududi: manzilga yetkazib bo'lmaydi yoki buyurtma hududning eng kam summasidan kam")]
    #[HeaderParameter('Idempotency-Key', description: 'Optional, 1-255 visible characters (e.g. a UUID made per checkout attempt). Same key + same body within 24 hours: the first response is returned again; another body: 409.', type: 'string')]
    public function store(Request $request): JsonResponse
    {
        // bail on the ids: one past bigint is a 422 here, never a database error from the exists query
        $request->validate([
            'store_id' => 'bail|required|integer|min:1|exists:stores,id',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|uuid',
            // P1-21: a fraction for items sold by weight (0.5 kg, at most 3 decimals); pieces stay whole (checked per unit)
            'items.*.quantity' => 'required|numeric|gt:0|max:9999',
            'items.*.modifiers' => 'nullable|array|max:50',
            // P1-34: accepted for older apps, ignored: the channel is the sign-in's (channelOf)
            'channel' => ['nullable', 'string', Rule::in(\App\Enums\OrderChannel::customerValues())],
            'type' => 'required|string|in:delivery,pickup,dine_in',
            'payment_method' => 'required|string|in:cash,click,payme,uzum,paynet,keshbek',
            'branch_id' => 'bail|required_if:type,pickup|nullable|integer|min:1|exists:branches,id',
            'address' => 'required_if:type,delivery|nullable|string',
            'latitude' => 'required_if:type,delivery|nullable|numeric',
            'longitude' => 'required_if:type,delivery|nullable|numeric',
            'notes' => 'nullable|string|max:500',
            'promo_code' => 'nullable|string|max:50',
        ]);

        // P1-34: a hall order is taken by a waiter or the till (the POS path), never from the customer apps: without a
        // branch it would skip the branch's stop-list and batch checks
        if ($request->input('type') === OrderType::DINE_IN->value) {
            throw ValidationException::withMessages([
                'type' => "Zalda ovqatlanish buyurtmasini ilovadan berib bo'lmaydi: uni ofitsiant yoki kassa qabul qiladi. Yetkazib berish yoki olib ketishni tanlang.",
            ]);
        }

        try {
            $payload = $request->only(['store_id', 'type', 'payment_method', 'address', 'latitude', 'longitude', 'branch_id', 'notes', 'promo_code']);
            // P1-34: the channel (its prices and visibility) is decided by how the customer signed in, not by the body
            $payload['channel'] = $this->channelOf($request);

            // The Idempotency-Key is marked used in the order's own transaction, so the order and the key commit
            // together: a retry can never place it a second time (IdempotencyService::markApplied)
            $order = DB::transaction(function () use ($request, $payload) {
                // Its "new" status history row is written by the action itself
                $order = $this->createOrderAction->execute($request->user(), $request->items, $payload);
                $this->idempotency->markApplied($request);

                return $order;
            });

            $this->alerts->newOrder($order);

            return $this->success([
                'order_uuid' => $order->uuid,
                'order_number' => $order->short_code,
                'status' => $order->status->value,
                'payment_url' => $this->paymentLinks->forOrder($order),
            ], 'Order created successfully.', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (DeliveryUnavailableException $e) {
            // P1-32: delivery zones, the same shape as GET /delivery/quote (data.reason, plus e.g. min_order_amount)
            return $this->error($e->getMessage(), $e->data(), 422);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            // The Idempotency-Key's claim was taken over by a retry: this attempt was rolled back
            return $this->error($e->getMessage(), [], 409);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [], 400);
        }
    }

    /**
     * Change an order's status (owner, manager, courier)
     *
     * Only the transitions the order's state machine allows; cancelling or rejecting needs a `reason`. Answers 200
     * with the unchanged order when it is already in that status; 403/422 when the transition is not allowed.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: Order}')]
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = $user->role->value ?? null; // null for an in-memory user created without a role
        if (!in_array($userRole, ['owner', 'manager', 'courier'])) {
            return $this->error('Unauthorized.', [], 403);
        }

        // Cancelling or rejecting needs a reason (App\Enums\CancelReason, payment_timeout being the system's own)
        $request->validate([
            'status' => 'required|string|in:accepted,preparing,ready,courier_assigned,on_the_way,delivery,delivered,canceled,rejected',
            'courier_id' => 'nullable|integer',
            'reason' => ['nullable', 'required_if:status,canceled,rejected', 'string', Rule::in(CancelReason::staffValues())],
            'note' => 'nullable|string|max:500',
        ], [
            'reason.required_if' => 'Bekor qilish sababini tanlang.',
            'reason.in' => "Bekor qilish sababi noto'g'ri.",
            'note.max' => "Izoh ko'pi bilan 500 belgi bo'lishi mumkin.",
        ]);

        $order = $this->orders->findVisible($user, $uuid);
        if (!$order) {
            return $this->error('Buyurtma topilmadi yoki sizning filialingizga tegishli emas.', [], 404);
        }

        $status = OrderStatus::from($request->status);

        try {
            $result = $this->orderStatus->transition($order, $status, $user, [
                'courier_id' => $request->input('courier_id'),
                'cancel_reason' => $status->isCancellation() ? $request->input('reason') : null,
                'cancel_note' => $status->isCancellation() ? $request->input('note') : null,
                'notes' => "Order status updated via Manager App to: {$status->value} by {$user->name}",
            ]);
        } catch (OrderTransitionException $e) {
            // A reason the order does not allow (no_show before the store accepted it) is a 422 on the reason field
            if ($e->reason === 'reason') {
                throw ValidationException::withMessages(['reason' => $e->getMessage()]);
            }

            return $this->error($e->getMessage(), [], $e->status);
        }

        if ($result->changed) {
            $this->alerts->statusChanged($result->order, $status->value);
        }

        return $this->success(
            $result->order->load(['items.variant.product', 'user', 'courier']),
            $result->changed ? 'Order status updated successfully.' : 'Buyurtma allaqachon shu holatda.'
        );
    }

    /**
     * Cancel own order (customer)
     *
     * The customer cancels their own order: only while it is new and unpaid (Order::isCancellableByCustomer), always
     * with the reason customer_request. Stock, promo use and the like come back as with any cancellation; a payment
     * arriving afterwards is refused by the payment webhooks. Returns the order as GET /orders lists it.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: Order}')]
    public function cancel(Request $request, string $uuid, TelegramNotificationService $telegram): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $request->validate([
            'reason' => ['nullable', 'string', Rule::in([CancelReason::CUSTOMER_REQUEST->value])],
            'note' => 'nullable|string|max:500',
        ], [
            'reason.in' => "Bekor qilish sababi noto'g'ri.",
            'note.max' => "Izoh ko'pi bilan 500 belgi bo'lishi mumkin.",
        ]);

        $order = Str::isUuid($uuid) ? Order::where('uuid', $uuid)->first() : null;
        if (!$order) {
            return $this->error('Buyurtma topilmadi.', [], 404);
        }

        if ((int) $order->user_id !== (int) $user->id) {
            return $this->error("Faqat o'zingizning buyurtmangizni bekor qila olasiz.", [], 403);
        }

        try {
            $result = $this->orderStatus->transition($order, OrderStatus::CANCELED, $user, [
                'customer' => true,
                'cancel_reason' => CancelReason::CUSTOMER_REQUEST,
                'cancel_note' => $request->input('note'),
                'notes' => 'Mijoz buyurtmani bekor qildi',
            ]);
        } catch (OrderTransitionException $e) {
            return $this->error($e->getMessage(), [], $e->status);
        }

        if ($result->changed) {
            // The customer hears from the OrderCancelled listener; the staff who got the order's card hear from here
            $telegram->sendCustomerCancelled($result->order);
        }

        return $this->success($result->order->load(['items.variant.product']), 'Buyurtma bekor qilindi.');
    }

    /**
     * Couriers an order can be assigned to (orders.assign_courier)
     *
     * The store's couriers (and staff who deliver), with a label for their role. P2-30: by the orders.assign_courier
     * permission (owner and manager by default), not the role; staff tied to a branch get their branch's couriers.
     */
    #[Group('Couriers')]
    #[QueryParameter('branch_id', description: "Owner only: that branch's couriers plus those of no branch. A manager always gets their own branch.", type: 'int')]
    public function couriers(Request $request, CourierListService $couriers): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        // The permission is the route's; couriers and customers never hold it
        if (!$user->store_id || !$user->role->isStoreStaff() || $user->role === UserRole::COURIER) {
            return $this->error('Ruxsat etilmagan.', [], 403);
        }

        return $this->success($couriers->forAssigner($user, $request->only('branch_id')));
    }

    /**
     * Assign a courier to an order (orders.assign_courier)
     *
     * 403/422 when the order's status does not allow it or the courier is not the store's. P2-30: by permission
     * (orders.assign_courier with orders.manage on this order: OrderPolicy::assignCourier), not by role.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: Order}')]
    public function assignCourier(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        if (!$user->role->isStoreStaff() || $user->role === UserRole::COURIER) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $request->validate([
            'courier_id' => 'required|integer',
        ]);

        $order = $this->orders->findVisible($user, $uuid);
        if (!$order) {
            return $this->error('Buyurtma topilmadi yoki sizning filialingizga tegishli emas.', [], 404);
        }

        try {
            $result = $this->orderStatus->transition($order, OrderStatus::COURIER_ASSIGNED, $user, [
                'courier_id' => $request->input('courier_id'),
                'notes' => "Courier assigned by {$user->name}",
            ]);
        } catch (OrderTransitionException $e) {
            return $this->error($e->getMessage(), [], $e->status);
        }

        // The courier hears about it from the SendCourierAssignedAlert listener, as with every other way of assigning one
        return $this->success($result->order->load(['items.variant.product', 'user', 'courier']), 'Courier assigned successfully.');
    }

    /**
     * Send a sales report to the user's Telegram (owner, manager)
     *
     * Built by a queue worker and sent as a file to the user's (or the store's) Telegram chat; the response only says
     * it was queued. A custom period needs both dates and is at most 93 days.
     */
    #[Group('Reports')]
    public function exportReport(Request $request, OrderReportService $reports, TelegramMessenger $telegram): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = $user->role->value ?? null; // null for an in-memory user created without a role
        if (!in_array($userRole, ['owner', 'manager']) || !$user->store_id) {
            return $this->error('Ruxsat etilmagan.', [], 403);
        }

        // A custom period needs both dates and is capped, so no export reads the store's whole history
        $maxDays = (int) config('orders.reports.max_custom_days', 93);
        $request->validate([
            'timeframe' => 'required|string|in:today,weekly,monthly,yearly,custom',
            'start_date' => 'nullable|required_if:timeframe,custom|date',
            'end_date' => [
                'nullable', 'required_if:timeframe,custom', 'date', 'after_or_equal:start_date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $maxDays) {
                    $start = $request->input('start_date');
                    if ($request->input('timeframe') !== 'custom' || !is_string($start) || strtotime($start) === false || !is_string($value)) {
                        return;
                    }
                    if (\Carbon\Carbon::parse($start)->startOfDay()->diffInDays(\Carbon\Carbon::parse($value)->startOfDay()) + 1 > $maxDays) {
                        $fail("Hisobot davri ko'pi bilan {$maxDays} kun bo'lishi mumkin.");
                    }
                },
            ],
        ], [
            'start_date.required_if' => 'Hisobot davrining boshlanish sanasini kiriting.',
            'end_date.required_if' => 'Hisobot davrining tugash sanasini kiriting.',
            'start_date.date' => "Boshlanish sanasi noto'g'ri.",
            'end_date.date' => "Tugash sanasi noto'g'ri.",
            'end_date.after_or_equal' => "Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.",
        ]);

        if (!$telegram->isConfigured()) {
            return $this->error('Telegram bot not configured globally.', [], 400);
        }

        $chatId = $reports->chatIdFor($user);
        if (!$chatId) {
            return $this->error('Target Telegram Chat ID not found.', [], 400);
        }

        // Built and sent by a queue worker: Telegram's own failures are retried and logged there
        try {
            $reports->queue($user, $chatId, $request->only(['timeframe', 'start_date', 'end_date']));
        } catch (\Exception $e) {
            return $this->error('Xatolik yuz berdi: ' . $e->getMessage(), [], 500);
        }

        return $this->success(null, 'Hisobot Telegramingizga muvaffaqiyatli yuborildi! 🚀');
    }

    /**
     * In-app notifications
     *
     * The user's notification feed, newest first, 20 per page, with the unread count.
     */
    #[Group('Notifications')]
    #[QueryParameter('page', description: 'Page number (Laravel pagination).', type: 'int', default: 1)]
    public function notifications(Request $request, NotificationFeedService $feed): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        return $this->success($feed->page($user));
    }

    /**
     * Mark all notifications read
     */
    #[Group('Notifications')]
    public function markNotificationsRead(Request $request, NotificationFeedService $feed): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $feed->markAllRead($user);

        return $this->success(null, 'Barcha xabarnomalar o\'qildi deb belgilandi.');
    }
}
