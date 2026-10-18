<?php

namespace App\Services\Notifications;

use App\Models\Order;
use App\Services\Telegram\TelegramMessenger;

/**
 * Order cards (with one-time action buttons), customer status alerts and employee invites. Messages and their
 * TelegramAction tokens are built here; sending is queued through TelegramMessenger.
 */
class TelegramNotificationService
{
    public function __construct(
        protected TelegramMessenger $telegram
    ) {}

    public function sendOrderCreated(Order $order): void
    {
        $store = $order->store;
        
        if (!$store) {
            return;
        }

        if (!$this->telegram->isConfigured()) {
            return;
        }

        list($message, $keyboard) = $this->buildMessageAndKeyboard($order);

        $sentChatIds = [];

        // 1. Send to store admin chat if set
        if ($store->telegram_admin_chat_id) {
            $this->sendMessage($store->telegram_admin_chat_id, $message, $keyboard);
            $sentChatIds[] = $store->telegram_admin_chat_id;
        }

        // 2. Managers who may act on this order (its branch's and store-wide ones), so no button answers "ruxsat yo'q"
        $managers = $this->managersForOrder($order);

        if ($managers->isNotEmpty()) {
            // If managers are registered, send to them
            foreach ($managers as $manager) {
                if (!in_array($manager->telegram_id, $sentChatIds)) {
                    $this->sendMessage($manager->telegram_id, $message, $keyboard);
                    $sentChatIds[] = $manager->telegram_id;
                }
            }
        } else {
            // Otherwise, send to owners
            $owners = \App\Models\User::where('store_id', $store->id)
                ->where('role', \App\Enums\UserRole::OWNER->value)
                ->whereNotNull('telegram_id')
                ->get();

            foreach ($owners as $owner) {
                if (!in_array($owner->telegram_id, $sentChatIds)) {
                    $this->sendMessage($owner->telegram_id, $message, $keyboard);
                    $sentChatIds[] = $owner->telegram_id;
                }
            }
        }
    }

    /**
     * Managers with Telegram whom OrderPolicy lets act on the order: its branch's managers and store-wide managers.
     */
    protected function managersForOrder(Order $order): \Illuminate\Support\Collection
    {
        return \App\Models\User::where('store_id', $order->store_id)
            ->where('role', \App\Enums\UserRole::MANAGER->value)
            ->whereNotNull('telegram_id')
            ->where(function ($query) use ($order) {
                $query->whereNull('branch_id')
                    ->when($order->branch_id, fn ($query) => $query->orWhere('branch_id', $order->branch_id));
            })
            ->get();
    }

    public function sendEmployeeInvite(\App\Models\User $employee, \App\Models\User $owner): void
    {
        $chatId = $owner->telegram_id;

        if (!$this->telegram->isConfigured() || !$chatId) {
            return;
        }

        $botUsername = config('services.telegram.bot_username');
        $inviteLink = "https://t.me/{$botUsername}?start=invite_{$employee->telegram_invite_token}";

        $message = "👤 <b>Yangi xodim qo'shildi!</b>\n\n" .
                   "Ismi: <b>" . htmlspecialchars($employee->name) . "</b>\n" .
                   "Roli: <b>" . htmlspecialchars(strtoupper($employee->role->value ?? $employee->role)) . "</b>\n" .
                   "Filial: <b>" . htmlspecialchars($employee->branch?->name ?? 'Biriktirilmagan') . "</b>\n\n" .
                   "🔗 <b>Ushbu linkni xodimga yuboring:</b>\n" .
                   "{$inviteLink}\n\n" .
                   "<i>Xodim ushbu link ustiga bosib botga kirishi bilan tizimga ulanadi.</i>";

        $this->sendMessage($chatId, $message, [], 'HTML');
    }
    
    public function sendOrderStatusChanged(Order $order): void
    {
        $statusValue = $order->status->value ?? $order->status;
        if (!$this->telegram->isConfigured()) return;

        // 1. Notify Customer about status change if they have a telegram_id (the only customer alert of a transition)
        if ($order->user && $order->user->telegram_id) {
            $canceledText = "❌ *Buyurtmangiz bekor qilindi.*\n🆔 ID: #{$order->short_code}\n😔 Agar xatolik bo'lgan bo'lsa, iltimos biz bilan bog'laning.";

            if ($order->type !== 'delivery') {
                $statusTexts = [
                    'new' => "🆕 *Buyurtmangiz muvaffaqiyatli qabul qilindi!*\n🆔 ID: #{$order->short_code}",
                    'accepted' => "✅ *Buyurtmangiz qabul qilindi!*\n🆔 ID: #{$order->short_code}\n👨‍🍳 Oshxonamiz tez orada uni tayyorlashni boshlaydi.",
                    'preparing' => "🍳 *Buyurtmangiz tayyorlanmoqda!*\n🆔 ID: #{$order->short_code}\n🔥 Oshpazlarimiz siz uchun eng shirin taomlarni tayyorlashmoqda.",
                    'delivered' => "🎉 *Buyurtmangiz tayyor, olib ketishingiz mumkin!*\n🆔 ID: #{$order->short_code}\n🏪 Biz sizni kutmoqdamiz!",
                    'canceled' => $canceledText,
                    'rejected' => $canceledText,
                    'refunded' => $this->refundText($order),
                ];
            } else {
                $courier = in_array($statusValue, ['delivery', 'on_the_way'], true) ? $order->courier : null;
                $onTheWay = "🚚 *Buyurtmangiz yo'lga chiqdi!*\n🆔 ID: #{$order->short_code}\n🚴 Kuryerimiz uni sizga yetkazish uchun tezlikda yo'lga chiqdi."
                    . ($courier ? "\n👤 *Kuryer:* {$courier->name}" . ($courier->phone ? " ({$courier->phone})" : '') : '');

                $statusTexts = [
                    'new' => "🆕 *Buyurtmangiz muvaffaqiyatli qabul qilindi!*\n🆔 ID: #{$order->short_code}",
                    'accepted' => "✅ *Buyurtmangiz qabul qilindi!*\n🆔 ID: #{$order->short_code}\n👨‍🍳 Oshxonamiz tez orada uni tayyorlashni boshlaydi.",
                    'preparing' => "🍳 *Buyurtmangiz tayyorlanmoqda!*\n🆔 ID: #{$order->short_code}\n🔥 Oshpazlarimiz siz uchun eng shirin taomlarni tayyorlashmoqda.",
                    'delivery' => $onTheWay,
                    'on_the_way' => $onTheWay,
                    'delivered' => "🎉 *Buyurtmangiz muvaffaqiyatli yetkazildi!*\n🆔 ID: #{$order->short_code}\n😋 Yoqimli ishtaha tilaymiz! Bizni tanlaganingiz uchun rahmat!",
                    'canceled' => $canceledText,
                    'rejected' => $canceledText,
                    'refunded' => $this->refundText($order),
                ];
            }

            $customerMsg = $statusTexts[$statusValue] ?? "🔔 *Buyurtmangiz holati o'zgardi:* " . strtoupper($statusValue);

            if ($order->type !== 'delivery' && !in_array($statusValue, ['canceled', 'rejected', 'refunded'], true)) {
                $cafeName = $order->branch?->name ?: ($order->store?->name ?? 'Kafe');
                $cafeAddress = $order->branch?->address ?: ($order->store?->address ?: 'Ko\'rsatilmagan');
                $cafePhone = $order->branch?->phone;
                if (!$cafePhone) {
                    $manager = \App\Models\User::where('store_id', $order->store_id)
                        ->whereIn('role', [\App\Enums\UserRole::MANAGER->value, \App\Enums\UserRole::OWNER->value])
                        ->whereNotNull('phone')
                        ->first();
                    $cafePhone = $manager?->phone ?: 'Ko\'rsatilmagan';
                }

                $customerMsg .= "\n\n🏪 *Olib ketish manzili:*\n";
                $customerMsg .= "🏢 Kafe: *{$cafeName}*\n";
                $customerMsg .= "📍 Manzil: {$cafeAddress}\n";
                $customerMsg .= "📞 Tel: {$cafePhone}";
            }
            
            // Add a button to open the webapp to track or view
            $webAppUrl = $this->webAppUrl();
            $customerKeyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📱 Buyurtmani ko\'rish (Ilova)', 'web_app' => ['url' => $webAppUrl]]
                    ]
                ]
            ];

            // These statuses also land in the customer's in-app feed, under the types it has always shown
            $feedType = match ($statusValue) {
                'accepted' => 'telegram_customer_accepted',
                'delivery', 'on_the_way' => 'telegram_customer_on_the_way',
                'delivered' => 'telegram_customer_delivered',
                'canceled', 'rejected' => 'telegram_customer_canceled',
                default => null,
            };

            $this->telegram->sendMessage(
                $order->user->telegram_id,
                $customerMsg,
                ['parse_mode' => 'Markdown', 'reply_markup' => $customerKeyboard],
                $feedType ? ['user_id' => $order->user->id, 'order_id' => $order->id, 'type' => $feedType] : null
            );
        }

        // 2. If the order is sent for delivery, notify courier (or fallback to managers/owners if no courier is assigned)
        if ($statusValue === 'delivery' || $statusValue === 'on_the_way') {
            $recipients = [];
            
            if ($order->courier && $order->courier->telegram_id) {
                $recipients[] = $order->courier;
            } else {
                // Find managers with telegram_id who may complete this order
                $managers = $this->managersForOrder($order);
                    
                if ($managers->isNotEmpty()) {
                    foreach ($managers as $manager) {
                        $recipients[] = $manager;
                    }
                } else {
                    // Fallback to owners with telegram_id
                    $owners = \App\Models\User::where('store_id', $order->store_id)
                        ->where('role', \App\Enums\UserRole::OWNER->value)
                        ->whereNotNull('telegram_id')
                        ->get();
                        
                    foreach ($owners as $owner) {
                        $recipients[] = $owner;
                    }
                }
            }

            if (!empty($recipients)) {
                $courierAction = ($statusValue === 'courier_assigned') ? 'take' : 'complete';
                $courierButtonText = ($statusValue === 'courier_assigned') ? '🚴 Yo\'lga chiqdim' : '✅ Yetkazildi / Topshirdim';

                $token = \Illuminate\Support\Str::random(32);
                \App\Models\TelegramAction::create([
                    'order_id' => $order->id,
                    'action' => $courierAction,
                    'token' => $token,
                    'expires_at' => now()->addHours(24),
                ]);

                foreach ($recipients as $recipient) {
                    $isFallback = !($order->courier && $order->courier->id === $recipient->id);
                    if ($statusValue === 'courier_assigned') {
                        $message = "🚴 *Sizga yangi buyurtma biriktirildi!* " . 
                            ($isFallback ? "_(Kuryer yo'qligi sababli sizga yuborildi)_" : "") . "\n\n";
                    } else {
                        $message = "🚚 *Buyurtma yo'lda!* " . 
                            ($isFallback ? "_(Kuryer yo'qligi sababli sizga yuborildi)_" : "") . "\n\n";
                    }
                    $message .= "🆔 *Buyurtma:* #{$order->short_code}\n";
                    $message .= "👤 *Mijoz:* " . ($order->user->name ?? 'Mijoz') . "\n";
                    $message .= "📞 *Telefon:* " . ($order->user->phone ?? 'Kiritilmagan') . "\n";
                    $message .= "💰 *Jami summa:* " . number_format($order->total_price, 0, '.', ' ') . " UZS\n";
                    
                    $paymentMethodText = match(strtolower($order->payment_method ?? 'cash')) {
                        'cash' => 'Naqd pul 💵',
                        'card', 'terminal' => 'Plastik karta 💳',
                        'click' => 'Click 📱',
                        'payme' => 'Payme 📱',
                        'paynet' => 'Paynet 📱',
                        'uzum' => 'Uzum Bank 📱',
                        default => strtoupper($order->payment_method)
                    };
                    $message .= "💳 *To'lov usuli:* {$paymentMethodText}\n";

                    $buttons = [];

                    if ($order->shipping_address) {
                        $addressText = $order->shipping_address['address'] ?? "Ko'rsatilmagan";
                        $lat = $order->shipping_address['latitude'] ?? null;
                        $lng = $order->shipping_address['longitude'] ?? null;

                        if ($lat && $lng) {
                            $url = "https://yandex.com/maps/?pt={$lng},{$lat}&z=16&l=map";
                            $displayAddress = $addressText ?: "GPS lokatsiya 📍";
                        } elseif (preg_match('/(https?:\/\/[^\s]+)/', $addressText, $matches)) {
                            $url = $matches[1];
                            $cleanAddress = trim(str_replace(['📍 Lokatsiya:', 'Lokatsiya:', $url], '', $addressText));
                            $cleanAddress = trim(preg_replace('/\s+/', ' ', $cleanAddress));
                            $displayAddress = $cleanAddress ?: "GPS lokatsiya 📍";
                        } else {
                            $url = "https://yandex.com/maps/?text=" . urlencode($addressText);
                            $displayAddress = $addressText;
                        }

                        $message .= "📍 *Manzil:* {$displayAddress}\n\n";
                        $buttons[] = [
                            ['text' => '🗺️ Yandex Navigatorda ochish', 'url' => $url]
                        ];
                    } else {
                        $message .= "📍 *Manzil:* Ko'rsatilmagan\n\n";
                    }

                    if ($order->notes) {
                        $message .= "📝 *Izoh:* {$order->notes}\n\n";
                    }

                    $buttons[] = [
                        ['text' => $courierButtonText, 'callback_data' => $token]
                    ];

                    $keyboard = ['inline_keyboard' => $buttons];

                    $this->sendMessage(
                        $recipient->telegram_id,
                        $message,
                        $keyboard
                    );
                }
            }
        }
    }

    public function updateOrderTelegramMessage(Order $order, ?string $actorName = null, ?string $messageId = null, ?string $chatId = null): void
    {
        if (!$this->telegram->isConfigured() || !$chatId || !$messageId) {
            return;
        }

        list($message, $keyboard) = $this->buildMessageAndKeyboard($order, $actorName);

        $this->telegram->editMessageText($chatId, $messageId, $message, [
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    public function buildMessageAndKeyboard(Order $order, ?string $actorName = null): array
    {
        $statusValue = $order->status->value ?? $order->status;

        $statusHeader = match($statusValue) {
            'new' => "🆕 *Yangi buyurtma #{$order->short_code}*",
            'accepted' => "✅ *Buyurtma qabul qilindi #{$order->short_code}*",
            'preparing' => "🍳 *Tayyorlanmoqda #{$order->short_code}*",
            'courier_assigned' => "🚴 *Kuryer biriktirildi #{$order->short_code}*",
            'delivery' => "🚚 *Yo'lga chiqdi #{$order->short_code}*",
            'on_the_way' => "🚚 *Yo'lga chiqdi #{$order->short_code}*",
            'delivered' => "🎉 *Yetkazildi #{$order->short_code}*",
            'canceled' => "❌ *Bekor qilindi #{$order->short_code}*",
            default => "*Buyurtma #{$order->short_code}*",
        };

        $message = "{$statusHeader}\n\n";
        $message .= "👤 {$order->user->name}\n";
        $message .= "💰 *" . number_format($order->total_price, 0, '.', ' ') . " UZS*\n";
        $message .= $order->type === 'delivery' ? "🚚 Yetkazib berish\n" : "🏪 Olib ketish\n";
        
        if ($order->type === 'delivery' && $order->delivery_address) {
            $addressText = $order->delivery_address;
            $lat = $order->shipping_address['latitude'] ?? null;
            $lng = $order->shipping_address['longitude'] ?? null;

            if ($lat && $lng) {
                $url = "https://yandex.com/maps/?pt={$lng},{$lat}&z=16&l=map";
                $displayAddress = $addressText ?: "GPS lokatsiya";
            } elseif (preg_match('/(https?:\/\/[^\s]+)/', $addressText, $matches)) {
                $url = $matches[1];
                $cleanAddress = trim(str_replace(['📍 Lokatsiya:', 'Lokatsiya:', $url], '', $addressText));
                $cleanAddress = trim(preg_replace('/\s+/', ' ', $cleanAddress));
                $displayAddress = $cleanAddress ?: "GPS lokatsiya";
            } else {
                $url = "https://yandex.com/maps/?text=" . urlencode($addressText);
                $displayAddress = $addressText;
            }
            $message .= "📍 {$displayAddress}\n";
        }
        
        if ($order->notes) {
            $message .= "📝 {$order->notes}\n";
        }
        
        $message .= "\n";
        
        foreach ($order->items as $item) {
            $variant = $item->variant_name ? " ({$item->variant_name})" : "";
            $message .= "🍔 {$item->quantity}x {$item->product_name}{$variant}\n";
        }
        
        $message .= "\n⏰ " . $order->created_at->format('H:i');
        
        if ($actorName) {
            $message .= "\n\n👤 *Last Action by:* {$actorName}";
        }

        // Inline Keyboard builder with one-time tokens
        $buttons = [];

        $createToken = function($action) use ($order) {
            $token = \Illuminate\Support\Str::random(32);
            \App\Models\TelegramAction::create([
                'order_id' => $order->id,
                'action' => $action,
                'token' => $token,
                'expires_at' => now()->addHours(24),
            ]);
            return $token;
        };

        switch($statusValue) {
            case 'new':
                $buttons[] = [
                    ['text' => '✅ Qabul qilish', 'callback_data' => $createToken('accept')],
                    ['text' => '❌ Rad etish', 'callback_data' => $createToken('cancel')]
                ];
                break;
            case 'accepted':
                $buttons[] = [
                    ['text' => '🍳 Tayyorlashni boshlash', 'callback_data' => $createToken('prepare')]
                ];
                break;
            case 'preparing':
                if ($order->type === 'delivery') {
                    $buttons[] = [
                        ['text' => '🚚 Yetkazishga berish', 'callback_data' => $createToken('deliver')]
                    ];
                } else {
                    $buttons[] = [
                        ['text' => '✅ Olib ketishga tayyor', 'callback_data' => $createToken('complete')]
                    ];
                }
                break;
            case 'courier_assigned':
                $buttons[] = [
                    ['text' => '🚚 Yo\'lga chiqdi', 'callback_data' => $createToken('take')]
                ];
                break;
            case 'delivery':
            case 'on_the_way':
                $buttons[] = [
                    ['text' => '✅ Yetkazildi', 'callback_data' => $createToken('complete')]
                ];
                break;
        }

        if ($order->type === 'delivery') {
            $addressText = $order->delivery_address;
            $lat = $order->shipping_address['latitude'] ?? null;
            $lng = $order->shipping_address['longitude'] ?? null;
            $url = null;

            if ($lat && $lng) {
                $url = "https://yandex.com/maps/?pt={$lng},{$lat}&z=16&l=map";
            } elseif ($addressText && preg_match('/(https?:\/\/[^\s]+)/', $addressText, $matches)) {
                $url = $matches[1];
            } elseif ($addressText) {
                $url = "https://yandex.com/maps/?text=" . urlencode($addressText);
            }

            if ($url) {
                $buttons[] = [
                    ['text' => '🗺️ Yandex Navigatorda ochish', 'url' => $url]
                ];
            }
        }

        // Always add open in mini app link at the bottom
        $webAppUrl = $this->webAppUrl();
        $buttons[] = [
            ['text' => '📱 Mini ilovada ochish', 'web_app' => ['url' => rtrim($webAppUrl, '/') . '/manager']]
        ];

        $keyboard = ['inline_keyboard' => $buttons];

        return [$message, $keyboard];
    }

    protected function refundText(Order $order): string
    {
        $amount = (float) $order->total_price;

        return "💸 *Buyurtmangiz bo'yicha pul qaytarildi*\n🆔 ID: #{$order->short_code}"
            . ($amount > 0 ? "\n💰 Summa: *" . number_format($amount, 0, '.', ' ') . " UZS*" : '')
            . ($order->payment_method === 'keshbek' ? "\n👛 Mablag' keshbek balansingizga qaytarildi." : '');
    }

    protected function sendMessage(string $chatId, string $text, array $replyMarkup = [], string $parseMode = 'Markdown'): void
    {
        $this->telegram->sendMessage($chatId, $text, [
            'parse_mode' => $parseMode,
            'reply_markup' => $replyMarkup,
        ]);
    }

    /**
     * From config, not env(): env() reads nothing once the config is cached, which left buttons on the fallback URL.
     */
    protected function webAppUrl(): string
    {
        return config('services.telegram.webapp_url') ?: 'https://chatty-bikes-say.loca.lt';
    }
}
