<?php

namespace App\Domain\Pos\TerminalFlow;

/**
 * The installation checklist the owner ticks per branch (P6-16, D5 c; POS_SPEC 4.7, 4.10): what has to be true
 * before Rizqim's receipts are the branch's receipts, so a sale is never counted twice and the law's kassa equipment
 * is there. Stored in branch_terminal_setups.checklist; a branch's checklist is complete when every item is done.
 */
final class TerminalChecklist
{
    /** item => [label, hint] */
    public const ITEMS = [
        'old_kassa_unused' => [
            'Eski kassa sotuvda ishlatilmaydi',
            "Eski onlayn-NKM yoki fiskal terminalning kassa ilovasi Rizqim sotuvlariga chek chiqarmaydi: aks holda sotuv ikki marta hisoblanadi.",
        ],
        'bank_terminal_mode' => [
            'Bank terminali rejimi sozlangan',
            "Bank terminali TXKM tomonidan \"faqat terminal\" rejimiga o'tkazilgan yoki smart-kassa provayder virtual kassasiga ulangan.",
        ],
        'scanner_2d' => [
            '2D skaner bor',
            "Har bir kassada 2D skaner ulangan (qonun talabi: markirovka va QR kodlar).",
        ],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::ITEMS);
    }
}
