<?php

namespace App\Domain\Pos\Orders;

use RuntimeException;

/**
 * A POS order action that cannot be done as asked (P3-12): the HTTP status, a machine-readable `data.reason` and the
 * Uzbek message the till shows. `version_conflict` (409) carries the order's current version: the app reloads the
 * order and applies the action again or shows it to the staff member (docs/POS_SPEC.md §12.3).
 */
class PosOrderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(string $message, public readonly int $status, public readonly string $reason, public readonly array $data = [])
    {
        parent::__construct($message);
    }

    public static function versionConflict(int $current): self
    {
        return new self("Buyurtma boshqa qurilmada o'zgartirildi. Uni qayta yuklang va amalni takrorlang.", 409, 'version_conflict', ['version' => $current]);
    }

    public static function notPos(): self
    {
        return new self("Bu buyurtma kassa (POS) buyurtmasi emas: uni bu yerda o'zgartirib bo'lmaydi.", 422, 'not_pos_order');
    }

    public static function closed(): self
    {
        return new self("Buyurtma yopilgan yoki bekor qilingan: uni o'zgartirib bo'lmaydi.", 422, 'order_closed');
    }

    public static function forbidden(string $message = 'Ruxsat etilmagan'): self
    {
        return new self($message, 403, 'forbidden');
    }
}
