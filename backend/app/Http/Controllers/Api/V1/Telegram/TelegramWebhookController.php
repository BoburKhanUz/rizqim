<?php

namespace App\Http\Controllers\Api\V1\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Services\Auth\ReferralService;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    protected TelegramNotificationService $telegramService;

    public function __construct(
        TelegramNotificationService $telegramService,
        protected OrderStatusService $orderStatus,
        protected ReferralService $referrals
    ) {
        $this->telegramService = $telegramService;
    }

    public function handle(Request $request)
    {
        $update = $request->all();

        // Log incoming update for debug
        Log::info('Telegram Webhook Update Received', ['update' => $update]);

        // Handle incoming text messages (e.g. /start)
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'] ?? null;
            $text = $message['text'] ?? '';

            // Handle Contact Sharing
            if (isset($message['contact']) && $chatId) {
                $contact = $message['contact'];
                $phoneNumber = $contact['phone_number'];
                $userId = $contact['user_id'] ?? null;
                $senderId = $message['from']['id'] ?? null;

                // Only the sender's own contact (request_contact button) proves the number; any forwarded card is ignored
                if ($userId !== null && $senderId !== null && (string) $userId === (string) $senderId) {
                    $phoneNumber = '+' . ltrim($phoneNumber, '+');

                    $users = \App\Models\User::where('telegram_id', (string) $senderId)->get();
                    if ($users->isNotEmpty()) {
                        foreach ($users as $user) {
                            $user->update(['phone' => $phoneNumber]);
                        }
                    } else {
                        $newUser = \App\Models\User::create([
                            'telegram_id' => (string) $senderId,
                            'store_id' => null, // Customers are global
                            'name' => trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? '')),
                            'telegram_username' => $message['from']['username'] ?? null,
                            'phone' => $phoneNumber,
                            'role' => \App\Enums\UserRole::CUSTOMER->value,
                        ]);

                        // Registered here, before the Mini App login could consume a referral parked by /start
                        $this->referrals->applyPendingReferral($newUser, (string) $senderId);
                    }

                    $botToken = config('services.telegram.bot_token');
                    if ($botToken) {
                        $webAppUrl = config('services.telegram.webapp_url', 'https://food-order.boburkhan.uz/');

                        // First remove reply keyboard with a message
                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $chatId,
                            'text' => "✅ Telefon raqamingiz muvaffaqiyatli tasdiqlandi!",
                            'reply_markup' => json_encode(['remove_keyboard' => true])
                        ]);

                        // Send welcome with WebApp button
                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $chatId,
                            'text' => "Buyurtma berishni boshlash uchun quyidagi tugmani bosing 👇",
                            'parse_mode' => 'Markdown',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => '🍔 Buyurtma berish', 'web_app' => ['url' => $webAppUrl]]
                                    ]
                                ]
                            ])
                        ]);
                    }
                    return response()->json(['status' => 'contact_linked']);
                }
            }

            if (str_starts_with($text, '/start') && $chatId) {
                $botToken = config('services.telegram.bot_token');
                
                // Parse command and payload robustly (handles "/start payload" and "/start@bot payload" in groups)
                $parts = preg_split('/\s+/', trim($text), 2);
                $command = $parts[0] ?? '';
                $payload = $parts[1] ?? '';
                $senderId = (string) ($message['from']['id'] ?? $chatId);

                // Referral link opened by someone without an account yet: parked until they register
                $referrerId = $this->referrals->referrerIdFromParam($payload, $senderId);
                if ($referrerId !== null && !User::where('telegram_id', $senderId)->exists()) {
                    $this->referrals->storePendingReferral($senderId, $referrerId);
                }

                if (str_starts_with($payload, 'store_')) {
                    $token = str_replace('store_', '', $payload);
                    $store = Str::isUuid($token) ? \App\Models\Store::where('uuid', $token)->first() : null;

                    if ($store) {
                        // The store uuid is public and this chat gets every order and its action buttons:
                        // only the owner, or a store-wide manager (no branch), may route it
                        $canBindStoreChat = isset($message['from']['id']) && User::where('telegram_id', (string) $message['from']['id'])
                            ->where('store_id', $store->id)
                            ->where(function ($query) {
                                $query->where('role', UserRole::OWNER->value)
                                    ->orWhere(fn ($q) => $q->where('role', UserRole::MANAGER->value)->whereNull('branch_id'));
                            })
                            ->exists();

                        if (!$canBindStoreChat) {
                            if ($botToken) {
                                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                    'chat_id' => $chatId,
                                    'text' => "⛔ Bu chatni restoranga faqat shu restoranning egasi yoki filialga biriktirilmagan menejeri bog'lay oladi.",
                                ]);
                            }
                            return response()->json(['status' => 'store_link_forbidden']);
                        }

                        $previousChatId = $store->telegram_admin_chat_id;
                        $store->forceFill(['telegram_admin_chat_id' => $chatId])->save();

                        if ($botToken && $previousChatId && (string) $previousChatId !== (string) $chatId) {
                            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $previousChatId,
                                'text' => "⚠️ {$store->name} restorani buyurtmalari endi boshqa chatga yuboriladi: bu chat restorandan uzildi. Agar buni siz qilmagan bo'lsangiz, restoran egasiga xabar bering.",
                            ]);
                        }

                        if ($botToken) {
                            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $chatId,
                                'text' => "✅ *{$store->name}* restorani ushbu chatga muvaffaqiyatli bog'landi!\n\nEndi barcha yangi buyurtmalar va bildirishnomalar shu yerga keladi.",
                                'parse_mode' => 'Markdown',
                            ]);
                        }
                        return response()->json(['status' => 'store_linked']);
                    }
                }

                // Support employee invites (either invite_token or raw token starting with emp_)
                $employee = null;
                if (!empty($payload)) {
                    $tokenWithoutPrefix = str_replace('invite_', '', $payload);
                    // A storeless invite would match the person's global account via "store_id IS NULL" below
                    $employee = \App\Models\User::where(function ($q) use ($payload, $tokenWithoutPrefix) {
                        $q->where('telegram_invite_token', $payload)
                          ->orWhere('telegram_invite_token', $tokenWithoutPrefix);
                    })->whereNotNull('store_id')->first();
                }

                if ($employee) {
                    // Unlink this telegram_id from any other user in other stores globally first to prevent conflicts
                    \App\Models\User::where('telegram_id', $chatId)
                        ->where('id', '!=', $employee->id)
                        ->where('store_id', '!=', $employee->store_id)
                        ->update(['telegram_id' => null]);

                    // Check if this telegram_id is already bound to this store
                    $alreadyExists = \App\Models\User::where('store_id', $employee->store_id)
                        ->where('telegram_id', $chatId)
                        ->where('id', '!=', $employee->id)
                        ->first();

                    if ($alreadyExists) {
                        // Update existing user with new role, branch, and name
                        $alreadyExists->update([
                            'role' => $employee->role,
                            'branch_id' => $employee->branch_id,
                            'name' => $employee->name,
                        ]);

                        // Clean up the dummy/temp employee row
                        $employee->delete();

                        if ($botToken) {
                            $roleLabel = method_exists($alreadyExists->role, 'label') ? $alreadyExists->role->label() : $alreadyExists->role->value;
                            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $chatId,
                                'text' => "✅ *Rolingiz yangilandi!*\n\nSiz muvaffaqiyatli *{$roleLabel}* roliga o'tdingiz!\nDo'kon: " . ($alreadyExists->store->name ?? 'Noma\'lum'),
                                'parse_mode' => 'Markdown',
                            ]);
                        }
                        return response()->json(['status' => 'employee_linked']);
                    }

                    $employee->update([
                        'telegram_id' => $chatId,
                        'telegram_username' => $message['from']['username'] ?? null,
                        'telegram_invite_token' => null,
                    ]);
                    
                    if ($botToken) {
                        $roleLabel = method_exists($employee->role, 'label') ? $employee->role->label() : $employee->role;
                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $chatId,
                            'text' => "✅ *Tabriklaymiz!*\n\nSiz muvaffaqiyatli *{$roleLabel}* sifatida ro'yxatdan o'tdingiz!\nDo'kon: " . ($employee->store->name ?? 'Noma\'lum'),
                            'parse_mode' => 'Markdown',
                        ]);
                    }
                    return response()->json(['status' => 'employee_linked']);
                }

                if ($botToken) {
                    $webAppUrl = config('services.telegram.webapp_url', 'https://food-order.boburkhan.uz/');

                    // Identify user role from database (Prioritize super admin if multiple records exist)
                    $existingUser = \App\Models\User::where('telegram_id', $chatId)
                        ->orderByRaw("CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END DESC")
                        ->first();
                    $role = $existingUser ? $existingUser->role->value : 'customer';

                    if ($role === 'customer' && (!$existingUser || !$existingUser->phone)) {
                        $welcomeText = "👋 *Assalomu alaykum!*\n\nBizning mazali taomlarimizga buyurtma berish uchun iltimos, pastdagi tugmani bosib telefon raqamingizni tasdiqlang 👇";
                        $keyboard = [
                            'keyboard' => [
                                [
                                    ['text' => '📱 Telefon raqamini yuborish', 'request_contact' => true]
                                ]
                            ],
                            'resize_keyboard' => true,
                            'one_time_keyboard' => true
                        ];
                        
                        try {
                            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $chatId,
                                'text' => $welcomeText,
                                'parse_mode' => 'Markdown',
                                'reply_markup' => json_encode($keyboard)
                            ]);
                            return response()->json(['status' => 'request_contact_sent']);
                        } catch (\Exception $e) {
                            Log::error("Failed to send contact request: " . $e->getMessage());
                        }
                    }

                    if (in_array($role, [\App\Enums\UserRole::OWNER->value, \App\Enums\UserRole::MANAGER->value, \App\Enums\UserRole::SUPER_ADMIN->value])) {
                        $welcomeText = "👋 *Assalomu alaykum, {$existingUser->name}!*\n\n";
                        $welcomeText .= "Tizimga xush kelibsiz. Boshqaruv paneliga kirish uchun quyidagi tugmani bosing 👇";
                        $buttonText = '⚙️ Boshqaruv paneli';
                    } elseif ($role === \App\Enums\UserRole::COURIER->value) {
                        $welcomeText = "👋 *Assalomu alaykum, {$existingUser->name}!*\n\n";
                        $welcomeText .= "Yangi buyurtmalarni qabul qilish va yetkazib berish holatini boshqarish uchun quyidagi tugmani bosing 👇";
                        $buttonText = '🛵 Kuryer paneli';
                    } else {
                        // Customer with phone
                        $welcomeText = "👋 *Assalomu alaykum!*\n\n";
                        $welcomeText .= "Bizning o'ta mazali va issiqqina fast-foodlarimizdan buyurtma berish uchun quyidagi tugmani bosing va ilovani ishga tushiring! 👇";
                        $buttonText = '🍔 Buyurtma berish';
                    }

                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $buttonText, 'web_app' => ['url' => $webAppUrl]]
                            ]
                        ]
                    ];

                    try {
                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $chatId,
                            'text' => $welcomeText,
                            'parse_mode' => 'Markdown',
                            'reply_markup' => json_encode($keyboard)
                        ]);
                        return response()->json(['status' => 'welcome_sent']);
                    } catch (\Exception $e) {
                        Log::error("Failed to send /start welcome message: " . $e->getMessage());
                    }
                }
            }

            return response()->json(['status' => 'ignored']);
        }

        // We only care about callback queries
        if (!isset($update['callback_query'])) {
            return response()->json(['status' => 'ignored']);
        }

        $callbackQuery = $update['callback_query'];
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
        $telegramAction = \App\Models\TelegramAction::where('token', $callbackData)->first();

        if (!$telegramAction) {
            $this->answerCallbackQuery($callbackQuery['id'], 'Invalid or expired action!', true);
            return response()->json(['status' => 'error', 'message' => 'Invalid token']);
        }

        if ($telegramAction->used_at) {
            $this->answerCallbackQuery($callbackQuery['id'], 'Bu amal allaqachon bajarilgan!', true);
            return response()->json(['status' => 'error', 'message' => 'Already used']);
        }

        if ($telegramAction->expires_at->isPast()) {
            $this->answerCallbackQuery($callbackQuery['id'], 'Amal muddati tugagan!', true);
            return response()->json(['status' => 'error', 'message' => 'Expired token']);
        }

        $order = $telegramAction->order;
        if (!$order) {
            $this->answerCallbackQuery($callbackQuery['id'], 'Order not found!', true);
            return response()->json(['status' => 'error', 'message' => 'Order not found']);
        }

        $action = $telegramAction->action;

        // Map action to status
        $newStatus = match ($action) {
            'accept' => OrderStatus::ACCEPTED,
            'prepare' => OrderStatus::PREPARING,
            'deliver' => OrderStatus::COURIER_ASSIGNED,
            'take' => OrderStatus::ON_THE_WAY,
            'complete' => OrderStatus::DELIVERED,
            'cancel' => OrderStatus::CANCELED,
            default => null,
        };

        if (!$newStatus) {
            return response()->json(['status' => 'unhandled_action']);
        }

        // The button only proves the message reached some chat: the presser must be staff allowed to make this change
        $actor = $this->staffActor($fromUser['id'] ?? null, $order);
        if (!$actor) {
            $this->answerCallbackQuery($callbackQuery['id'], "Sizda bu buyurtma bo'yicha amal bajarishga ruxsat yo'q!", true);
            return response()->json(['status' => 'forbidden']);
        }

        try {
            $result = $this->orderStatus->transition($order, $newStatus, $actor, [
                // "Rad etish" is offered on new orders only: an old button must not cancel an accepted order
                'from' => $newStatus === OrderStatus::CANCELED ? [OrderStatus::NEW] : null,
                'notes' => "Status updated via Telegram Webhook by: {$actorName}",
            ]);
        } catch (OrderTransitionException $e) {
            if ($e->reason === 'forbidden') {
                $this->answerCallbackQuery($callbackQuery['id'], "Sizda bu buyurtma bo'yicha amal bajarishga ruxsat yo'q!", true);
                return response()->json(['status' => 'forbidden']);
            }

            if ($newStatus === OrderStatus::CANCELED) {
                $this->answerCallbackQuery($callbackQuery['id'], "Buyurtma qabul qilinganidan keyin uni bekor qilib bo'lmaydi!");
                return response()->json(['status' => 'cannot_cancel']);
            }

            $this->answerCallbackQuery($callbackQuery['id'], $e->getMessage(), true);
            return response()->json(['status' => 'invalid_transition']);
        }

        // Spent only once it went through, so a refused press leaves the button usable for staff who may use it
        $telegramAction->update(['used_at' => now()]);

        if (!$result->changed) {
            $this->answerCallbackQuery($callbackQuery['id'], "Buyurtma allaqachon bu holatda!");
            return response()->json(['status' => 'no_change']);
        }

        // Update the Telegram message visually
        $this->telegramService->updateOrderTelegramMessage($result->order, $actorName, $messageId, $chatId);

        // Answer callback query with success banner
        $statusLabel = $newStatus->label();
        $this->answerCallbackQuery($callbackQuery['id'], "Buyurtma holati: {$statusLabel}!");

        return response()->json(['status' => 'success']);
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

    protected function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): void
    {
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            return;
        }

        try {
            Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to answer callback query: " . $e->getMessage());
        }
    }
}
