<?php

namespace App\Services\Telegram;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\TelegramAction;
use App\Models\User;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use Illuminate\Support\Facades\Gate;

/**
 * Presses of the one-time action buttons on order cards (TelegramAction tokens). Returns the webhook's answer;
 * the callback answer and the refreshed card are queued.
 *
 * A cancel button ("Rad etish" on a new order, "Bekor qilish" after acceptance) first swaps the card's keyboard for
 * the cancel reasons; each reason button carries the same token plus ":<reason>" (":back" returns to the card), so
 * it passes the very same token, expiry and staff checks before the order is cancelled with that reason.
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

        // A TelegramAction token, optionally followed by ":<choice>" (a cancel reason, or "back")
        [$token, $choice] = array_pad(explode(':', (string) $callbackData, 2), 2, null);
        $telegramAction = $token !== '' ? TelegramAction::where('token', $token)->first() : null;

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
            'cancel', 'abort' => OrderStatus::CANCELED,
            default => null,
        };

        if (!$newStatus) {
            return ['status' => 'unhandled_action'];
        }

        if ($choice !== null && $newStatus !== OrderStatus::CANCELED) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], 'Invalid or expired action!', true);
            return ['status' => 'error', 'message' => 'Invalid token'];
        }

        // The button only proves the message reached some chat: the presser must be staff allowed to make this change
        $actor = $this->staffActor($fromUser['id'] ?? null, $order);
        if (!$actor) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], "Sizda bu buyurtma bo'yicha amal bajarishga ruxsat yo'q!", true);
            return ['status' => 'forbidden'];
        }

        // "Rad etish" is offered on new orders only: an old button must not cancel an accepted order
        $from = match ($telegramAction->action) {
            'cancel' => [OrderStatus::NEW],
            'abort' => [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY],
            default => null,
        };
        $reason = null;

        if ($newStatus === OrderStatus::CANCELED) {
            $reasons = $this->reasonsFor($order, $telegramAction->action);

            if ($choice === null || $choice === 'back') {
                return $this->showReasons($callbackQuery, $telegramAction, $order, $actor, $from, $reasons, $choice === 'back', $chatId, $messageId);
            }

            $reason = CancelReason::tryFrom($choice);
            if (!$reason || !in_array($reason, $reasons, true)) {
                $this->telegram->answerCallbackQuery($callbackQuery['id'], "Bu sababni tanlab bo'lmaydi!", true);
                return ['status' => 'invalid_reason'];
            }
        }

        try {
            $result = $this->orderStatus->transition($order, $newStatus, $actor, [
                'from' => $from,
                'cancel_reason' => $reason,
                'notes' => "Status updated via Telegram Webhook by: {$actorName}",
            ]);
        } catch (OrderTransitionException $e) {
            if ($e->reason === 'forbidden') {
                $this->telegram->answerCallbackQuery($callbackQuery['id'], "Sizda bu buyurtma bo'yicha amal bajarishga ruxsat yo'q!", true);
                return ['status' => 'forbidden'];
            }

            if ($newStatus === OrderStatus::CANCELED) {
                $this->telegram->answerCallbackQuery($callbackQuery['id'], $this->cannotCancelText($telegramAction->action));
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
     * First press of a cancel button: the card's keyboard becomes the reasons (nothing changes yet). With $back, the
     * reasons go away and the card's own buttons come back. Refused like the cancel itself would be.
     *
     * @param list<OrderStatus>|null $from
     * @param list<CancelReason> $reasons
     */
    protected function showReasons(array $callbackQuery, TelegramAction $telegramAction, Order $order, User $actor, ?array $from, array $reasons, bool $back, mixed $chatId, mixed $messageId): array
    {
        if (Gate::forUser($actor)->denies('changeStatus', [$order, OrderStatus::CANCELED])) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], "Sizda bu buyurtma bo'yicha amal bajarishga ruxsat yo'q!", true);
            return ['status' => 'forbidden'];
        }

        if ($back) {
            if ($chatId && $messageId) {
                $this->telegramService->updateOrderTelegramMessage($order, null, (string) $messageId, (string) $chatId);
            }
            $this->telegram->answerCallbackQuery($callbackQuery['id'], 'Bekor qilinmadi.');
            return ['status' => 'back'];
        }

        if ($order->status->isCancellation()) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], "Buyurtma allaqachon bu holatda!");
            return ['status' => 'no_change'];
        }

        if ($from !== null && !in_array($order->status, $from, true)) {
            $this->telegram->answerCallbackQuery($callbackQuery['id'], $this->cannotCancelText($telegramAction->action));
            return ['status' => 'cannot_cancel'];
        }

        if ($chatId && $messageId) {
            $this->telegram->editMessageReplyMarkup($chatId, $messageId, $this->telegramService->cancelReasonKeyboard($telegramAction->token, $reasons));
        }
        $this->telegram->answerCallbackQuery($callbackQuery['id'], 'Bekor qilish sababini tanlang.');

        return ['status' => 'choose_reason'];
    }

    /**
     * Reasons offered for a cancel button: "no show" only once the store accepted a pickup order (the "abort" button,
     * CancelReason::appliesTo), "no courier" only for delivery orders. payment_timeout is the system's own.
     *
     * @return list<CancelReason>
     */
    protected function reasonsFor(Order $order, string $action): array
    {
        return array_values(array_filter(CancelReason::staffCases(), fn (CancelReason $reason) => match ($reason) {
            CancelReason::NO_SHOW => $action === 'abort' && $reason->appliesTo($order->order_type, $order->status),
            CancelReason::COURIER_UNAVAILABLE => $order->type === 'delivery',
            default => true,
        }));
    }

    protected function cannotCancelText(string $action): string
    {
        return $action === 'cancel'
            ? "Buyurtma qabul qilinganidan keyin uni bekor qilib bo'lmaydi!"
            : "Buyurtmani bu holatda bekor qilib bo'lmaydi!";
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
