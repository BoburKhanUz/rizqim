<?php

namespace App\Services\Admin\Stores;

use App\Enums\StoreApplicationStage;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\User;
use App\Services\Sms\SmsServiceInterface;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * P4-22: what a team member does to a store from its 360° card (admin.stores.manage, a step-up and a `reason` each,
 * audited by the controller with the fields before and after). Every status change writes a StoreStatusLog row with
 * the member and the reason (the card's "Holat tarixi"); the Store model's updated hook writes it, setStatus names the member.
 *
 *  - suspend: status suspended and hidden from the marketplace (is_active false): no new Mini App orders, the store
 *    disappears from the catalog. The till keeps working (the POS never checks the store status) — owner decision:
 *    the kassa never stops taking payments and printing receipts;
 *  - activate: back to active and visible, from suspended, pending, under review or rejected. Not from
 *    payment_required / finance_hold (the debt rules and Moliya decide those) and not while the store's
 *    application is still in the pipeline (its `faol` stage activates it);
 *  - credit limit: how much the store may owe (a positive amount; kept as the negative floor stores.credit_limit),
 *    then the same block rule LedgerService applies: over the limit an active store becomes payment_required, back
 *    within it a payment_required store becomes active;
 *  - display flags: premium, recommended (is_featured);
 *  - Telegram link: a new one-time invite for an owner who is not on Telegram yet (the old link stops working),
 *    sent to the owner's phone by SMS, never handed to the member (review F3-1);
 *  - message the owner: a queued Telegram message, plain text.
 */
class StoreActions
{
    public function __construct(
        private readonly TelegramMessenger $telegram,
        private readonly SmsServiceInterface $sms,
    ) {}

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     *
     * @throws StoreAdminException
     */
    public function suspend(Store $store, User $member, string $reason): array
    {
        return DB::transaction(function () use ($store, $member, $reason) {
            $store = $this->lock($store);
            if ($store->status === StoreStatus::SUSPENDED && !$store->is_active) {
                throw new StoreAdminException("Ovqatxona allaqachon to'xtatilgan.", 'already_suspended', 409);
            }

            return $this->setStatus($store, StoreStatus::SUSPENDED, false, $member, $reason);
        });
    }

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     *
     * @throws StoreAdminException
     */
    public function activate(Store $store, User $member, string $reason, bool $fromPipeline = false): array
    {
        return DB::transaction(function () use ($store, $member, $reason, $fromPipeline) {
            $store = $this->lock($store);

            if (in_array($store->status, [StoreStatus::PAYMENT_REQUIRED, StoreStatus::FINANCE_HOLD], true)) {
                throw new StoreAdminException(
                    "Bu holatni moliya qoidalari boshqaradi: qarz to'lansa yoki kredit limiti o'zgartirilsa, ovqatxona o'zi faollashadi.",
                    'finance_status',
                );
            }
            if (!$fromPipeline && self::openApplication($store) !== null) {
                throw new StoreAdminException("Ovqatxona hali ulash jarayonida: arizani «Faol» bosqichiga o'tkazing.", 'use_pipeline');
            }
            if ($store->status === StoreStatus::ACTIVE && $store->is_active) {
                throw new StoreAdminException('Ovqatxona allaqachon faol.', 'already_active', 409);
            }

            return $this->setStatus($store, StoreStatus::ACTIVE, true, $member, $reason);
        });
    }

    /**
     * @param  string  $limit  the debt the store may have, a non-negative decimal string
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function setCreditLimit(Store $store, string $limit, User $member, string $reason): array
    {
        return DB::transaction(function () use ($store, $limit, $member, $reason) {
            $store = $this->lock($store);
            $before = ['credit_limit' => Money::of($store->credit_limit)->abs()->toString(), 'status' => $store->status->value];

            $store->forceFill(['credit_limit' => Money::of($limit)->abs()->negated()->toString()])->save();

            // The rule LedgerService applies when the debt moves, applied when the limit moves
            if ($store->status === StoreStatus::PAYMENT_REQUIRED && !$store->exceedsCreditLimit()) {
                $this->setStatus($store, StoreStatus::ACTIVE, (bool) $store->is_active, $member, 'Kredit limiti o\'zgardi: ' . $reason);
            } elseif ($store->status === StoreStatus::ACTIVE && $store->exceedsCreditLimit()) {
                $this->setStatus($store, StoreStatus::PAYMENT_REQUIRED, (bool) $store->is_active, $member, 'Kredit limiti o\'zgardi: ' . $reason);
            }

            $store->refresh();

            return ['before' => $before, 'after' => ['credit_limit' => Money::of($store->credit_limit)->abs()->toString(), 'status' => $store->status->value]];
        });
    }

    /**
     * @param  array{is_premium?: bool, is_featured?: bool}  $flags
     * @return array{before: array<string, bool>, after: array<string, bool>}
     */
    public function setDisplay(Store $store, array $flags): array
    {
        return DB::transaction(function () use ($store, $flags) {
            $store = $this->lock($store);
            $before = ['is_premium' => (bool) $store->is_premium, 'is_featured' => (bool) $store->is_featured];
            $store->forceFill(array_intersect_key($flags, $before))->save();

            return ['before' => $before, 'after' => ['is_premium' => (bool) $store->is_premium, 'is_featured' => (bool) $store->is_featured]];
        });
    }

    /**
     * P4-32 (was the old superadmin store form): where the store's online payments go and the reserve a payout must
     * leave in the wallet.
     *
     *  - `payment_account_type`: platform_account (Click/Payme pay the platform, the ledger settles the store's wallet)
     *    or own_account (the store's own merchant cashbox, once the owner entered its keys in the partner panel). Not
     *    while the store has an online order still unpaid at any stage (review C#2: not only `new`; one that is not
     *    cancelled, rejected or refunded may still be paid) — 409 payments_in_flight. The callbacks of an order, and
     *    the refund of a paid one, are verified with the account it was placed with (`payment_owner`,
     *    App\Services\Payment\OrderMerchantAccount) whatever this is changed to;
     *  - `min_payout_reserve`: what stays in the wallet after a payout (LedgerService::availableForPayout).
     *
     * @param  array{payment_account_type?: string, min_payout_reserve?: string}  $change
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     *
     * @throws StoreAdminException
     */
    public function setPaymentAccount(Store $store, array $change): array
    {
        return DB::transaction(function () use ($store, $change) {
            $store = $this->lock($store);
            $before = ['payment_account_type' => (string) $store->payment_account_type, 'min_payout_reserve' => Money::of($store->min_payout_reserve ?? 0)->toString()];

            $fill = [];
            if (array_key_exists('payment_account_type', $change) && $change['payment_account_type'] !== $before['payment_account_type']) {
                $waiting = \App\Models\Order::query()
                    ->where('store_id', $store->id)
                    ->whereNotIn('status', [\App\Enums\OrderStatus::CANCELED->value, \App\Enums\OrderStatus::REJECTED->value, \App\Enums\OrderStatus::REFUNDED->value])
                    ->whereIn('payment_method', (array) config('orders.online_payment_methods', []))
                    ->whereNotIn('payment_status', [\App\Enums\PaymentStatus::PAID->value, \App\Enums\PaymentStatus::REFUNDED->value])
                    ->exists();
                if ($waiting) {
                    throw new StoreAdminException(
                        "Ovqatxonada to'lovi kutilayotgan onlayn buyurtma bor: to'lov hisobini u yakunlangach (yoki bekor bo'lgach) o'zgartiring.",
                        'payments_in_flight',
                        409,
                    );
                }
                $fill['payment_account_type'] = $change['payment_account_type'];
            }
            if (array_key_exists('min_payout_reserve', $change)) {
                $fill['min_payout_reserve'] = Money::of($change['min_payout_reserve'])->toString();
            }
            if ($fill !== []) {
                $store->forceFill($fill)->save();
            }

            return ['before' => $before, 'after' => ['payment_account_type' => (string) $store->payment_account_type, 'min_payout_reserve' => Money::of($store->min_payout_reserve ?? 0)->toString()]];
        });
    }

    /**
     * P4-32 (was "SMS bilan kirishni yoqish" and the phone field of the old superadmin user form): the owner's login
     * phone, for an owner who lost the old number. The platform vouches for it (StaffPhoneLoginService::vouch, audited
     * as staff.login_phone_*), so its holder signs in to the partner panel and the apps by SMS code; every app session
     * of the old number ends. A phone another staff account already has is refused (409 owner_phone_taken), as when
     * the store was created. Who may do it: stores.impersonate (it hands the store to whoever holds the number).
     *
     * @return array{before: array{phone: string|null}, after: array{phone: string|null}, owner: User}
     *
     * @throws StoreAdminException
     */
    public function setOwnerLoginPhone(Store $store, string $phone): array
    {
        $owner = self::owner($store);
        if ($owner === null) {
            throw new StoreAdminException("Ovqatxonaning egasi topilmadi.", 'no_owner');
        }

        $phone = trim($phone);
        if (!preg_match(\App\Services\Auth\StaffPhoneLoginService::PHONE_PATTERN, $phone)) {
            throw new StoreAdminException("Telefon +998XXXXXXXXX ko'rinishida bo'lsin.", 'owner_phone_invalid');
        }
        if (StoreProvisioner::phoneTaken($phone, (int) $owner->id)) {
            throw new StoreAdminException("Bu telefon raqami boshqa xodim hisobida bor.", 'owner_phone_taken', 409);
        }

        $before = \App\Services\Auth\StaffPhoneLoginService::loginPhone($owner);
        app(\App\Services\Auth\StaffPhoneLoginService::class)->vouch($owner, $phone);
        $owner->refresh();

        return ['before' => ['phone' => $before], 'after' => ['phone' => \App\Services\Auth\StaffPhoneLoginService::loginPhone($owner)], 'owner' => $owner];
    }

    /**
     * A new one-time invite link for the owner's Telegram; the old link stops working.
     *
     * Review F3-1: whoever opens the link binds their Telegram to the owner's account, so it is a credential:
     *  - an owner already on Telegram is never relinked (409 owner_already_linked): the link would take their account;
     *  - otherwise it goes to the owner's own phone by SMS (a phone the platform or an SMS code vouched for) and
     *    nowhere else; when that cannot be done the member sees the link only with stores.impersonate
     *    ($mayReveal; nobody has it by default except the superadmin), else 422 link_not_deliverable and the old
     *    link is left as it was.
     *
     * @return array{owner: User, owner_linked: false, delivered_via: 'sms'|null, invite_link: string|null}
     *
     * @throws StoreAdminException
     */
    public function regenerateTelegramLink(Store $store, bool $mayReveal = false): array
    {
        $owner = self::owner($store);
        if ($owner === null) {
            throw new StoreAdminException("Ovqatxonaning egasi topilmadi.", 'no_owner');
        }
        if (self::telegramChat($owner) !== null) {
            throw new StoreAdminException(
                "Egasi Telegramga allaqachon ulangan: yangi havola uning hisobini boshqa Telegramga o'tkazib yuborardi. Egasiga «Xabar yuborish» orqali yozing.",
                'owner_already_linked',
                409,
            );
        }

        $previous = $owner->telegram_invite_token;
        $token = Str::uuid()->toString();
        $owner->forceFill(['telegram_invite_token' => $token])->save();
        $link = StoreProvisioner::inviteLink($token);

        $delivered = $this->sendBySms($owner, $link);
        if (!$delivered && !$mayReveal) {
            $owner->forceFill(['telegram_invite_token' => $previous])->save();

            throw new StoreAdminException(
                "Havolani egasining telefoniga SMS bilan yuborib bo'lmadi (tasdiqlangan telefon yo'q yoki SMS ketmadi). Havolani ko'rish uchun alohida ruxsat kerak.",
                'link_not_deliverable',
            );
        }

        return ['owner' => $owner, 'owner_linked' => false, 'delivered_via' => $delivered ? 'sms' : null, 'invite_link' => $delivered ? null : $link];
    }

    private function sendBySms(User $owner, string $link): bool
    {
        $phone = trim((string) $owner->phone);
        if ($phone === '' || $owner->phone_verified_at === null) {
            return false;
        }

        try {
            return $this->sms->send($phone, "RIZQIM: Telegram hisobingizni ulash havolasi (bir marta ishlaydi, hech kimga bermang): {$link}");
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * @return array{recipients: int}
     *
     * @throws StoreAdminException
     */
    public function messageOwner(Store $store, string $text): array
    {
        if (!$this->telegram->isConfigured()) {
            throw new StoreAdminException("Telegram bot sozlanmagan: xabar yuborib bo'lmaydi.", 'telegram_not_configured', 503);
        }

        $owner = self::owner($store);
        $chat = $owner !== null ? self::telegramChat($owner) : null;
        if ($owner === null || $chat === null) {
            throw new StoreAdminException("Ovqatxona egasining Telegram akkaunti ulanmagan. Telegram havolasini yuboring.", 'no_recipient');
        }

        // Plain text (no parse mode): whatever the member typed reaches the chat as it is
        $this->telegram->sendMessage($chat, "📣 RIZQIM jamoasidan xabar\n\n" . trim($text), [], [
            'user_id' => $owner->id,
            'type' => 'admin_message_owner',
        ]);

        return ['recipients' => 1];
    }

    /**
     * The store's owner (the oldest owner account).
     */
    public static function owner(Store $store): ?User
    {
        return User::query()->where('store_id', $store->id)->where('role', UserRole::OWNER->value)->orderBy('id')->first();
    }

    /**
     * The Telegram chat of an account that joined the bot (null for an invite not used yet or a placeholder id).
     */
    public static function telegramChat(User $user): ?string
    {
        $id = $user->telegram_id;

        return $id !== null && $id !== '' && !str_starts_with((string) $id, 'temp_') ? (string) $id : null;
    }

    public static function openApplication(Store $store): ?StoreApplication
    {
        return StoreApplication::query()
            ->where('store_id', $store->id)
            ->whereNotIn('stage', [StoreApplicationStage::ACTIVE->value, StoreApplicationStage::REJECTED->value])
            ->first();
    }

    /**
     * Change the status (and the marketplace visibility) and record it in the store's status history.
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function setStatus(Store $store, StoreStatus $to, bool $isActive, ?User $member, string $comment): array
    {
        $before = ['status' => $store->status->value, 'is_active' => (bool) $store->is_active];
        // The Store model's updated hook writes the StoreStatusLog row (the member and the reason set here)
        $store->logStatusChangeAs($member, $comment)->forceFill(['status' => $to->value, 'is_active' => $isActive])->save();

        return ['before' => $before, 'after' => ['status' => $to->value, 'is_active' => $isActive]];
    }

    private function lock(Store $store): Store
    {
        return Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
    }
}
