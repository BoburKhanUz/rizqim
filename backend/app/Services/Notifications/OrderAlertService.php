<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Push\OrderPushMessages;
use App\Services\Push\PushContent;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Support\Facades\Log;

/**
 * Telegram alerts to staff about orders: new order and ready (sent by the Mini App's endpoints) and the courier's
 * assignment (sent by the SendCourierAssignedAlert listener, whichever way the courier was assigned). Each one is
 * queued with its NotificationLog type, so the recipient's in-app notification feed shows it as well. The customer
 * hears about every transition from the OrderStatusUpdated/OrderCancelled listeners instead.
 */
class OrderAlertService
{
    public function __construct(
        protected TelegramMessenger $telegram,
        protected NotificationRouter $router,
        protected OrderPushMessages $pushMessages,
    ) {}

    /**
     * The managers of the order's branch (store-wide managers for an order without a branch).
     */
    public function newOrder(Order $order): void
    {
        try {
            if (!$this->telegram->isConfigured()) {
                return;
            }

            $managers = User::where('store_id', $order->store_id)
                ->where('role', UserRole::MANAGER->value)
                ->where('branch_id', $order->branch_id)
                ->get();

            foreach ($managers as $manager) {
                if ($manager->telegram_id) {
                    $typeText = $order->type === 'delivery' ? 'ETKAZIB BERISH' : 'OLIB KETISH';
                    $text = "🔔 *Yangi buyurtma keldi!*\n\n"
                          . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                          . "📍 *Yetkazish turi:* {$typeText}\n"
                          . "💰 *Jami Summa:* " . number_format((float) $order->total_price, 0, '.', ' ') . " UZS\n\n"
                          . "Ilovaga kirib qabul qiling.";
                    $this->send($manager, NotificationType::STAFF_NEW_ORDER, $text, $order->id, 'telegram_manager_new_order');
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to notify managers: " . $e->getMessage());
        }
    }

    /**
     * Whoever has to act next after the order reached $status.
     */
    public function statusChanged(Order $order, string $status): void
    {
        try {
            // READY -> notify branch manager OR courier
            if ($status === 'ready') {
                list($displayAddress, $keyboard) = $this->addressAndKeyboard($order->delivery_address, $order->shipping_address);

                // If a courier is assigned, notify that specific courier
                if ($order->courier_id && $order->courier && $this->router->reachable($order->courier)) {
                    $text = "📦 *Buyurtma tayyor!*\n\n"
                          . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                          . "📍 *Manzil:* {$displayAddress}\n"
                          . "Yetkazib berishni boshlashingiz mumkin.";
                    $this->send($order->courier, NotificationType::COURIER_TASKS, $text, $order->id, 'telegram_courier_ready', $keyboard, $this->pushMessages->forCourier($order, assigned: false));
                } else {
                    // Otherwise notify all couriers in this branch
                    $couriers = $this->router->onlyReachable(User::where('store_id', $order->store_id)
                        ->where('role', UserRole::COURIER->value)
                        ->where('branch_id', $order->branch_id))
                        ->get();
                    if ($couriers->isNotEmpty()) {
                        foreach ($couriers as $courier) {
                            $text = "🔔 *Yangi tayyor buyurtma! (Kuryer biriktirilmagan)*\n\n"
                                  . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                                  . "📍 *Manzil:* {$displayAddress}\n"
                                  . "Menejer orqali o'zingizni biriktirishingizni so'rang.";
                            $this->send($courier, NotificationType::COURIER_TASKS, $text, $order->id, 'telegram_branch_couriers_ready', $keyboard, $this->pushMessages->forCourier($order, assigned: false));
                        }
                    } else {
                        // Fallback: if no couriers are registered, notify managers/owners as they act as couriers
                        $managers = $this->router->onlyReachable(User::where('store_id', $order->store_id)
                            ->where('role', UserRole::MANAGER->value))
                            ->get();

                        $fallback = $managers->isNotEmpty() ? $managers : $this->router->onlyReachable(User::where('store_id', $order->store_id)
                            ->where('role', UserRole::OWNER->value))
                            ->get();

                        foreach ($fallback as $staff) {
                            $text = "📦 *Buyurtma tayyor! (Kuryer yo'qligi sababli sizga yuborildi)*\n\n"
                                  . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                                  . "📍 *Manzil:* {$displayAddress}\n"
                                  . "Yetkazib berishni boshlashingiz mumkin.";
                            $this->send($staff, NotificationType::COURIER_TASKS, $text, $order->id, 'telegram_courier_ready', $keyboard, $this->pushMessages->forCourier($order, assigned: false));
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to send telegram notification flow: " . $e->getMessage());
        }
    }

    /**
     * The courier (or stand-in) the order was just handed to; nothing without one or without their Telegram account.
     * Only SendCourierAssignedAlert calls it, so each assignment is announced once.
     */
    public function courierAssigned(Order $order, ?User $courier): void
    {
        try {
            if ($courier !== null && $this->router->reachable($courier)) {
                list($displayAddress, $keyboard) = $this->addressAndKeyboard($order->delivery_address, $order->shipping_address);

                $text = "🚚 *Yangi buyurtma biriktirildi!*\n\n"
                      . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                      . "📍 *Manzil:* {$displayAddress}\n"
                      . "💰 *Jami summa:* " . number_format((float) $order->total_price, 0, '.', ' ') . " UZS\n\n"
                      . "Ilovadan buyurtmani qabul qiling va yo'lga chiqing! 🚀";

                $this->send($courier, NotificationType::COURIER_TASKS, $text, $order->id, 'telegram_courier_assigned', $keyboard, $this->pushMessages->forCourier($order, assigned: true));
            }
        } catch (\Exception $e) {
            Log::error("Failed to send telegram notification to courier {$order->courier_id}: " . $e->getMessage());
        }
    }

    /**
     * Telegram (as always) and, when given, the app push, each over the channels the recipient keeps on for $type.
     */
    protected function send(User $recipient, NotificationType $type, string $text, int $orderId, string $logType, array $replyMarkup = [], ?PushContent $push = null): void
    {
        $this->router->send($recipient, new NotificationMessage(
            $type,
            telegram: new TelegramContent($text, ['parse_mode' => 'Markdown', 'reply_markup' => $replyMarkup], [
                'user_id' => $recipient->id,
                'order_id' => $orderId,
                'type' => $logType,
            ]),
            push: $push,
        ));
    }

    /**
     * The address line for a message and a "Yandex Navigator" button: GPS point, a map link inside the text, or a search.
     */
    protected function addressAndKeyboard(?string $addressText, ?array $shippingAddress = null): array
    {
        $displayAddress = $addressText ?: "Ko'rsatilmagan";
        $keyboard = [];

        $lat = $shippingAddress['latitude'] ?? null;
        $lng = $shippingAddress['longitude'] ?? null;

        if ($lat && $lng) {
            $url = "https://yandex.com/maps/?pt={$lng},{$lat}&z=16&l=map";
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🗺️ Yandex Navigatorda ochish', 'url' => $url]
                    ]
                ]
            ];
        } elseif ($addressText) {
            if (preg_match('/(https?:\/\/[^\s]+)/', $addressText, $matches)) {
                $url = $matches[1];
                $cleanAddress = trim(str_replace(['📍 Lokatsiya:', 'Lokatsiya:', $url], '', $addressText));
                $cleanAddress = trim(preg_replace('/\s+/', ' ', $cleanAddress));
                $displayAddress = $cleanAddress ?: "GPS lokatsiya";
            } else {
                $url = "https://yandex.com/maps/?text=" . urlencode($addressText);
                $displayAddress = $addressText;
            }

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🗺️ Yandex Navigatorda ochish', 'url' => $url]
                    ]
                ]
            ];
        }

        return [$displayAddress, $keyboard];
    }
}
