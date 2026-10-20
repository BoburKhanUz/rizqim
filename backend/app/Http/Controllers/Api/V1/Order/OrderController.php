<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Actions\Order\CreateOrderAction;
use App\Enums\CancelReason;
use App\Enums\OrderStatus;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CreateOrderAction $createOrderAction,
        protected OrderStatusService $orderStatus,
        protected OrderQueryService $orders,
        protected OrderAlertService $alerts,
        protected PaymentLinkService $paymentLinks,
    ) {}

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

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
            'type' => 'required|string|in:delivery,pickup,dine_in',
            'payment_method' => 'required|string|in:cash,click,payme,uzum,paynet,keshbek',
            'branch_id' => 'required_if:type,pickup|nullable|exists:branches,id',
            'address' => 'required_if:type,delivery|nullable|string',
            'latitude' => 'required_if:type,delivery|nullable|numeric',
            'longitude' => 'required_if:type,delivery|nullable|numeric',
            'notes' => 'nullable|string|max:500',
            'promo_code' => 'nullable|string|max:50',
        ]);

        try {
            // Its "new" status history row is written by the action itself
            $order = $this->createOrderAction->execute(
                $request->user(),
                $request->items,
                $request->only(['store_id', 'type', 'payment_method', 'address', 'latitude', 'longitude', 'branch_id', 'notes', 'promo_code'])
            );

            $this->alerts->newOrder($order);

            return $this->success([
                'order_uuid' => $order->uuid,
                'order_number' => $order->short_code,
                'status' => $order->status->value,
                'payment_url' => $this->paymentLinks->forOrder($order),
            ], 'Order created successfully.', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [], 400);
        }
    }

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
     * The customer cancels their own order: only while it is new and unpaid (Order::isCancellableByCustomer), always
     * with the reason customer_request. Stock, promo use and the like come back as with any cancellation; a payment
     * arriving afterwards is refused by the payment webhooks. Returns the order as GET /orders lists it.
     */
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

    public function couriers(Request $request, CourierListService $couriers): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = $user->role->value ?? null; // null for an in-memory user created without a role
        if (!in_array($userRole, ['owner', 'manager']) || !$user->store_id) {
            return $this->error('Ruxsat etilmagan.', [], 403);
        }

        return $this->success($couriers->forAssigner($user, $request->only('branch_id')));
    }

    public function assignCourier(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = $user->role->value ?? null; // null for an in-memory user created without a role
        if (!in_array($userRole, ['owner', 'manager'])) {
            return $this->error('Unauthorized.', [], 403);
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

    public function notifications(Request $request, NotificationFeedService $feed): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        return $this->success($feed->page($user));
    }

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
