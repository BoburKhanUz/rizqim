<?php

namespace App\Http\Controllers\Api\V1\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Enums\OrderStatus;
use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TelegramWebhookController extends Controller
{
    protected TelegramNotificationService $telegramService;

    public function __construct(TelegramNotificationService $telegramService)
    {
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

                if (!$userId || (string)$userId === (string)$chatId) {
                    $phoneNumber = '+' . ltrim($phoneNumber, '+');

                    $users = \App\Models\User::where('telegram_id', $chatId)->get();
                    if ($users->isNotEmpty()) {
                        foreach ($users as $user) {
                            $user->update(['phone' => $phoneNumber]);
                        }
                    } else {
                        \App\Models\User::create([
                            'telegram_id' => $chatId,
                            'store_id' => null, // Customers are global
                            'name' => trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? '')),
                            'telegram_username' => $message['from']['username'] ?? null,
                            'phone' => $phoneNumber,
                            'role' => \App\Enums\UserRole::CUSTOMER->value,
                        ]);
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

                if (str_starts_with($payload, 'store_')) {
                    $token = str_replace('store_', '', $payload);
                    $store = \App\Models\Store::where('uuid', $token)->first();
                    
                    if ($store) {
                        $store->update(['telegram_admin_chat_id' => $chatId]);
                        
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
                    $employee = \App\Models\User::where(function ($q) use ($payload, $tokenWithoutPrefix) {
                        $q->where('telegram_invite_token', $payload)
                          ->orWhere('telegram_invite_token', $tokenWithoutPrefix);
                    })->first();
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

        // Mark token as used
        $telegramAction->update(['used_at' => now()]);

        // Prevent canceling if it is already past the new stage
        if ($newStatus === OrderStatus::CANCELED) {
            $oldStatus = $order->status->value ?? $order->status;
            if ($oldStatus !== 'new') {
                $this->answerCallbackQuery($callbackQuery['id'], "Buyurtma qabul qilinganidan keyin uni bekor qilib bo'lmaydi!");
                return response()->json(['status' => 'cannot_cancel']);
            }
        }

        // Don't downgrade status unless needed, but allow updates
        if ($order->status === $newStatus) {
            $this->answerCallbackQuery($callbackQuery['id'], "Buyurtma allaqachon bu holatda!");
            return response()->json(['status' => 'no_change']);
        }

        // Update order status with auto courier/owner assignment if transitioning to delivery/courier_assigned
        $updateData = ['status' => $newStatus->value];

        if (in_array($newStatus->value, ['delivery', 'on_the_way', 'courier_assigned']) && !$order->courier_id) {
            $courier = \App\Models\User::where('store_id', $order->store_id)
                ->where('role', \App\Enums\UserRole::COURIER->value)
                ->where('branch_id', $order->branch_id)
                ->first();
                
            if (!$courier) {
                $courier = \App\Models\User::where('store_id', $order->store_id)
                    ->where('role', \App\Enums\UserRole::COURIER->value)
                    ->first();
            }

            if ($courier) {
                $updateData['courier_id'] = $courier->id;
            } else {
                $owner = \App\Models\User::where('store_id', $order->store_id)
                    ->where('role', \App\Enums\UserRole::OWNER->value)
                    ->first();
                if ($owner) {
                    $updateData['courier_id'] = $owner->id;
                }
            }

            // Create assignment log
            if (isset($updateData['courier_id'])) {
                \App\Models\OrderAssignment::create([
                    'order_id' => $order->id,
                    'courier_id' => $updateData['courier_id'],
                    'assigned_by' => $updateData['courier_id'],
                    'assigned_branch_id' => $order->branch_id,
                    'is_current' => true,
                    'assigned_at' => now(),
                ]);
            }
        }

        $oldStatus = $order->status->value ?? $order->status;
        $order->update($updateData);

        // Save history
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $newStatus->value,
            'note' => "Status updated via Telegram Webhook by: {$actorName}",
        ]);

        if ($oldStatus !== \App\Enums\OrderStatus::DELIVERED->value && $newStatus->value === \App\Enums\OrderStatus::DELIVERED->value) {
            try {
                $performedBy = null;
                $performerUser = \App\Models\User::where('telegram_id', $chatId)->first();
                if ($performerUser) {
                    $performedBy = $performerUser->id;
                }
                app(\App\Services\Finance\LedgerService::class)->processOrderDelivered($order, $performedBy);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to process financials via Telegram for order {$order->uuid}: " . $e->getMessage());
            }
        }

        // Update the Telegram message visually
        $this->telegramService->updateOrderTelegramMessage($order, $actorName, $messageId, $chatId);

        // Dispatch Event for further queued actions (e.g., notify customer)
        event(new \App\Events\OrderStatusUpdated($order));

        // Answer callback query with success banner
        $statusLabel = $newStatus->label();
        $this->answerCallbackQuery($callbackQuery['id'], "Buyurtma holati: {$statusLabel}!");

        return response()->json(['status' => 'success']);
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
