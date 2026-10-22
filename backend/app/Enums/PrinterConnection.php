<?php

namespace App\Enums;

/**
 * How a terminal reaches a printer (printers.connection). LAN and Bluetooth are reached by address; USB and the
 * terminal's built-in printer (Sunmi) belong to the one terminal they are attached to.
 */
enum PrinterConnection: string
{
    case LAN = 'lan';
    case USB = 'usb';
    case BUILTIN = 'builtin';
    case BLUETOOTH = 'bluetooth';

    public function label(): string
    {
        return match ($this) {
            self::LAN => 'LAN (Wi-Fi yoki Ethernet)',
            self::USB => 'USB',
            self::BUILTIN => 'Terminalning ichki printeri',
            self::BLUETOOTH => 'Bluetooth',
        };
    }

    /**
     * USB and built-in printers work only from the terminal they are attached to.
     */
    public function isDeviceBound(): bool
    {
        return in_array($this, [self::USB, self::BUILTIN], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $connection) => $connection->value, self::cases());
    }
}
