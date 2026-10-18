<?php

namespace App\Services\Telegram;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\ReferralService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bot messages: a shared contact (phone verification / registration) and /start with its payloads (referral link,
 * store chat binding, employee invite, welcome). Returns the status the webhook answers with; replies are queued.
 */
class TelegramMessageHandler
{
    public function __construct(
        protected TelegramMessenger $telegram,
        protected ReferralService $referrals
    ) {}

    public function handle(array $message): string
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        // Handle Contact Sharing
        if (isset($message['contact']) && $chatId) {
            $status = $this->linkContact($message, $chatId);
            if ($status) {
                return $status;
            }
        }

        if (str_starts_with($text, '/start') && $chatId) {
            return $this->start($message, $text, $chatId) ?? 'ignored';
        }

        return 'ignored';
    }

    protected function linkContact(array $message, int|string $chatId): ?string
    {
        $contact = $message['contact'];
        $phoneNumber = $contact['phone_number'];
        $userId = $contact['user_id'] ?? null;
        $senderId = $message['from']['id'] ?? null;

        // Only the sender's own contact (request_contact button) proves the number; any forwarded card is ignored
        if ($userId === null || $senderId === null || (string) $userId !== (string) $senderId) {
            return null;
        }

        $phoneNumber = '+' . ltrim($phoneNumber, '+');

        $users = User::where('telegram_id', (string) $senderId)->get();
        if ($users->isNotEmpty()) {
            foreach ($users as $user) {
                $user->update(['phone' => $phoneNumber]);
            }
        } else {
            $newUser = User::create([
                'telegram_id' => (string) $senderId,
                'store_id' => null, // Customers are global
                'name' => trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? '')),
                'telegram_username' => $message['from']['username'] ?? null,
                'phone' => $phoneNumber,
                'role' => UserRole::CUSTOMER->value,
            ]);

            // Registered here, before the Mini App login could consume a referral parked by /start
            $this->referrals->applyPendingReferral($newUser, (string) $senderId);
        }

        // First remove reply keyboard with a message, then the welcome with the WebApp button (only when the Mini App
        // URL is configured: without it there is nothing to open)
        $messages = [
            ["✅ Telefon raqamingiz muvaffaqiyatli tasdiqlandi!", [
                'reply_markup' => ['remove_keyboard' => true],
            ]],
        ];
        $orderButton = $this->telegram->webAppButton('🍔 Buyurtma berish');
        if ($orderButton) {
            $messages[] = ["Buyurtma berishni boshlash uchun quyidagi tugmani bosing 👇", [
                'parse_mode' => 'Markdown',
                'reply_markup' => ['inline_keyboard' => [[$orderButton]]],
            ]];
        }
        $this->telegram->sendMessagesInOrder($chatId, $messages);

        return 'contact_linked';
    }

    protected function start(array $message, string $text, int|string $chatId): ?string
    {
        // Parse command and payload robustly (handles "/start payload" and "/start@bot payload" in groups)
        $parts = preg_split('/\s+/', trim($text), 2);
        $payload = $parts[1] ?? '';
        $senderId = (string) ($message['from']['id'] ?? $chatId);

        // Referral link opened by someone without an account yet: parked until they register
        $referrerId = $this->referrals->referrerIdFromParam($payload, $senderId);
        if ($referrerId !== null && !User::where('telegram_id', $senderId)->exists()) {
            $this->referrals->storePendingReferral($senderId, $referrerId);
        }

        if (str_starts_with($payload, 'store_')) {
            $token = str_replace('store_', '', $payload);
            $store = Str::isUuid($token) ? Store::where('uuid', $token)->first() : null;

            if ($store) {
                return $this->bindStoreChat($store, $message, $chatId);
            }
        }

        // Support employee invites (either invite_token or raw token starting with emp_)
        $employee = null;
        if (!empty($payload)) {
            $tokenWithoutPrefix = str_replace('invite_', '', $payload);
            // A storeless invite would match the person's global account via "store_id IS NULL" below
            $employee = User::where(function ($q) use ($payload, $tokenWithoutPrefix) {
                $q->where('telegram_invite_token', $payload)
                  ->orWhere('telegram_invite_token', $tokenWithoutPrefix);
            })->whereNotNull('store_id')->first();
        }

        if ($employee) {
            return $this->linkEmployee($employee, $message, $chatId);
        }

        return $this->welcome($chatId);
    }

    protected function bindStoreChat(Store $store, array $message, int|string $chatId): string
    {
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
            $this->telegram->sendMessage($chatId, "⛔ Bu chatni restoranga faqat shu restoranning egasi yoki filialga biriktirilmagan menejeri bog'lay oladi.");
            return 'store_link_forbidden';
        }

        $previousChatId = $store->telegram_admin_chat_id;
        $store->forceFill(['telegram_admin_chat_id' => $chatId])->save();

        if ($previousChatId && (string) $previousChatId !== (string) $chatId) {
            $this->telegram->sendMessage($previousChatId, "⚠️ {$store->name} restorani buyurtmalari endi boshqa chatga yuboriladi: bu chat restorandan uzildi. Agar buni siz qilmagan bo'lsangiz, restoran egasiga xabar bering.");
        }

        $this->telegram->sendMessage($chatId, "✅ *{$store->name}* restorani ushbu chatga muvaffaqiyatli bog'landi!\n\nEndi barcha yangi buyurtmalar va bildirishnomalar shu yerga keladi.", [
            'parse_mode' => 'Markdown',
        ]);

        return 'store_linked';
    }

    protected function linkEmployee(User $employee, array $message, int|string $chatId): string
    {
        // Unlink this telegram_id from any other user in other stores globally first to prevent conflicts
        User::where('telegram_id', $chatId)
            ->where('id', '!=', $employee->id)
            ->where('store_id', '!=', $employee->store_id)
            ->update(['telegram_id' => null]);

        // Check if this telegram_id is already bound to this store
        $alreadyExists = User::where('store_id', $employee->store_id)
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

            if ($this->telegram->isConfigured()) {
                $roleLabel = method_exists($alreadyExists->role, 'label') ? $alreadyExists->role->label() : $alreadyExists->role->value;
                $this->telegram->sendMessage($chatId, "✅ *Rolingiz yangilandi!*\n\nSiz muvaffaqiyatli *{$roleLabel}* roliga o'tdingiz!\nDo'kon: " . ($alreadyExists->store->name ?? 'Noma\'lum'), [
                    'parse_mode' => 'Markdown',
                ]);
            }
            return 'employee_linked';
        }

        $employee->update([
            'telegram_id' => $chatId,
            'telegram_username' => $message['from']['username'] ?? null,
            'telegram_invite_token' => null,
        ]);

        if ($this->telegram->isConfigured()) {
            $roleLabel = method_exists($employee->role, 'label') ? $employee->role->label() : $employee->role;
            $this->telegram->sendMessage($chatId, "✅ *Tabriklaymiz!*\n\nSiz muvaffaqiyatli *{$roleLabel}* sifatida ro'yxatdan o'tdingiz!\nDo'kon: " . ($employee->store->name ?? 'Noma\'lum'), [
                'parse_mode' => 'Markdown',
            ]);
        }
        return 'employee_linked';
    }

    /**
     * A customer without a verified phone is asked for it first; everyone else gets the Mini App button for their role.
     */
    protected function welcome(int|string $chatId): ?string
    {
        if (!$this->telegram->isConfigured()) {
            return null;
        }

        // Identify user role from database (Prioritize super admin if multiple records exist)
        $existingUser = User::where('telegram_id', $chatId)
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

            $this->telegram->sendMessage($chatId, $welcomeText, ['parse_mode' => 'Markdown', 'reply_markup' => $keyboard]);
            return 'request_contact_sent';
        }

        if (in_array($role, [UserRole::OWNER->value, UserRole::MANAGER->value, UserRole::SUPER_ADMIN->value])) {
            $welcomeText = "👋 *Assalomu alaykum, {$existingUser->name}!*\n\n";
            $welcomeText .= "Tizimga xush kelibsiz. Boshqaruv paneliga kirish uchun quyidagi tugmani bosing 👇";
            $buttonText = '⚙️ Boshqaruv paneli';
        } elseif ($role === UserRole::COURIER->value) {
            $welcomeText = "👋 *Assalomu alaykum, {$existingUser->name}!*\n\n";
            $welcomeText .= "Yangi buyurtmalarni qabul qilish va yetkazib berish holatini boshqarish uchun quyidagi tugmani bosing 👇";
            $buttonText = '🛵 Kuryer paneli';
        } else {
            // Customer with phone
            $welcomeText = "👋 *Assalomu alaykum!*\n\n";
            $welcomeText .= "Bizning o'ta mazali va issiqqina fast-foodlarimizdan buyurtma berish uchun quyidagi tugmani bosing va ilovani ishga tushiring! 👇";
            $buttonText = '🍔 Buyurtma berish';
        }

        // Every welcome below points at the Mini App button: without a configured Mini App URL none is sent
        $button = $this->telegram->webAppButton($buttonText);
        if (!$button) {
            Log::warning('Telegram welcome not sent: TELEGRAM_WEBAPP_URL is not set, so there is no Mini App to open.');
            return 'webapp_url_missing';
        }

        $this->telegram->sendMessage($chatId, $welcomeText, ['parse_mode' => 'Markdown', 'reply_markup' => ['inline_keyboard' => [[$button]]]]);
        return 'welcome_sent';
    }
}
