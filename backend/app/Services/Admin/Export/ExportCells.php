<?php

namespace App\Services\Admin\Export;

use App\Models\User;
use App\Services\Admin\AdminPii;
use App\Support\Money;
use App\Support\PiiMask;
use Carbon\CarbonInterface;

/**
 * P4-31: how a dataset turns values into spreadsheet cells. Texts stay texts (the writer makes every string a string
 * cell, see XlsxExporter), amounts and counts are numbers so the sheet can sum them, times are Asia/Tashkent wall time.
 * Phones go through the viewer's right to see them: masked unless admin.pii.view; a revealed phone is remembered, so the
 * export is audited as ONE reveal, not one entry per row.
 */
final class ExportCells
{
    /** The longest text Excel keeps in a cell is 32 767 characters */
    private const MAX_TEXT = 32000;

    public const TIMEZONE = 'Asia/Tashkent';

    private bool $revealed = false;

    private readonly bool $mayReveal;

    public function __construct(AdminPii $pii, ?User $viewer)
    {
        $this->mayReveal = $pii->canReveal($viewer);
    }

    public function phone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return $phone;
        }
        if (!$this->mayReveal) {
            return PiiMask::phone($phone);
        }

        $this->revealed = true;

        return PiiMask::formatPhone($phone);
    }

    public function revealedPersonalData(): bool
    {
        return $this->revealed;
    }

    public function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = is_scalar($value) || $value instanceof \Stringable ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return mb_strlen($text) > self::MAX_TEXT ? mb_substr($text, 0, self::MAX_TEXT - 1) . '…' : $text;
    }

    /**
     * An amount as a number (so'm, two decimals).
     */
    public function money(mixed $amount): ?float
    {
        return $amount === null || $amount === '' ? null : Money::of($amount)->toFloat();
    }

    public function number(mixed $value): int|float|null
    {
        return $value === null || $value === '' ? null : (is_float($value) ? $value : (int) $value);
    }

    public function time(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d H:i:s');
    }

    public function date(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d');
    }

    public function yesNo(?bool $value): ?string
    {
        return $value === null ? null : ($value ? 'Ha' : "Yo'q");
    }
}
