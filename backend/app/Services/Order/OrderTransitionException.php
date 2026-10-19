<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use RuntimeException;

class OrderTransitionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly string $reason = 'illegal',
    ) {
        parent::__construct($message);
    }

    public static function illegal(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Buyurtmani «{$from->label()}» holatidan «{$to->label()}» holatiga o'tkazib bo'lmaydi.");
    }

    /**
     * The action was offered for a status the order has since left (e.g. an old Telegram button).
     */
    public static function stale(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Buyurtmani «{$from->label()}» holatidan «{$to->label()}» holatiga o'tkazib bo'lmaydi.", 422, 'stale');
    }

    public static function forbidden(): self
    {
        return new self("Sizda bu buyurtma holatini o'zgartirishga ruxsat yo'q.", 403, 'forbidden');
    }

    public static function invalidCourier(): self
    {
        return new self("Kuryer topilmadi yoki bu do'kon/filialga tegishli emas.", 422, 'courier');
    }

    public static function noCourier(): self
    {
        return new self("Buyurtmani biriktirish uchun kuryer topilmadi.", 422, 'courier');
    }

    public static function courierNotApplicable(): self
    {
        return new self("Kuryer faqat buyurtma yetkazib berishga topshirilganda biriktiriladi.", 422, 'courier');
    }

    public static function reasonRequired(): self
    {
        return new self("Bekor qilish sababini tanlang.", 422, 'reason');
    }

    public static function reasonNotAllowed(): self
    {
        return new self("Bu sababni tanlab bo'lmaydi.", 422, 'reason');
    }

    public static function noShowNotApplicable(): self
    {
        return new self("«Mijoz kelmadi» sababi faqat qabul qilingan olib ketish buyurtmasini bekor qilishda tanlanadi.", 422, 'reason');
    }

    /**
     * The customer's own cancel: the store already took the order, or it is paid.
     */
    public static function notCancellableByCustomer(): self
    {
        return new self("Buyurtmani endi bekor qilib bo'lmaydi: u allaqachon qabul qilingan yoki to'langan.", 422, 'not_cancellable');
    }
}
