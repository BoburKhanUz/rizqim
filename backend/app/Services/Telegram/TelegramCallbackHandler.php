<?php

namespace App\Services\Telegram;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\TelegramAction;
use App\Models\User;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;

/**
 * Presses of the one-time action buttons on order cards (TelegramAction tokens). Returns the webhook's answer;
 * the callback answer and the refreshed card are queued.
 */
class TelegramCallbackHandler
{
    public function __construct(
        protected TelegramMessenger $telegram,
        protected TelegramNotificationService $telegramService,
        protected OrderStatusService $orderStatus
    ) {}

    public function handle(array $callbackQuery): array
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $fromUser = $callbackQuery['from'] ?? [];
        $actorName = trim(($fromUser['first_name'] ?? '') . ' ' . ($fromUser['last_name'] ?? ''));
        if (empty($actorName)) {
            $actorName = $fromUser['username'] ?? 'Courier';
        }

        $message = $callbackQuery['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $messageId = $message['message_id'] ?? null;

        // Parse callback action using TelegramAction token
        $telegramAction = TelegramAction::where('token', $callbackData)->first();

        if (!$telegramAction) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], 'Invalid or expired action!', true);
            return ['status' => 'error', 'message' => 'Invalid token'];
        }

        if ($telegramAction->used_at) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], 'Bu amal allaqachon bajarilgan!', true);
            return ['status' => 'error', 'message' => 'Already used'];
        }

        if ($telegramAction->expires_at->isPast()) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], 'Amal muddati tugagan!', true);
            return ['status' => 'error', 'message' => 'Expired token'];
        }

        $order = $telegramAction->order;
        if (!$order) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], 'Order not found!', true);
            return ['status' => 'error', 'message' => 'Order not found'];
        }

        // Map action to status
        $newStatus = match ($telegramAction->action) {
            'accept' => OrderStatus::ACCEPTED,
            'prepare' => OrderStatus::PREPARING,
            'deliver' => OrderStatus::COURIER_ASSIGNED,
            'take' => OrderStatus::ON_THE_WAY,
            'complete' => OrderStatus::DELIVERED,
            'cancel' => OrderStatus::CANCELED,
            default => null,
        };

        if (!$newStatus) {
            return ['status' => 'unhandled_action'];
        }

        // The button only proves the message reached some chat: the presser must be staff allowed to make this change
        $actor = $this->staffActor($fromUser['id'] ?? null, $order);
        if (!$actor) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], "Sizda bu buyurtma bo'yicha amal bajarishga ruxsat yo'q!", true);
            return ['status' => 'forbidden'];
        }

        try {
            $result = $this->orderStatus->transition($order, $newStatus, $actor, [
                // "Rad etish" is offered on new orders only: an old button must not cancel an accepted order
                'from' => $newStatus === OrderStatus::CANCELED ? [OrderStatus::NEW] : null,
                'notes' => "Status updated via Telegram Webhook by: {$actorName}",
            ]);
        } catch (OrderTransitionException $e) {
            if ($e->reason === 'forbidden') {
                $this->telegram->answerCallbackQuery($callbackQuery['id'], "Sizda bu buyurtma bo'yicha amal bajarishga ruxsat yo'q!", true);
                return ['status' => 'forbidden'];
            }

            if ($newStatus === OrderStatus::CANCELED) {
                $this->telegram->answerCallbackQuery($callbackQuery['id'], "Buyurtma qabul qilinganidan keyin uni bekor qilib bo'lmaydi!");
                return ['status' => 'cannot_cancel'];
            }

            $this->telegram->answerCallbackQuery($callbackQuery['id'], $e->getMessage(), true);
            return ['status' => 'invalid_transition'];
        }

        // Spent only once it went through, so a refused press leaves the button usable for staff who may use it
        $telegramAction->update(['used_at' => now()]);

        if (!$result->changed) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], "Buyurtma allaqachon bu holatda!");
            return ['status' => 'no_change'];
        }

        // Update the Telegram message visually
        $this->telegramService->updateOrderTelegramMessage($result->order, $actorName, $messageId, $chatId);

        // Answer callback query with success banner
        $statusLabel = $newStatus->label();
        $this->telegram->answerCallbackQuery($callbackQuery['id'], "Buyurtma holati: {$statusLabel}!");

        return ['status' => 'success'];
    }

    /**
     * The staff account behind a Telegram user for this order: their account in the order's store, else a super admin.
     * Whether it may make the change is decided by OrderPolicy inside OrderStatusService.
     */
    protected function staffActor(mixed $telegramUserId, Order $order): ?User
    {
        if (!is_scalar($telegramUserId) || (string) $telegramUserId === '') {
            return null;
        }

        return User::where('telegram_id', (string) $telegramUserId)
            ->whereIn('role', [UserRole::OWNER->value, UserRole::MANAGER->value, UserRole::COURIER->value, UserRole::SUPER_ADMIN->value])
            ->where(function ($query) use ($order) {
                $query->where('store_id', $order->store_id)
                    ->orWhere('role', UserRole::SUPER_ADMIN->value);
            })
            ->orderByRaw('CASE WHEN store_id = ? THEN 0 ELSE 1 END', [$order->store_id])
            ->first();
    }
}
