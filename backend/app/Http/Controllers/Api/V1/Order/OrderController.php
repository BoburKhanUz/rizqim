<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Actions\Order\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Services\Notifications\NotificationFeedService;
use App\Services\Notifications\OrderAlertService;
use App\Services\Order\CourierListService;
use App\Services\Order\OrderQueryService;
use App\Services\Order\OrderReportService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Order\PaymentLinkService;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Support\Facades\Gate;

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

        return $this->success($this->orders->visibleTo($user));
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
            $this->alerts->statusChanged($result->order, 'delivered');
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

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
        if (!in_array($userRole, ['owner', 'manager', 'courier'])) {
            return $this->error('Unauthorized.', [], 403);
        }

        $request->validate([
            'status' => 'required|string|in:accepted,preparing,ready,courier_assigned,on_the_way,delivery,delivered,canceled,rejected',
            'courier_id' => 'nullable|integer',
        ]);

        $order = $this->orders->findVisible($user, $uuid);
        if (!$order) {
            return $this->error('Buyurtma topilmadi yoki sizning filialingizga tegishli emas.', [], 404);
        }

        $status = OrderStatus::from($request->status);

        try {
            $result = $this->orderStatus->transition($order, $status, $user, [
                'courier_id' => $request->input('courier_id'),
                'notes' => "Order status updated via Manager App to: {$status->value} by {$user->name}",
            ]);
        } catch (OrderTransitionException $e) {
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

    public function couriers(Request $request, CourierListService $couriers): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
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

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
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

        $this->alerts->courierAssigned($result->order, $result->assignedCourier);

        return $this->success($result->order->load(['items.variant.product', 'user', 'courier']), 'Courier assigned successfully.');
    }

    public function exportReport(Request $request, OrderReportService $reports, TelegramMessenger $telegram): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized.', [], 401);
        }

        $userRole = is_object($user->role) ? $user->role->value : $user->role;
        if (!in_array($userRole, ['owner', 'manager']) || !$user->store_id) {
            return $this->error('Ruxsat etilmagan.', [], 403);
        }

        $request->validate([
            'timeframe' => 'required|string|in:today,weekly,monthly,yearly,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
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
