<?php

namespace App\Services\Admin\Customers;

/**
 * P4-31: the words of a complaint in Mijozlar va yordam: its type (complaints.type, what the customer picked) and the
 * customer ↔ store flow (complaints.status) beside the support status (App\Enums\ComplaintSupportStatus).
 */
final class ComplaintLabels
{
    public const TYPES = [
        'WRONG_ITEM' => "Noto'g'ri mahsulot",
        'ORDER_NOT_RECEIVED' => 'Buyurtma kelmadi',
        'QUALITY_ISSUE' => 'Sifat muammosi',
        'COURIER_ISSUE' => 'Kuryer muammosi',
        'PAYMENT_ISSUE' => "To'lov muammosi",
        'OTHER' => 'Boshqa',
    ];

    public const STORE_FLOW = [
        'pending' => 'Ovqatxona javobini kutmoqda',
        'owner_replied' => 'Ovqatxona javob berdi',
        'in_review' => 'Mijoz RIZQIMga yubordi',
        'closed' => 'Yopilgan',
    ];

    public static function type(?string $type): string
    {
        return self::TYPES[(string) $type] ?? (string) $type;
    }

    public static function storeFlow(?string $status): string
    {
        return self::STORE_FLOW[(string) $status] ?? (string) $status;
    }
}
