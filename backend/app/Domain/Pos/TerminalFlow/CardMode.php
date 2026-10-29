<?php

namespace App\Domain\Pos\TerminalFlow;

/**
 * How a branch takes cards (P6-16, D5; research "Narxlar va fiskal terminallar" §4–5). From 22.10.2026 typing a sale's
 * amount into a fiscal terminal, or connecting Rizqim to the terminal's kassa app for its receipt, is not allowed
 * (VM 393, VM 943 item 25), so a branch either:
 *  - `terminal_only`: keeps its bank terminal, switched by its TXKM to "faqat terminal" (payment only, no receipt);
 *    the cashier types the terminal's transaction (RRN) number into Rizqim and Rizqim's receipt carries it;
 *  - `smart_kassa`: runs Rizqim on an Android smart kassa (Sunmi P2/P3): the card is taken on the device and the
 *    receipt goes through the fiscal provider's virtual kassa bound to it;
 *  - `none`: takes no cards (cash and QR only).
 */
enum CardMode: string
{
    case TERMINAL_ONLY = 'terminal_only';
    case SMART_KASSA = 'smart_kassa';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::TERMINAL_ONLY => 'Bank terminali "faqat terminal" rejimida',
            self::SMART_KASSA => 'Rizqim smart-kassada (provayder virtual kassasi)',
            self::NONE => "Karta qabul qilinmaydi",
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
