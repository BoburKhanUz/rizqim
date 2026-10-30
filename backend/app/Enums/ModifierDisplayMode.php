<?php

namespace App\Enums;

/**
 * P6-32 (B2): where a modifier group's options are shown on the cashier's tile (modifier_groups.display_mode). A hint
 * for the till and the waiter app: the server sells the same options whichever way they were picked.
 *
 *  - dialog: the modifier window, as before (the default);
 *  - inline: the options as chips beside the menu tile, no window;
 *  - size: a one-tap size selector ("Kichik / O'rta / Katta"); the group takes exactly one option (max_select 1).
 */
enum ModifierDisplayMode: string
{
    case DIALOG = 'dialog';
    case INLINE = 'inline';
    case SIZE = 'size';

    public function label(): string
    {
        return match ($this) {
            self::DIALOG => 'Oyna orqali',
            self::INLINE => 'Tugma yonida',
            self::SIZE => 'Hajm tanlash (bir bosish)',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $mode) => $mode->value, self::cases());
    }
}
