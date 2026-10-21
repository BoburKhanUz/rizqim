<?php

namespace App\Enums;

/**
 * Where an order line is in the kitchen (order_items.kitchen_status, P2-11): `pending` until a cook bumps it on the
 * kitchen display, then `ready`.
 *
 * P3-12 (docs/POS_SPEC.md §2.8, §10.3): a POS line is `pending` while it is only on the order (not sent yet, or held),
 * `sent` once the POS sends it to the kitchen, then `ready`, and `served` when it reached the guests; `void` when it was
 * taken off the order ("O'CHIRILDI"). An online (Telegram, mobile) line keeps the P2-11 meaning: `pending` is in the
 * kitchen from the start (it has no sent_at and no fire_mode).
 */
enum KitchenItemStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    // P3-12: POS lines
    case SENT = 'sent';
    case SERVED = 'served';
    case VOID = 'void';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Tayyorlanmoqda',
            self::READY => 'Tayyor',
            self::SENT => 'Oshxonaga yuborildi',
            self::SERVED => 'Berildi',
            self::VOID => "O'chirildi",
        };
    }

    /**
     * Still to be cooked: a P2-11 pending line or a POS line sent to the kitchen (a POS line not sent yet is pending
     * too, so an order with unsent lines is not "all ready").
     *
     * @return list<string>
     */
    public static function cookingValues(): array
    {
        return [self::PENDING->value, self::SENT->value];
    }

    /**
     * Done in the kitchen (the "ready" count of tickets and the board).
     *
     * @return list<string>
     */
    public static function doneValues(): array
    {
        return [self::READY->value, self::SERVED->value];
    }
}
