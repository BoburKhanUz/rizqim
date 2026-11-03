<?php

namespace App\Domain\Pos\Menu;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * P6-31: a combo pick the server refuses (422, validation envelope with a `code` and `data.reason`, bootstrap/app.php):
 *
 *  - `combo_option_invalid`: the option is not this combo's (or the pick is malformed);
 *  - `combo_option_unavailable`: the option's dish is stopped, sold out, off today's menu or switched off now (a stale
 *    pick: the client refreshes the menu and asks again);
 *  - `combo_slot_required`: a required slot has fewer picks than its minimum (and no default to take);
 *  - `combo_slot_limit`: a slot has more picks than its maximum.
 *
 * The error key is `items.{index}.combo`, like a line's other fields.
 */
class ComboPickException extends ValidationException
{
    public function __construct(public readonly string $reason, string $key, string $message)
    {
        parent::__construct(Validator::make([], []));
        $this->validator->errors()->add($key, $message);
        $this->message = $message;
    }

    public static function invalid(int $index, string $message): self
    {
        return new self('combo_option_invalid', "items.{$index}.combo", $message);
    }

    public static function unavailable(int $index, string $combo, string $option): self
    {
        $prefix = $combo !== '' ? "'{$combo}': " : '';

        return new self('combo_option_unavailable', "items.{$index}.combo", "{$prefix}'{$option}' hozir mavjud emas. Boshqasini tanlang yoki menyuni yangilang.");
    }

    public static function required(int $index, string $combo, string $slot, int $min): self
    {
        return new self('combo_slot_required', "items.{$index}.combo", $min === 1
            ? "'{$combo}': '{$slot}' tanlovi majburiy."
            : "'{$combo}': '{$slot}' dan kamida {$min} ta tanlang.");
    }

    public static function limit(int $index, string $combo, string $slot, int $max): self
    {
        return new self('combo_slot_limit', "items.{$index}.combo", "'{$combo}': '{$slot}' dan ko'pi bilan {$max} ta tanlash mumkin.");
    }
}
