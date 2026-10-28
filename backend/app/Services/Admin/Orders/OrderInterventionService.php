<?php

namespace App\Services\Admin\Orders;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\OrderAlertService;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Order\OrderTransitionResult;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Support\Facades\DB;

/**
 * P4-14: what a RIZQIM team member does to a marketplace order from the Boshqaruv markazi. Every status change goes
 * through OrderStatusService (the state machine, OrderPolicy, stock, keshbek and refund flags, settlement, the customer's
 * alerts, the stores' real-time events): nothing here writes a status column. The member acts as themselves
 * (OrderPolicy lets a team member with admin.orders.intervene move orders; refunds need admin.orders.refund_approve).
 *
 *  - cancel: the store-side cancel reasons (CancelReason::staffOptionsFor), with the existing refund rules: keshbek goes
 *    back to the balance, money Click/Payme (or cash) took is flagged for a manual refund (then a refund request);
 *  - fix status: only a move the lifecycle allows from the current status (OrderStatus::allowedTransitions), never a
 *    cancellation (cancel) or a refund (refund requests). There is no "force" list: a move the lifecycle does not
 *    allow is refused (422 illegal);
 *  - message: a Telegram message to the store's order chats (its admin chat and the managers who get order cards, the
 *    same recipients as a new order card) or to the customer through the bot, queued like every Telegram message.
 * The store hears about a cancel or a status fix in its order chats too.
 */
class OrderInterventionService
{
    public function __construct(
        private readonly OrderStatusService $orders,
        private readonly OrderAlertService $alerts,
        private readonly TelegramNotificationService $cards,
        private readonly TelegramMessenger $telegram,
    ) {}

    /**
     * Statuses the "fix status" action may move this order to now.
     *
     * @return list<OrderStatus>
     */
    public static function fixableTo(Order $order): array
    {
        return array_values(array_filter(
            $order->status->allowedTransitions($order->order_type),
            fn (OrderStatus $to) => !$to->isCancellation() && $to !== OrderStatus::REFUNDED,
        ));
    }

    public static function canCancel(Order $order): bool
    {
        return $order->status->canTransitionTo(OrderStatus::CANCELED, $order->order_type);
    }

    /**
     * @throws AdminOrderException
     */
    public function cancel(Order $order, User $member, CancelReason $cancelReason, ?string $note, string $reason): OrderTransitionResult
    {
        if (!self::canCancel($order)) {
            throw new AdminOrderException("Buyurtmani «{$order->status->label()}» holatida bekor qilib bo'lmaydi.", 'illegal');
        }

        $result = $this->transition($order, OrderStatus::CANCELED, $member, [
            'from' => [$order->status],
            'cancel_reason' => $cancelReason,
            'cancel_note' => $note,
            'notes' => 'Boshqaruv markazi: ' . trim($reason),
        ]);

        if ($result->changed) {
            $this->tellStore($result->order, "❌ Buyurtma #{$result->order->short_code} RIZQIM jamoasi tomonidan bekor qilindi.\nSabab: {$cancelReason->label()}"
                . ($note !== null && trim($note) !== '' ? "\nIzoh: " . trim($note) : ''));
        }

        return $result;
    }

    /**
     * @throws AdminOrderException
     */
    public function fixStatus(Order $order, User $member, OrderStatus $to, string $reason, ?int $courierId = null): OrderTransitionResult
    {
        if ($to->isCancellation()) {
            throw new AdminOrderException("Bekor qilish uchun «Bekor qilish» amalidan foydalaning.", 'use_cancel');
        }
        if ($to === OrderStatus::REFUNDED) {
            throw new AdminOrderException("Pulni qaytarish so'rov va tasdiq orqali qilinadi.", 'use_refund_request');
        }
        if ($to !== $order->status && !$order->status->canTransitionTo($to, $order->order_type)) {
            throw new AdminOrderException(OrderTransitionException::illegal($order->status, $to)->getMessage(), 'illegal');
        }

        $result = $this->transition($order, $to, $member, array_filter([
            'from' => [$order->status],
            'courier_id' => $courierId,
            'notes' => 'Boshqaruv markazi: holat tuzatildi. Sabab: ' . trim($reason),
        ], fn ($value) => $value !== null));

        if ($result->changed) {
            $order = $result->order;
            // The next one to act hears about it as after any staff move (couriers when a delivery order is ready)
            DB::afterCommit(fn () => $this->alerts->statusChanged($order, $to->value));
            $this->tellStore($order, "ℹ️ Buyurtma #{$order->short_code} holati RIZQIM jamoasi tomonidan o'zgartirildi: {$result->from->label()} → {$to->label()}.");
        }

        return $result;
    }

    /**
     * @param  'store'|'customer'  $to
     * @return array{recipients: int}
     *
     * @throws AdminOrderException
     */
    public function message(Order $order, User $member, string $to, string $text): array
    {
        if (!$this->telegram->isConfigured()) {
            throw new AdminOrderException("Telegram bot sozlanmagan: xabar yuborib bo'lmaydi.", 'telegram_not_configured', 503);
        }

        $chatIds = $to === 'store' ? $this->storeChats($order) : $this->customerChats($order);
        if ($chatIds === []) {
            throw new AdminOrderException(
                $to === 'store' ? "Ovqatxonaning Telegram chati topilmadi." : "Mijozning Telegram akkaunti yo'q.",
                'no_recipient',
            );
        }

        $body = ($to === 'store' ? "📣 RIZQIM jamoasidan xabar\nBuyurtma #{$order->short_code}\n\n" : "📣 RIZQIM\nBuyurtmangiz #{$order->short_code} bo'yicha:\n\n") . trim($text);
        foreach ($chatIds as $chatId) {
            // Plain text (no parse mode): whatever the member typed reaches the chat as it is
            $this->telegram->sendMessage($chatId, $body, [], [
                'user_id' => $to === 'customer' ? $order->user_id : null,
                'order_id' => $order->id,
                'type' => $to === 'store' ? 'admin_message_store' : 'admin_message_customer',
            ]);
        }

        return ['recipients' => count($chatIds)];
    }

    /**
     * @return list<string>
     */
    public function storeChats(Order $order): array
    {
        return $this->cards->orderCardChatIds($order);
    }

    /**
     * @return list<string>
     */
    public function customerChats(Order $order): array
    {
        $telegramId = $order->user?->telegram_id;

        return $telegramId !== null && $telegramId !== '' && !str_starts_with((string) $telegramId, 'temp_') ? [(string) $telegramId] : [];
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws AdminOrderException
     */
    private function transition(Order $order, OrderStatus $to, User $member, array $options): OrderTransitionResult
    {
        try {
            return $this->orders->transition($order, $to, $member, $options);
        } catch (OrderTransitionException $e) {
            throw new AdminOrderException($e->getMessage(), $e->reason, $e->status === 403 ? 403 : 422);
        }
    }

    private function tellStore(Order $order, string $text): void
    {
        if (!$this->telegram->isConfigured()) {
            return;
        }

        $order->loadMissing('store');
        foreach ($this->storeChats($order) as $chatId) {
            $this->telegram->sendMessage($chatId, $text, [], ['order_id' => $order->id, 'type' => 'admin_order_notice']);
        }
    }
}
