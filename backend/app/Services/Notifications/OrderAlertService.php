<?php

namespace App\Services\Notifications;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
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
        protected TelegramMessenger $telegram
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
                    $this->send($manager->telegram_id, $text, $order->id, $manager->id, 'telegram_manager_new_order');
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
            if (!$this->telegram->isConfigured()) {
                return;
            }

            // READY -> notify branch manager OR courier
            if ($status === 'ready') {
                list($displayAddress, $keyboard) = $this->addressAndKeyboard($order->delivery_address, $order->shipping_address);

                // If a courier is assigned, notify that specific courier
                if ($order->courier_id && $order->courier?->telegram_id) {
                    $text = "📦 *Buyurtma tayyor!*\n\n"
                          . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                          . "📍 *Manzil:* {$displayAddress}\n"
                          . "Yetkazib berishni boshlashingiz mumkin.";
                    $this->send($order->courier->telegram_id, $text, $order->id, $order->courier_id, 'telegram_courier_ready', $keyboard);
                } else {
                    // Otherwise notify all couriers in this branch
                    $couriers = User::where('store_id', $order->store_id)
                        ->where('role', UserRole::COURIER->value)
                        ->where('branch_id', $order->branch_id)
                        ->get();
                    if ($couriers->isNotEmpty()) {
                        foreach ($couriers as $courier) {
                            if ($courier->telegram_id) {
                                $text = "🔔 *Yangi tayyor buyurtma! (Kuryer biriktirilmagan)*\n\n"
                                      . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                                      . "📍 *Manzil:* {$displayAddress}\n"
                                      . "Menejer orqali o'zingizni biriktirishingizni so'rang.";
                                $this->send($courier->telegram_id, $text, $order->id, $courier->id, 'telegram_branch_couriers_ready', $keyboard);
                            }
                        }
                    } else {
                        // Fallback: if no couriers are registered, notify managers/owners as they act as couriers
                        $managers = User::where('store_id', $order->store_id)
                            ->where('role', UserRole::MANAGER->value)
                            ->whereNotNull('telegram_id')
                            ->get();

                        $fallback = $managers->isNotEmpty() ? $managers : User::where('store_id', $order->store_id)
                            ->where('role', UserRole::OWNER->value)
                            ->whereNotNull('telegram_id')
                            ->get();

                        foreach ($fallback as $staff) {
                            $text = "📦 *Buyurtma tayyor! (Kuryer yo'qligi sababli sizga yuborildi)*\n\n"
                                  . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                                  . "📍 *Manzil:* {$displayAddress}\n"
                                  . "Yetkazib berishni boshlashingiz mumkin.";
                            $this->send($staff->telegram_id, $text, $order->id, $staff->id, 'telegram_courier_ready', $keyboard);
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
            if ($this->telegram->isConfigured() && $courier?->telegram_id) {
                list($displayAddress, $keyboard) = $this->addressAndKeyboard($order->delivery_address, $order->shipping_address);

                $text = "🚚 *Yangi buyurtma biriktirildi!*\n\n"
                      . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                      . "📍 *Manzil:* {$displayAddress}\n"
                      . "💰 *Jami summa:* " . number_format((float) $order->total_price, 0, '.', ' ') . " UZS\n\n"
                      . "Ilovadan buyurtmani qabul qiling va yo'lga chiqing! 🚀";

                $this->send($courier->telegram_id, $text, $order->id, $courier->id, 'telegram_courier_assigned', $keyboard);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send telegram notification to courier {$courier?->id}: " . $e->getMessage());
        }
    }

    protected function send(string $chatId, string $text, int $orderId, int $userId, string $type, array $replyMarkup = []): void
    {
        $this->telegram->sendMessage($chatId, $text, ['parse_mode' => 'Markdown', 'reply_markup' => $replyMarkup], [
            'user_id' => $userId,
            'order_id' => $orderId,
            'type' => $type,
        ]);
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
