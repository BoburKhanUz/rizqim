<?php

namespace App\Enums;

/**
 * Kinds of message a person can switch on or off per channel (GET/PUT /me/notification-preferences).
 */
enum NotificationType: string
{
    // The customer's own order: accepted, preparing, on the way, delivered, cancelled, refunded
    case ORDER_UPDATES = 'order_updates';
    case PROMOTIONS = 'promotions';
    // A new order for the store's managers (the owner when the store has no manager)
    case STAFF_NEW_ORDER = 'staff_new_order';
    // An order that is ready or was handed to the courier (managers and owners stand in when there is no courier)
    case COURIER_TASKS = 'courier_tasks';
    // P3 integration fix (R20): the till's money decisions for the owner — voids after sending, waived charges,
    // cancelled orders, deposits refunded or retained, nasiya, rooms open too long (App\Domain\Pos\Accounts\PosOwnerAlerts)
    case POS_ALERTS = 'pos_alerts';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_UPDATES => 'Buyurtma holati',
            self::PROMOTIONS => 'Aksiyalar va chegirmalar',
            self::STAFF_NEW_ORDER => 'Yangi buyurtmalar',
            self::COURIER_TASKS => 'Kuryer topshiriqlari',
            self::POS_ALERTS => 'Kassa ogohlantirishlari',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ORDER_UPDATES => "Buyurtmangiz qabul qilinishi, tayyorlanishi, yo'lga chiqishi va yetkazilishi haqida",
            self::PROMOTIONS => 'Yangi aksiyalar va maxsus takliflar haqida',
            self::STAFF_NEW_ORDER => "Do'koningizga yangi buyurtma kelganda",
            self::COURIER_TASKS => 'Buyurtma tayyor bo\'lganda yoki sizga biriktirilganda',
            self::POS_ALERTS => "Kassada: yuborilgan taom o'chirilsa, xona puli yoki xizmat haqi olib tashlansa, buyurtma bekor qilinsa, avans qaytarilsa yoki ushlab qolinsa, nasiya, xona uzoq ochiq qolsa",
        };
    }

    /**
     * Channels the person can choose between for this type.
     *
     * @return list<NotificationChannel>
     */
    public function channels(): array
    {
        return [NotificationChannel::TELEGRAM, NotificationChannel::PUSH];
    }

    /**
     * The setting before the person ever touched it: everything that is about their own orders or work is on (Telegram
     * as it always was), promotions need an explicit opt-in.
     */
    public function defaultEnabled(NotificationChannel $channel): bool
    {
        return $this !== self::PROMOTIONS && in_array($channel, $this->channels(), true);
    }

    /**
     * Whether this type makes sense for the person's role (a customer is not shown "new orders").
     */
    public function appliesTo(UserRole $role): bool
    {
        return match ($this) {
            self::ORDER_UPDATES => true,
            self::PROMOTIONS => $role === UserRole::CUSTOMER,
            self::STAFF_NEW_ORDER => in_array($role, [UserRole::OWNER, UserRole::MANAGER], true),
            self::COURIER_TASKS => in_array($role, [UserRole::COURIER, UserRole::MANAGER, UserRole::OWNER], true),
            self::POS_ALERTS => $role === UserRole::OWNER,
        };
    }

    /**
     * Which of the person's app installs a push of this type goes to.
     *
     * @return list<DeviceType>
     */
    public function deviceTypes(): array
    {
        return match ($this) {
            self::ORDER_UPDATES, self::PROMOTIONS => [DeviceType::CUSTOMER_MOBILE, DeviceType::WEB, DeviceType::PARTNER_MOBILE, DeviceType::COURIER],
            self::STAFF_NEW_ORDER, self::COURIER_TASKS => [DeviceType::PARTNER_MOBILE, DeviceType::COURIER, DeviceType::WEB],
            self::POS_ALERTS => [DeviceType::PARTNER_MOBILE, DeviceType::WEB],
        };
    }

    /**
     * @return list<self>
     */
    public static function forRole(UserRole $role): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->appliesTo($role)));
    }
}
