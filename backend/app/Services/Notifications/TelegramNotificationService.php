<?php

namespace App\Services\Notifications;

use App\Enums\CancelReason;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\Order;
use App\Services\Push\OrderPushMessages;
use App\Services\Telegram\TelegramMessenger;

/**
 * Order cards (with one-time action buttons), customer status alerts and employee invites. Messages and their
 * TelegramAction tokens are built here; sending is queued through TelegramMessenger.
 */
class TelegramNotificationService
{
    public function __construct(
        protected TelegramMessenger $telegram,
        protected NotificationRouter $router,
        protected NotificationPreferenceService $preferences,
        protected OrderPushMessages $pushMessages,
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

        // The store's order chat, and the managers who may act on the order (else its owners), so no button answers
        // "ruxsat yo'q"
        foreach ($this->orderCardChatIds($order) as $chatId) {
            $this->sendMessage($chatId, $message, $keyboard);
        }
    }

    /**
     * Managers with Telegram whom OrderPolicy lets act on the order: its branch's managers and store-wide managers.
     * Never a blocked one (review W3 S#3: the card carries the customer's name, phone and address).
     */
    protected function managersForOrder(Order $order): \Illuminate\Support\Collection
    {
        return $this->router->withoutBlocked(\App\Models\User::where('store_id', $order->store_id)
            ->where('role', \App\Enums\UserRole::MANAGER->value)
            ->whereNotNull('telegram_id')
            ->where(function ($query) use ($order) {
                $query->whereNull('branch_id')
                    ->when($order->branch_id, fn ($query) => $query->orWhere('branch_id', $order->branch_id));
            }))
            ->get();
    }

    /**
     * The store's owners with Telegram (who stand in when no manager may act), never a blocked one.
     */
    protected function ownersForOrder(Order $order): \Illuminate\Support\Collection
    {
        return $this->router->withoutBlocked(\App\Models\User::where('store_id', $order->store_id)
            ->where('role', \App\Enums\UserRole::OWNER->value)
            ->whereNotNull('telegram_id'))
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
                   "Filial: <b>" . htmlspecialchars($employee->branch->name ?? 'Biriktirilmagan') . "</b>\n\n" .
                   "🔗 <b>Ushbu linkni xodimga yuboring:</b>\n" .
                   "{$inviteLink}\n\n" .
                   "<i>Xodim ushbu link ustiga bosib botga kirishi bilan tizimga ulanadi.</i>";

        $this->sendMessage($chatId, $message, [], 'HTML');
    }
    
    public function sendOrderStatusChanged(Order $order): void
    {
        $statusValue = $order->status->value ?? $order->status;
        // P1-22: without the bot the customer can still get the push, so the Telegram parts are guarded one by one
        $telegramReady = $this->telegram->isConfigured();

        // 1. Notify Customer about status change if they have a telegram_id (the only customer alert of a transition)
        if ($order->user && $order->user->telegram_id && $telegramReady) {
            $canceledText = $this->cancelledText($order);

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
                // P6-13: a pickup order with a queue number is called by it (again on every call from the till)
                if ($order->queue_number !== null) {
                    $statusTexts['ready'] = "🔔 *Buyurtmangiz tayyor, raqam {$order->queue_number}!*\n🆔 ID: #{$order->short_code}\n🏪 Kassaga kelib, raqamingizni ayting.";
                }
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
                $cafeName = $order->branch?->name ?: ($order->store->name ?? 'Kafe');
                $cafeAddress = $order->branch?->address ?: ($order->store?->address ?: 'Ko\'rsatilmagan');
                // The branch's number, else the store's own, else a manager's/owner's
                $cafePhone = $order->branch?->phone ?: $order->store?->phone;
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
            
            // A button to open the Mini App to track or view the order (none without a configured Mini App URL)
            $appButton = $this->telegram->webAppButton('📱 Buyurtmani ko\'rish (Ilova)');
            $customerKeyboard = $appButton ? ['inline_keyboard' => [[$appButton]]] : [];

            // These statuses also land in the customer's in-app feed, under the types it has always shown
            $feedType = match ($statusValue) {
                'accepted' => 'telegram_customer_accepted',
                'delivery', 'on_the_way' => 'telegram_customer_on_the_way',
                'delivered' => 'telegram_customer_delivered',
                'canceled', 'rejected' => 'telegram_customer_canceled',
                default => null,
            };

            // Telegram and the app push, each only if the customer keeps that channel on for order updates
            $this->router->send($order->user, new NotificationMessage(
                NotificationType::ORDER_UPDATES,
                telegram: new TelegramContent(
                    $customerMsg,
                    ['parse_mode' => 'Markdown', 'reply_markup' => $customerKeyboard],
                    $feedType ? ['user_id' => $order->user->id, 'order_id' => $order->id, 'type' => $feedType] : null
                ),
                push: $this->pushMessages->forCustomer($order),
            ));
        } elseif ($order->user) {
            // No Telegram to tell (not linked, or the bot is off): the app push alone
            $this->router->send($order->user, new NotificationMessage(
                NotificationType::ORDER_UPDATES,
                push: $this->pushMessages->forCustomer($order),
            ));
        }

        // 2. If the order is sent for delivery, notify courier (or fallback to managers/owners if no courier is assigned).
        //    An assignment (courier_assigned) is announced to the courier by the SendCourierAssignedAlert listener.
        //    An app-only courier (P1-36, no Telegram) works the order in the courier app: nobody else is told "no courier"
        if ($telegramReady && ($statusValue === 'delivery' || $statusValue === 'on_the_way')) {
            $recipients = [];
            // A blocked courier gets nothing: the order goes to those who may finish it instead
            $courier = $order->courier && !$order->courier->isBlocked() ? $order->courier : null;

            if ($courier && $courier->hasTelegram()) {
                $recipients[] = $courier;
            } elseif ($courier) {
                // Nothing to send over Telegram: the courier's app shows the order and its "delivered" action
            } else {
                // Find managers with telegram_id who may complete this order
                $managers = $this->managersForOrder($order);
                    
                if ($managers->isNotEmpty()) {
                    foreach ($managers as $manager) {
                        $recipients[] = $manager;
                    }
                } else {
                    // Fallback to owners with telegram_id
                    $owners = $this->ownersForOrder($order);

                    foreach ($owners as $owner) {
                        $recipients[] = $owner;
                    }
                }
            }

            if (!empty($recipients)) {
                $token = \Illuminate\Support\Str::random(32);
                \App\Models\TelegramAction::create([
                    'order_id' => $order->id,
                    'action' => 'complete',
                    'token' => $token,
                    'expires_at' => now()->addHours(24),
                ]);

                foreach ($recipients as $recipient) {
                    $isFallback = !($courier && $courier->id === $recipient->id);
                    $message = "🚚 *Buyurtma yo'lda!* " .
                        ($isFallback ? "_(Kuryer yo'qligi sababli sizga yuborildi)_" : "") . "\n\n";
                    $message .= "🆔 *Buyurtma:* #{$order->short_code}\n";
                    $message .= "👤 *Mijoz:* " . ($order->user->name ?? 'Mijoz') . "\n";
                    $message .= "📞 *Telefon:* " . ($order->user->phone ?? 'Kiritilmagan') . "\n";
                    $message .= "💰 *Jami summa:* " . number_format((float) $order->total_price, 0, '.', ' ') . " UZS\n";
                    
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
                        ['text' => '✅ Yetkazildi / Topshirdim', 'callback_data' => $token]
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
            'rejected' => "❌ *Rad etildi #{$order->short_code}*",
            default => "*Buyurtma #{$order->short_code}*",
        };

        $message = "{$statusHeader}\n\n";
        $message .= "👤 {$order->user->name}\n";
        $message .= "💰 *" . number_format((float) $order->total_price, 0, '.', ' ') . " UZS*\n";
        $message .= $order->type === 'delivery' ? "🚚 Yetkazib berish\n" : "🏪 Olib ketish\n";
        
        // The address line; a pin without an address text (GPS only) still shows as one
        if ($order->type === 'delivery') {
            $addressText = (string) $order->delivery_address;
            $lat = $order->shipping_address['latitude'] ?? null;
            $lng = $order->shipping_address['longitude'] ?? null;
            $displayAddress = null;

            if ($lat && $lng) {
                $displayAddress = $addressText !== '' ? $addressText : "GPS lokatsiya";
            } elseif (preg_match('/(https?:\/\/[^\s]+)/', $addressText, $matches)) {
                $cleanAddress = trim(str_replace(['📍 Lokatsiya:', 'Lokatsiya:', $matches[1]], '', $addressText));
                $cleanAddress = trim(preg_replace('/\s+/', ' ', $cleanAddress));
                $displayAddress = $cleanAddress ?: "GPS lokatsiya";
            } elseif ($addressText !== '') {
                $displayAddress = $addressText;
            }

            if ($displayAddress !== null) {
                $message .= "📍 {$displayAddress}\n";
            }
        }
        
        if ($order->notes) {
            $message .= "📝 {$order->notes}\n";
        }
        
        $message .= "\n";
        
        foreach ($order->items as $item) {
            // P1-21: "2x Osh (Qazi)", "1.5 kg Somsa": weight and modifiers as sold
            $message .= "🍔 {$item->displayLine()}\n";
            // P6-31: a combo's parts under it, without prices
            foreach (\App\Domain\Pos\Menu\Combos::receiptParts($item->combo_parts) as $part) {
                $message .= "   • {$part['label']}\n";
            }
        }

        // P1-21: the POS hall's service charge and place fee, as their own lines when charged
        foreach (['service_charge_amount' => '🧾 Xizmat haqi', 'place_fee_amount' => '🪑 Joy narxi'] as $column => $label) {
            $amount = \App\Support\Money::of($order->{$column} ?? 0);
            if ($amount->isPositive()) {
                $message .= "{$label}: " . number_format($amount->toFloat(), 0, '.', ' ') . " UZS\n";
            }
        }
        
        $message .= "\n⏰ " . $order->created_at->format('H:i');

        if ($order->status->isCancellation() && $order->cancel_reason) {
            $message .= "\n🚫 *Sabab:* {$order->cancel_reason->label()}"
                . ($order->cancel_note ? "\n💬 " . $this->escapeMarkdown($order->cancel_note) : '');
        }
        
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
                // "Bekor qilish" after acceptance (e.g. a pickup the customer never collects): asks for the reason first
                $buttons[] = [
                    ['text' => '❌ Bekor qilish', 'callback_data' => $createToken('abort')]
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
                $buttons[] = [
                    ['text' => '❌ Bekor qilish', 'callback_data' => $createToken('abort')]
                ];
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

        // Open in the Mini App, at the bottom (left out without a configured Mini App URL)
        $appButton = $this->telegram->webAppButton('📱 Mini ilovada ochish', 'manager');
        if ($appButton) {
            $buttons[] = [$appButton];
        }

        $keyboard = ['inline_keyboard' => $buttons];

        return [$message, $keyboard];
    }

    /**
     * The reasons to pick from after "Rad etish"/"Bekor qilish" was pressed, two per row, and a way back to the card.
     * Each button's callback data is the pressed button's own token plus ":<reason>" (at most 32 + 1 + 19 bytes,
     * within Telegram's 64), so the choice is checked and authorised exactly like the button itself.
     *
     * @param list<CancelReason> $reasons
     */
    public function cancelReasonKeyboard(string $token, array $reasons): array
    {
        $buttons = array_map(fn (CancelReason $reason) => [
            'text' => $reason->label(),
            'callback_data' => "{$token}:{$reason->value}",
        ], $reasons);

        $rows = array_chunk($buttons, 2);
        $rows[] = [['text' => '⬅️ Orqaga', 'callback_data' => "{$token}:back"]];

        return ['inline_keyboard' => $rows];
    }

    /**
     * Staff on whom a new order's card lands: the store's order chat, and the managers who may act on it (else its
     * owners). Telegram chat ids, without repeats. Blocked staff are left out; the store's chat is the store's and
     * gets the card even when the owner is blocked.
     *
     * @return list<string>
     */
    public function orderCardChatIds(Order $order): array
    {
        $store = $order->store;
        if (!$store) {
            return [];
        }

        $chatIds = $store->telegram_admin_chat_id ? [(string) $store->telegram_admin_chat_id] : [];

        $staff = $this->managersForOrder($order);
        if ($staff->isEmpty()) {
            $staff = $this->ownersForOrder($order);
        }

        foreach ($staff as $user) {
            // Staff who switched Telegram off for new orders (P1-22) get the app push only
            if ($this->preferences->allows($user, NotificationType::STAFF_NEW_ORDER, NotificationChannel::TELEGRAM)) {
                $chatIds[] = (string) $user->telegram_id;
            }
        }

        return array_values(array_unique($chatIds));
    }

    /**
     * Tells the staff who got the new order's card that the customer cancelled it themselves.
     */
    public function sendCustomerCancelled(Order $order): void
    {
        if (!$this->telegram->isConfigured()) {
            return;
        }

        $message = "❌ *Mijoz buyurtmani bekor qildi #{$order->short_code}*\n\n"
            . "👤 " . $this->escapeMarkdown((string) ($order->user->name ?? 'Mijoz')) . "\n"
            . "💰 *" . number_format((float) $order->total_price, 0, '.', ' ') . " UZS*\n"
            . "🚫 *Sabab:* " . ($order->cancel_reason?->label() ?? CancelReason::CUSTOMER_REQUEST->label())
            . ($order->cancel_note ? "\n💬 " . $this->escapeMarkdown($order->cancel_note) : '');

        foreach ($this->orderCardChatIds($order) as $chatId) {
            $this->sendMessage($chatId, $message);
        }
    }

    /**
     * The customer's cancellation message, with the reason (and the store's note) when one was recorded.
     */
    protected function cancelledText(Order $order): string
    {
        $reason = $order->cancel_reason;

        return "❌ *Buyurtmangiz bekor qilindi.*\n🆔 ID: #{$order->short_code}"
            . ($reason ? "\n📝 *Sabab:* {$reason->customerLabel()}" : '')
            . ($order->cancel_note && $reason !== CancelReason::CUSTOMER_REQUEST ? "\n💬 " . $this->escapeMarkdown($order->cancel_note) : '')
            . ($reason === CancelReason::CUSTOMER_REQUEST ? '' : "\n😔 Agar xatolik bo'lgan bo'lsa, iltimos biz bilan bog'laning.");
    }

    /**
     * Free text inside a (legacy) Markdown message: its markup characters are escaped so it cannot break the message.
     */
    protected function escapeMarkdown(string $text): string
    {
        return str_replace(['_', '*', '`', '['], ['\\_', '\\*', '\\`', '\\['], $text);
    }

    /**
     * "Returned" only once the money is back: Payme refunded it (refunded_by_provider) or it went to the keshbek
     * balance, both leaving the payment refunded. Cash, and a card payment flagged for a manual refund, are still to
     * be returned.
     */
    protected function refundText(Order $order): string
    {
        $amount = (float) $order->total_price;
        $returned = $order->payment_method !== 'cash' && $order->payment_status === \App\Enums\PaymentStatus::REFUNDED;

        return "💸 *Buyurtmangiz bo'yicha pul " . ($returned ? 'qaytarildi' : 'qaytariladi') . "*\n🆔 ID: #{$order->short_code}"
            . ($amount > 0 ? "\n💰 Summa: *" . number_format($amount, 0, '.', ' ') . " UZS*" : '')
            . ($returned && $order->payment_method === 'keshbek' ? "\n👛 Mablag' keshbek balansingizga qaytarildi." : '');
    }

    protected function sendMessage(string $chatId, string $text, array $replyMarkup = [], string $parseMode = 'Markdown'): void
    {
        $this->telegram->sendMessage($chatId, $text, [
            'parse_mode' => $parseMode,
            'reply_markup' => $replyMarkup,
        ]);
    }
}
