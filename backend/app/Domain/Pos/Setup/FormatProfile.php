<?php

namespace App\Domain\Pos\Setup;

use App\Enums\StoreFormat;

/**
 * P6-11: a branch's format profile (branches.pos_settings.profile, docs/PHASE6_PLAN.md §6, docs/POS_SPEC.md "Format
 * profili"): a ready set, not a lock. It gives the till's mode and first screen and the default modules; the owner
 * switches single modules on or off (pos_settings.modules) except where the law locks one (stolovaya: no service
 * charge, VM 75). Existing branches are `choyxona` (the phase 3 flow, unchanged).
 */
enum FormatProfile: string
{
    case CHOYXONA = 'choyxona';
    case OSH_MARKAZI = 'osh_markazi';
    case LAGMONXONA = 'lagmonxona';
    case SOMSAXONA = 'somsaxona';
    case TAMADDIXONA = 'tamaddixona';
    case STOLOVAYA = 'stolovaya';
    case KAFE = 'kafe';
    case RESTORAN = 'restoran';
    case KABOBXONA = 'kabobxona';
    case BALIQXONA = 'baliqxona';
    case FAST_FOOD = 'fast_food';
    case QAHVAXONA = 'qahvaxona';
    case TOYXONA = 'toyxona';
    case DARK_KITCHEN = 'dark_kitchen';

    public const DEFAULT = self::CHOYXONA;

    /** The till's first screens (format.first_screen). */
    public const FIRST_SCREENS = ['hall_map', 'quick_counter', 'booking_calendar', 'online_orders'];

    public function label(): string
    {
        return match ($this) {
            self::CHOYXONA => 'Choyxona',
            self::OSH_MARKAZI => 'Osh markazi',
            self::LAGMONXONA => "Lag'monxona",
            self::SOMSAXONA => 'Somsaxona',
            self::TAMADDIXONA => 'Tamaddixona',
            self::STOLOVAYA => 'Stolovaya (oshxona-liniya)',
            self::KAFE => 'Kafe',
            self::RESTORAN => 'Restoran',
            self::KABOBXONA => 'Kabobxona, shashlikxona',
            self::BALIQXONA => 'Baliqxona',
            self::FAST_FOOD => 'Fast food',
            self::QAHVAXONA => 'Qahvaxona',
            self::TOYXONA => "To'yxona, banket",
            self::DARK_KITCHEN => 'Faqat yetkazish (dark kitchen)',
        };
    }

    /**
     * How the place works, in plain words, for the profile picker.
     */
    public function description(): string
    {
        return match ($this) {
            self::CHOYXONA => "Ofitsiantlar xona va tapchanlarga xizmat qiladi: xona puli, choy-non, bron, nasiya, sexlar.",
            self::OSH_MARKAZI => "Mijoz kassada buyurtma beradi va to'laydi; qozon qoldig'i, ulushlar (1 / 0,7 / 2), raqam.",
            self::LAGMONXONA => "Zal yoki kassa: porsiya taomlar, oshxona markasi, buyurtma raqami.",
            self::SOMSAXONA => "Kassa yoki telefon: dona va kg savdo, tandir partiyasi, \"keyingi partiya\", oldindan buyurtma.",
            self::TAMADDIXONA => "Ega o'zi kassir, telefonda ishlaydi: tayyor mahsulot, partiya va hisobdan chiqarish.",
            self::STOLOVAYA => "O'z-o'ziga xizmat liniyasi, oxirida kassa: bugungi menyu, kompleks. Xizmat haqi qonun bo'yicha 0%.",
            self::KAFE => "Zal va kassa yonma-yon: ofitsiant, stolga olib kelish, buyurtma raqami, xizmat haqi.",
            self::RESTORAN => "Zal xaritasi, ofitsiantlar, sexlar, bron va xizmat haqi.",
            self::KABOBXONA => "Zal xaritasi, mangal sexi, six soni, xizmat haqi.",
            self::BALIQXONA => "Zal xaritasi, kg narx × haqiqiy og'irlik, mangal sexi.",
            self::FAST_FOOD => "Kassa, buyurtma raqami va \"Tayyor\" ekrani, kombo, oshxona ekranlari.",
            self::QAHVAXONA => "Kassa → barista: ichimlik va modifikatorlar, buyurtma raqami, mijoz ekrani.",
            self::TOYXONA => "Tadbirlar oldindan sotiladi: zal va sana bo'yicha bron, zaklad va to'lovlar.",
            self::DARK_KITCHEN => "Faqat yetkazish: Telegram va boshqa kanallar buyurtmalari bitta navbatda, oshxona ekranlari.",
        };
    }

    /**
     * The till's mode (pos_settings.mode) a profile starts with; the owner may still change it.
     */
    public function defaultMode(): string
    {
        return match ($this) {
            self::CHOYXONA, self::LAGMONXONA, self::KAFE => 'hybrid',
            self::RESTORAN, self::KABOBXONA, self::BALIQXONA, self::TOYXONA => 'table',
            default => 'counter',
        };
    }

    /**
     * The screen the till opens on, for the profile's own mode (PosModules::firstScreen adapts it to another mode).
     */
    public function firstScreen(): string
    {
        return match ($this) {
            self::CHOYXONA, self::RESTORAN, self::KABOBXONA, self::BALIQXONA => 'hall_map',
            self::TOYXONA => 'booking_calendar',
            self::DARK_KITCHEN => 'online_orders',
            default => 'quick_counter',
        };
    }

    /**
     * The modules a profile switches on (docs/PHASE6_PLAN.md §6). `terminal_card_flow` is on everywhere: a bank
     * terminal next to the till is the common case, and the flow only adds an optional transaction number.
     *
     * @return list<PosModule>
     */
    public function modules(): array
    {
        $modules = match ($this) {
            // Phase 3 as it is: the quick counter button next to the hall map, the qozon panel
            self::CHOYXONA => [PosModule::QUICK_COUNTER, PosModule::ROOMS, PosModule::ROOM_FEE, PosModule::GUEST_ITEMS, PosModule::BOOKINGS, PosModule::CUSTOMER_ACCOUNTS, PosModule::SERVICE_CHARGE, PosModule::KDS, PosModule::BATCHES],
            self::OSH_MARKAZI => [PosModule::QUICK_COUNTER, PosModule::QUEUE_NUMBERS, PosModule::QUEUE_CALLING, PosModule::BATCHES, PosModule::BATCH_WRITEOFF, PosModule::SERVICE_CHARGE],
            self::LAGMONXONA => [PosModule::QUICK_COUNTER, PosModule::QUEUE_NUMBERS, PosModule::QUEUE_CALLING, PosModule::SERVICE_CHARGE, PosModule::KDS, PosModule::PACKAGING],
            self::SOMSAXONA => [PosModule::QUICK_COUNTER, PosModule::PHONE_MODE, PosModule::BATCHES, PosModule::BATCH_WRITEOFF, PosModule::BOOKINGS, PosModule::PACKAGING],
            self::TAMADDIXONA => [PosModule::QUICK_COUNTER, PosModule::PHONE_MODE, PosModule::BATCHES, PosModule::BATCH_WRITEOFF, PosModule::PACKAGING],
            self::STOLOVAYA => [PosModule::QUICK_COUNTER, PosModule::DAILY_MENU, PosModule::COMBO_MEAL, PosModule::CUSTOMER_ACCOUNTS, PosModule::CUSTOMER_DISPLAY],
            // Wave 2: the counter order carried to the table plate, the service charge by zone (order type)
            self::KAFE => [PosModule::QUICK_COUNTER, PosModule::QUEUE_NUMBERS, PosModule::QUEUE_CALLING, PosModule::CUSTOMER_DISPLAY, PosModule::SERVICE_CHARGE, PosModule::KDS, PosModule::TABLE_SERVICE, PosModule::SERVICE_CHARGE_RULES, PosModule::WAITER_TRANSFER],
            self::RESTORAN => [PosModule::BOOKINGS, PosModule::SERVICE_CHARGE, PosModule::KDS, PosModule::CUSTOMER_ACCOUNTS, PosModule::COURSES, PosModule::TIPS, PosModule::STAFF_PAY, PosModule::GUEST_SPLIT, PosModule::WAITER_TRANSFER, PosModule::HOSTESS, PosModule::TABLE_BOOKING, PosModule::SERVICE_CHARGE_RULES],
            // The assorti is a kompleks (combo_meal, P6-15): one line, its parts listed for the grill
            self::KABOBXONA => [PosModule::SERVICE_CHARGE, PosModule::KDS, PosModule::BOOKINGS, PosModule::SKEWER_BUTTONS, PosModule::COMBO_MEAL, PosModule::WAITER_TRANSFER],
            self::BALIQXONA => [PosModule::SERVICE_CHARGE, PosModule::KDS, PosModule::WEIGHT_LATER, PosModule::WAITER_TRANSFER],
            // Wave 3: the combo, packaging, the expo screen and the pickup time; 24/7 is the owner's switch
            self::FAST_FOOD => [PosModule::QUICK_COUNTER, PosModule::QUEUE_NUMBERS, PosModule::QUEUE_CALLING, PosModule::CUSTOMER_DISPLAY, PosModule::COMBO_MEAL, PosModule::KDS, PosModule::COMBO, PosModule::QUICK_MODIFIERS, PosModule::LABELS, PosModule::KDS_EXPO, PosModule::PACKAGING, PosModule::SCHEDULED_PICKUP],
            self::QAHVAXONA => [PosModule::QUICK_COUNTER, PosModule::QUEUE_NUMBERS, PosModule::QUEUE_CALLING, PosModule::CUSTOMER_DISPLAY, PosModule::KDS, PosModule::QUICK_MODIFIERS, PosModule::LABELS, PosModule::PACKAGING, PosModule::SCHEDULED_PICKUP],
            self::TOYXONA => [PosModule::BOOKINGS, PosModule::ROOMS, PosModule::CUSTOMER_ACCOUNTS],
            self::DARK_KITCHEN => [PosModule::QUICK_COUNTER, PosModule::KDS, PosModule::COMBO, PosModule::PACKAGING, PosModule::KDS_EXPO],
        };
        $modules[] = PosModule::TERMINAL_CARD_FLOW;

        return $modules;
    }

    /**
     * The law's lock (VM 75): a stolovaya charges no service charge, and it cannot be switched on.
     */
    public function locksServiceCharge(): bool
    {
        return $this === self::STOLOVAYA;
    }

    /**
     * The modules the owner may not switch on in this profile.
     *
     * @return list<PosModule>
     */
    public function lockedOff(): array
    {
        return $this->locksServiceCharge() ? [PosModule::SERVICE_CHARGE] : [];
    }

    /**
     * The plan the profile is meant for (docs/PHASE6_PLAN.md §6): a hint on the picker, never a gate.
     */
    public function recommendedPlan(): string
    {
        return match ($this) {
            self::OSH_MARKAZI, self::LAGMONXONA, self::SOMSAXONA, self::TAMADDIXONA, self::STOLOVAYA, self::QAHVAXONA => 'start',
            self::TOYXONA, self::DARK_KITCHEN => 'pro',
            default => 'asosiy',
        };
    }

    /**
     * P4-22: the profile a new store starts with from its application's `format`. `canteen` ("Oshxona") is the
     * self-service line (stolovaya); `bakery` the tandir and batch place (somsaxona); `other` the general hybrid (kafe).
     */
    public static function fromStoreFormat(StoreFormat|string|null $format): self
    {
        $format = is_string($format) ? StoreFormat::tryFrom($format) : $format;

        return match ($format) {
            StoreFormat::RESTAURANT => self::RESTORAN,
            StoreFormat::CAFE => self::KAFE,
            StoreFormat::FAST_FOOD => self::FAST_FOOD,
            StoreFormat::CANTEEN => self::STOLOVAYA,
            StoreFormat::COFFEE_SHOP => self::QAHVAXONA,
            StoreFormat::BAKERY => self::SOMSAXONA,
            StoreFormat::TEAHOUSE => self::CHOYXONA,
            StoreFormat::CATERING => self::TOYXONA,
            StoreFormat::OTHER => self::KAFE,
            null => self::DEFAULT,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $profile) => $profile->value, self::cases());
    }
}
