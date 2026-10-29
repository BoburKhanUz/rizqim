<?php

namespace App\Domain\Pos\Setup;

use App\Enums\PlanFeature;

/**
 * P6-11: a POS module a branch's format profile switches on or off (docs/POS_SPEC.md, "Format profili"). The effective
 * modules of a branch are its profile's defaults with the owner's overrides (pos_settings.modules), minus what the law
 * locks (stolovaya: no service charge) and minus what the store's plan does not have (planFeature(), P4-21). The one
 * check is PosModules::enabled(); routes use the `pos.module:<key>` middleware (403 module_disabled).
 *
 * Modules gate SETUP and new flows only: taking payments, closing bills, receipts, shifts and refunds never ask.
 */
enum PosModule: string
{
    // Wave 1 "Tez kassa"
    case QUICK_COUNTER = 'quick_counter';
    case PHONE_MODE = 'phone_mode';
    case QUEUE_NUMBERS = 'queue_numbers';
    case QUEUE_CALLING = 'queue_calling';
    case CUSTOMER_DISPLAY = 'customer_display';
    case DAILY_MENU = 'daily_menu';
    case COMBO_MEAL = 'combo_meal';
    case BATCHES = 'batches';
    case BATCH_WRITEOFF = 'batch_writeoff';
    case TERMINAL_CARD_FLOW = 'terminal_card_flow';

    // What the choyxona already has (phase 3)
    case ROOMS = 'rooms';
    case ROOM_FEE = 'room_fee';
    case GUEST_ITEMS = 'guest_items';
    case BOOKINGS = 'bookings';
    case CUSTOMER_ACCOUNTS = 'customer_accounts';
    case SERVICE_CHARGE = 'service_charge';
    case KDS = 'kds';

    // Wave 2 "Restoran va kafe" (docs/PHASE6_PLAN.md §9)
    case COURSES = 'courses';
    case TIPS = 'tips';
    case STAFF_PAY = 'staff_pay';
    case GUEST_SPLIT = 'guest_split';
    case WAITER_TRANSFER = 'waiter_transfer';
    case HOSTESS = 'hostess';
    case TABLE_BOOKING = 'table_booking';
    case SKEWER_BUTTONS = 'skewer_buttons';
    case WEIGHT_LATER = 'weight_later';
    case TABLE_SERVICE = 'table_service';
    case SERVICE_CHARGE_RULES = 'service_charge_rules';

    public function label(): string
    {
        return match ($this) {
            self::QUICK_COUNTER => 'Tez kassa',
            self::PHONE_MODE => 'Telefon rejimi',
            self::QUEUE_NUMBERS => 'Buyurtma raqami',
            self::QUEUE_CALLING => 'Raqamni chaqirish',
            self::CUSTOMER_DISPLAY => 'Mijoz ekrani',
            self::DAILY_MENU => 'Bugungi menyu',
            self::COMBO_MEAL => 'Kompleks',
            self::BATCHES => 'Partiya va qozon',
            self::BATCH_WRITEOFF => 'Hisobdan chiqarish',
            self::TERMINAL_CARD_FLOW => 'Bank terminali oqimi',
            self::ROOMS => 'Xonalar va tapchanlar',
            self::ROOM_FEE => 'Xona puli',
            self::GUEST_ITEMS => 'Choy-non',
            self::BOOKINGS => 'Bron va oldindan buyurtma',
            self::CUSTOMER_ACCOUNTS => 'Nasiya',
            self::SERVICE_CHARGE => 'Xizmat haqi',
            self::KDS => 'Oshxona ekrani (KDS)',
            self::COURSES => 'Kurslar',
            self::TIPS => 'Choy puli',
            self::STAFF_PAY => 'Ofitsiant ish haqi',
            self::GUEST_SPLIT => "Mehmon bo'yicha bo'lish",
            self::WAITER_TRANSFER => "Stolni boshqa ofitsiantga o'tkazish",
            self::HOSTESS => 'Hostes',
            self::TABLE_BOOKING => 'Stol broni',
            self::SKEWER_BUTTONS => 'Six tugmalari',
            self::WEIGHT_LATER => "Og'irlikni keyin kiritish",
            self::TABLE_SERVICE => 'Stolga olib kelish',
            self::SERVICE_CHARGE_RULES => 'Xizmat haqi qoidalari',
        };
    }

    /**
     * One plain sentence for the owner's "Format" page.
     */
    public function description(): string
    {
        return match ($this) {
            self::QUICK_COUNTER => "Kassa menyu panjarasidan boshlanadi: 1–3 bosishda buyurtma, \"To'lash\" va \"Keyingi mijoz\".",
            self::PHONE_MODE => "Ega o'zi kassir: Android telefon uchun ixcham ekran, printer ixtiyoriy.",
            self::QUEUE_NUMBERS => 'Har bir kassa buyurtmasiga raqam va talon (internetsiz ham takrorlanmaydi).',
            self::QUEUE_CALLING => "Buyurtma tayyor bo'lganda raqamni ovoz va ekranda chaqirish.",
            self::CUSTOMER_DISPLAY => "Mijozga ko'rinadigan ikkinchi ekran yoki \"Tayyor\" raqamlar ekrani (qurilmalar soniga kirmaydi).",
            self::DAILY_MENU => "Har kuni sotiladigan taomlarni yoqish, kechagi menyuni nusxalash.",
            self::COMBO_MEAL => "Belgilangan narxdagi to'plam (kompleks): bitta qator, tarkibi oshxonaga yoziladi.",
            self::BATCHES => "Qozon yoki tandir partiyasi: qoldiq hisoblagichi va \"keyingi partiya HH:MM\".",
            self::BATCH_WRITEOFF => 'Sotilmagan qoldiqni sabab bilan hisobdan chiqarish va hosil hisoboti.',
            self::TERMINAL_CARD_FLOW => "Bank terminalidagi karta to'lovi tranzaksiya raqami bilan, kun oxirida Z bilan solishtirish.",
            self::ROOMS => "Zal xaritasida xona va tapchanlar (oddiy stollar doim bor).",
            self::ROOM_FEE => "Xona va tapchan uchun soatbay yoki kunlik narx (tarif, bayram kunlari).",
            self::GUEST_ITEMS => "Mehmon soniga qarab choy va non avtomatik qo'shiladi.",
            self::BOOKINGS => "Xona yoki stolni oldindan band qilish, bo'nak va oldindan buyurtma.",
            self::CUSTOMER_ACCOUNTS => "Doimiy mijozlarga nasiya hisobi: qarzga yozish va to'lovni qabul qilish.",
            self::SERVICE_CHARGE => 'Zal yoki xona bo\'yicha xizmat haqi foizi, chekda alohida qator.',
            self::KDS => 'Oshxona va sexlarda buyurtmalarni ekranda ko\'rsatish (printer o\'rniga yoki u bilan).',
            self::COURSES => "Taomlar kurs bo'yicha: keyingi kurs oshxonaga \"N-kursni yuborish\" bilan chiqadi, kurs tayyor bo'lsa ofitsiantga xabar.",
            self::TIPS => "Choy puli to'lovda alohida olinadi: tushum emas, fiskal chekka kirmaydi, ofitsiantga yoki umumiy qozonga.",
            self::STAFF_PAY => "Ofitsiant va xodimlar ish haqi qoidalari: smena, soat, sotuvdan %, garant + %, davr hisobi.",
            self::GUEST_SPLIT => "Schyotni mehmon (o'rin) raqami bo'yicha bo'lish.",
            self::WAITER_TRANSFER => "Ochiq stolni boshqa ofitsiantga o'tkazish (menejer), eski ofitsiantga xabar.",
            self::HOSTESS => "Hostes: bronlar, stollar holati va kutish ro'yxati.",
            self::TABLE_BOOKING => "Stolni stol turi bo'yicha vaqt oralig'iga band qilish, ixtiyoriy depozit.",
            self::SKEWER_BUTTONS => "Six va dona taomlar uchun ×2…×10 tez tugmalari, assorti va mangal kutish vaqti.",
            self::WEIGHT_LATER => "Taom tanlanadi, og'irlik tortilgach kiritiladi (kim tortdi); schyotda haqiqiy kg.",
            self::TABLE_SERVICE => "Kassada to'lab, stol raqami tablichkasi bilan: taom stolga olib boriladi.",
            self::SERVICE_CHARGE_RULES => "Xizmat haqi buyurtma turi bo'yicha, katta guruh qoidasi, mehmon boshiga \"cover\" va e'lon matni.",
        };
    }

    /**
     * The plan feature (P4-21) a store needs for this module; null: every plan has it (docs/PHASE6_PLAN.md §7: the quick
     * counter family, numbers, the customer display, daily menu and batches are on Start; wave 2: skewer buttons,
     * weight later, table service and the waiter transfer too, while courses, the hostess, table booking, guest split,
     * the service charge rules, tips and staff pay are Asosiy+). Wave 2 reuses the Asosiy plan features instead of
     * adding new ones, so the catalog and the plans already sold do not change: courses go with the kitchen display,
     * the hostess and table booking with bookings, the bill and staff money (guest split, service charge rules, tips,
     * staff pay) with the service charge.
     */
    public function planFeature(): ?PlanFeature
    {
        return match ($this) {
            self::ROOMS => PlanFeature::HALL,
            self::ROOM_FEE => PlanFeature::ROOM_FEE,
            self::GUEST_ITEMS => PlanFeature::GUEST_ITEMS,
            self::BOOKINGS => PlanFeature::BOOKINGS,
            self::CUSTOMER_ACCOUNTS => PlanFeature::CUSTOMER_ACCOUNTS,
            self::SERVICE_CHARGE => PlanFeature::SERVICE_CHARGE,
            self::KDS => PlanFeature::KDS,
            self::COURSES => PlanFeature::KDS,
            self::HOSTESS, self::TABLE_BOOKING => PlanFeature::BOOKINGS,
            self::GUEST_SPLIT, self::SERVICE_CHARGE_RULES, self::TIPS, self::STAFF_PAY => PlanFeature::SERVICE_CHARGE,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $module) => $module->value, self::cases());
    }
}
