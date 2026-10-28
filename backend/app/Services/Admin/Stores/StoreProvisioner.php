<?php

namespace App\Services\Admin\Stores;

use App\Enums\PaymentAccountType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\StaffPhoneLoginService;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * P4-22: the one way a new store is created: the store, its owner account and its main branch, in one transaction.
 * The Boshqaruv markazi's "convert an application to a store" calls it (the old superadmin wizard did too, until
 * P4-33; the legacy POST /superadmin/stores is gone since P4-25).
 *
 *  - Store: payment settings as a new store starts (cash, Click, Payme where offered), the platform's merchant
 *    account by default, no flat commission rate unless one is given (null: the per-channel policy of P4-21), status
 *    and visibility as the caller says (by default
 *    active and visible; the pipeline: pending and hidden until the application reaches `faol`).
 *  - Owner: role owner, a one-time Telegram invite token (the owner opens the bot link and the account binds to their
 *    Telegram), an optional password, and the phone vouched as the SMS login phone of the
 *    partner panel (docs/AUTH.md). Another staff account may not hold that phone; the owner's own customer account may.
 *  - Branch "Asosiy filial": region, district, address, coordinates, delivery radius, opening hours.
 * The superadmins with Telegram hear about it, with the owner's invite link (as before).
 */
class StoreProvisioner
{
    public function __construct(
        private readonly StaffPhoneLoginService $phones,
        private readonly TelegramMessenger $telegram,
    ) {}

    /**
     * @param  array{name: string, phone?: string|null, commission_rate?: numeric|null, payment_account_type?: string|null, payment_methods?: list<string>|null, status?: StoreStatus|string|null, is_active?: bool|null, tin?: string|null, legal_name?: string|null, owner_name: string, owner_phone: string, owner_password?: string|null, branch_region_id: int, branch_district_id: int, branch_address: string, branch_phone?: string|null, branch_lat: numeric, branch_lng: numeric, branch_radius?: numeric|null, branch_open_time?: string|null, branch_close_time?: string|null}  $data
     * @return array{store: Store, owner: User, branch: Branch, invite_link: string}
     *
     * @throws StoreAdminException owner_phone_invalid, owner_phone_taken
     */
    public function create(array $data, ?string $channel = null): array
    {
        $ownerPhone = trim((string) $data['owner_phone']);
        if (!preg_match(StaffPhoneLoginService::PHONE_PATTERN, $ownerPhone)) {
            throw new StoreAdminException("Egasining telefoni +998XXXXXXXXX ko'rinishida bo'lsin.", 'owner_phone_invalid');
        }
        if (self::phoneTaken($ownerPhone)) {
            throw new StoreAdminException("Bu telefon raqami boshqa xodim hisobida bor.", 'owner_phone_taken', 409);
        }

        $status = $data['status'] ?? StoreStatus::ACTIVE;
        $status = $status instanceof StoreStatus ? $status : StoreStatus::from($status);

        // None given: no flat rate (written as null, whatever the column's default), so the per-channel commission
        // policy applies (P4-21: RIZQIM courier 15%, own delivery 10%, pickup 5%, hall 0%)
        $commissionRate = $data['commission_rate'] ?? null;

        $result = DB::transaction(function () use ($data, $ownerPhone, $status, $commissionRate) {
            $store = Store::create([...array_filter([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'payment_account_type' => $data['payment_account_type'] ?? PaymentAccountType::PLATFORM_ACCOUNT->value,
                'payment_settings' => Store::initialPaymentSettings($data['payment_methods'] ?? ['cash', 'click', 'payme']),
                'status' => $status->value,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'tin' => $data['tin'] ?? null,
                'legal_name' => $data['legal_name'] ?? null,
            ], fn ($value) => $value !== null), 'commission_rate' => $commissionRate]);

            $owner = User::create(array_filter([
                'name' => $data['owner_name'],
                'password' => isset($data['owner_password']) && $data['owner_password'] !== '' ? Hash::make($data['owner_password']) : null,
                'role' => UserRole::OWNER->value,
                'store_id' => $store->id,
                'telegram_invite_token' => Str::uuid()->toString(),
            ], fn ($value) => $value !== null));
            // The platform vouches for the owner's login phone: SMS sign-in to the partner panel (docs/AUTH.md)
            $this->phones->vouch($owner, $ownerPhone);

            $branch = Branch::create([
                'store_id' => $store->id,
                'region_id' => $data['branch_region_id'],
                'district_id' => $data['branch_district_id'],
                'name' => 'Asosiy Filial',
                'address' => $data['branch_address'],
                'phone' => $data['branch_phone'] ?? null,
                'latitude' => $data['branch_lat'],
                'longitude' => $data['branch_lng'],
                'radius_km' => $data['branch_radius'] ?? 5,
                'open_time' => $data['branch_open_time'] ?? null,
                'close_time' => $data['branch_close_time'] ?? null,
                'is_active' => true,
            ]);

            return ['store' => $store, 'owner' => $owner->refresh(), 'branch' => $branch];
        });

        $link = self::inviteLink((string) $result['owner']->telegram_invite_token);
        $this->announce($result['store'], $result['owner'], $result['branch'], $link, $channel);

        return [...$result, 'invite_link' => $link];
    }

    /**
     * The bot link that binds the owner's Telegram to their account (one use).
     */
    public static function inviteLink(string $token): string
    {
        $bot = (string) config('services.telegram.bot_username');

        return "https://t.me/{$bot}?start=invite_{$token}";
    }

    /**
     * A phone is taken when another staff account (not a customer) already has it.
     */
    public static function phoneTaken(string $phone, ?int $exceptUserId = null): bool
    {
        return User::query()
            ->where('phone', $phone)
            ->where('role', '!=', UserRole::CUSTOMER->value)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists();
    }

    private function announce(Store $store, User $owner, Branch $branch, string $link, ?string $channel): void
    {
        if (!$this->telegram->isConfigured()) {
            return;
        }

        $superAdmins = User::where('role', UserRole::SUPER_ADMIN)->whereNotNull('telegram_id')->get(['id', 'telegram_id']);
        foreach ($superAdmins as $admin) {
            $this->telegram->sendMessage($admin->telegram_id, "🏢 <b>Yangi do'kon qo'shildi" . ($channel ? " ({$channel})" : '') . "!</b>\n\n" .
                'Nomi: <b>' . htmlspecialchars($store->name) . "</b>\n" .
                'Komissiya: <b>' . ($store->commission_rate !== null ? "{$store->commission_rate}%" : "kanal bo'yicha (15/10/5/0%)") . "</b>\n" .
                'Egasi: <b>' . htmlspecialchars($owner->name) . '</b> (' . htmlspecialchars((string) $owner->phone) . ")\n" .
                'Filial: <b>' . htmlspecialchars((string) $branch->address) . "</b>\n\n" .
                "🔗 <b>Egasi uchun taklifnoma havolasi:</b>\n{$link}", ['parse_mode' => 'HTML']);
        }
    }
}
