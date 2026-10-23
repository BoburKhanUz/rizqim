<?php

namespace App\Domain\Fiscal;

use App\Models\Branch;
use App\Models\Device;
use App\Models\FiscalReceipt;
use App\Models\Store;
use App\Support\Money;
use InvalidArgumentException;

/**
 * The rules a document must meet before it is sent (FiscalException::INVALID_DOCUMENT otherwise, Uzbek message):
 * the lines add up to the total and so do the payments; every line has a 17-digit MXIK, a unit, a positive quantity
 * and the VAT its amount and rate give; a turnover-tax store charges no VAT; only a sale (and a refund of one) may be paid by
 * prepayment (the advance offset) or on credit (nasiya), and one payment is never both; an advance or credit receipt is one line
 * of the store's deposit or credit charge; a refund names the issued sale (or advance) it refunds and never refunds
 * more than is left of it.
 *
 * A store whose fiscal mode is off (FiscalSettings::enabled() false: its receipts go to the `null` driver and are
 * non_fiscal) may have lines without an MXIK code: the menu need not be coded before the POS takes money. A code that
 * is there must still be a valid one.
 */
class FiscalDocumentValidator
{
    /** At most this many lines on one receipt */
    public const MAX_LINES = 500;

    public function validate(FiscalDocument $document, Store $store, ?FiscalReceipt $original): void
    {
        $key = trim($document->idempotencyKey);
        if ($key === '' || mb_strlen($key) > 128) {
            throw FiscalException::invalid("Chek kaliti (idempotency key) bo'sh yoki juda uzun.");
        }

        $this->checkPlace($document, $store);
        $this->checkLines($document, $store);
        $this->checkPayments($document);
        $this->checkType($document, $store, $original);
    }

    private function checkPlace(FiscalDocument $document, Store $store): void
    {
        if ($document->branchId !== null && !Branch::where('store_id', $store->id)->whereKey($document->branchId)->exists()) {
            throw FiscalException::invalid("Filial bu do'konga tegishli emas.");
        }

        if ($document->deviceId !== null && !Device::where('store_id', $store->id)->whereKey($document->deviceId)->exists()) {
            throw FiscalException::invalid("Qurilma bu do'konga tegishli emas.");
        }

        if (($document->subjectType === null) !== ($document->subjectId === null)
            || ($document->subjectType !== null && (trim($document->subjectType) === '' || mb_strlen($document->subjectType) > 64))) {
            throw FiscalException::invalid("Chek nimaga tegishliligi (subject) to'liq emas.");
        }
    }

    private function checkLines(FiscalDocument $document, Store $store): void
    {
        if ($document->lines === []) {
            throw FiscalException::invalid("Chekda kamida bitta qator bo'lishi kerak.");
        }
        if (count($document->lines) > self::MAX_LINES) {
            throw FiscalException::invalid('Chekda ko\'pi bilan ' . self::MAX_LINES . ' ta qator bo\'lishi mumkin.');
        }

        $regime = TaxRegime::ofStore($store->tax_regime);
        $fiscal = FiscalSettings::of($store)->enabled();
        $sum = Money::zero();

        foreach ($document->lines as $line) {
            $name = trim($line->name);
            if ($name === '' || mb_strlen($name) > 255) {
                throw FiscalException::invalid("Chek qatorining nomi bo'sh yoki juda uzun.");
            }
            if (trim($line->mxikCode) === '') {
                if ($fiscal) {
                    throw FiscalException::missingMxik($name);
                }
            } elseif (!preg_match(FiscalSettings::MXIK_PATTERN, $line->mxikCode)) {
                throw FiscalException::invalid("\"{$name}\": MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.");
            }
            if ($line->packageCode !== null && !preg_match(FiscalSettings::PACKAGE_CODE_PATTERN, $line->packageCode)) {
                throw FiscalException::invalid("\"{$name}\": qadoq kodi faqat raqamlardan iborat bo'lishi kerak.");
            }
            if (trim($line->unit) === '' || mb_strlen($line->unit) > 64) {
                throw FiscalException::invalid("\"{$name}\": o'lchov birligi ko'rsatilmagan.");
            }
            if (!$line->quantity->isPositive()) {
                throw FiscalException::invalid("\"{$name}\": miqdor noldan katta bo'lishi kerak.");
            }
            if ($line->amount->isNegative() || $line->unitPrice->isNegative()) {
                throw FiscalException::invalid("\"{$name}\": narx va summa manfiy bo'lishi mumkin emas.");
            }

            try {
                $rate = FiscalVat::normalizeRate($line->vatRate);
            } catch (InvalidArgumentException) {
                throw FiscalException::invalid("\"{$name}\": QQS stavkasi noto'g'ri.");
            }
            if (!$regime->chargesVat() && $rate !== '0') {
                throw FiscalException::invalid("\"{$name}\": do'kon aylanma solig'ida, chekda QQS bo'lmaydi.");
            }
            if (!$line->vatAmount->equals(FiscalVat::included($line->amount, $rate))) {
                throw FiscalException::invalid("\"{$name}\": QQS summasi stavka bo'yicha hisoblangan summaga teng emas.");
            }

            if ($line->commissionerTin !== null && !preg_match('/^(\d{9}|\d{14})$/', $line->commissionerTin)) {
                throw FiscalException::invalid("\"{$name}\": komitent STIRi 9 (yoki JShShIR 14) ta raqam bo'lishi kerak.");
            }
            if ($line->markingCode !== null && mb_strlen($line->markingCode) > 255) {
                throw FiscalException::invalid("\"{$name}\": markirovka kodi juda uzun.");
            }

            $sum = $sum->plus($line->amount);
        }

        if (!$document->total->isPositive()) {
            throw FiscalException::invalid("Chek summasi noldan katta bo'lishi kerak.");
        }
        if (!$sum->equals($document->total)) {
            throw FiscalException::invalid(
                "Chek qatorlari summasi ({$sum}) chek summasiga ({$document->total}) teng emas.",
                ['lines_total' => $sum->toString(), 'total' => $document->total->toString()],
            );
        }
    }

    private function checkPayments(FiscalDocument $document): void
    {
        if ($document->payments === []) {
            throw FiscalException::invalid("Chekda kamida bitta to'lov bo'lishi kerak.");
        }

        $sum = Money::zero();
        foreach ($document->payments as $payment) {
            if ($payment->form === FiscalPaymentForm::MIXED) {
                throw FiscalException::invalid("Har bir to'lov bitta usulda bo'ladi (naqd, karta yoki QR); \"aralash\" — chekning shakli.");
            }
            if (!$payment->amount->isPositive()) {
                throw FiscalException::invalid("To'lov summasi noldan katta bo'lishi kerak.");
            }
            // Review P3-35: a refund of a sale gives its prepayment part back to the advance and takes its credit part
            // off the debt, in the same marks as the sale showed them
            if ($payment->prepayment && !in_array($document->type, [FiscalReceiptType::SALE, FiscalReceiptType::REFUND], true)) {
                throw FiscalException::invalid("Avans hisobidan to'lov faqat sotuv chekida (va uni qaytarishda) bo'ladi.");
            }
            if ($payment->credit && !in_array($document->type, [FiscalReceiptType::SALE, FiscalReceiptType::REFUND], true)) {
                throw FiscalException::invalid("Nasiyaga (kreditga) sotish faqat sotuv chekida (va uni qaytarishda) bo'ladi.");
            }
            if ($payment->prepayment && $payment->credit) {
                throw FiscalException::invalid("Bitta to'lov ham avans hisobidan, ham nasiyaga bo'lishi mumkin emas.");
            }
            if (($payment->cardType !== null && mb_strlen($payment->cardType) > 32)
                || ($payment->transactionId !== null && mb_strlen($payment->transactionId) > 128)) {
                throw FiscalException::invalid("Karta turi yoki tranzaksiya raqami juda uzun.");
            }

            $sum = $sum->plus($payment->amount);
        }

        if (!$sum->equals($document->total)) {
            throw FiscalException::invalid(
                "To'lovlar summasi ({$sum}) chek summasiga ({$document->total}) teng emas.",
                ['payments_total' => $sum->toString(), 'total' => $document->total->toString()],
            );
        }
    }

    private function checkType(FiscalDocument $document, Store $store, ?FiscalReceipt $original): void
    {
        $type = $document->type;

        if (in_array($type, [FiscalReceiptType::ADVANCE, FiscalReceiptType::CREDIT], true)) {
            $charge = FiscalSettings::of($store)->charge($type === FiscalReceiptType::ADVANCE ? 'deposit' : 'credit');
            // Outside fiscal mode the line may carry no code (MenuFiscalLineBuilder leaves a missing one empty)
            $codeMatches = $document->lines !== [] && ($document->lines[0]->mxikCode === $charge['mxik']
                || (!FiscalSettings::of($store)->enabled() && trim($document->lines[0]->mxikCode) === ''));
            if (count($document->lines) !== 1 || !$codeMatches) {
                throw FiscalException::invalid($type === FiscalReceiptType::ADVANCE
                    ? "Bo'nak (avans) chekida bitta qator bo'ladi: avans xizmati (MXIK {$charge['mxik']})."
                    : "Kredit chekida bitta qator bo'ladi: qarz to'lovi (MXIK {$charge['mxik']}).");
            }
        }

        if ($document->originalReceiptUuid === null) {
            if ($type === FiscalReceiptType::REFUND) {
                throw FiscalException::originalReceipt("Qaytarish chekida asl sotuv cheki ko'rsatilishi kerak.");
            }

            return;
        }

        if ($original === null) {
            throw FiscalException::originalReceipt('Asl chek topilmadi.');
        }

        // A refund gives back (part of) a sale, or an advance (a cancelled booking's deposit)
        $expected = match ($type) {
            FiscalReceiptType::REFUND => [FiscalReceiptType::SALE, FiscalReceiptType::ADVANCE],
            FiscalReceiptType::CREDIT => [FiscalReceiptType::SALE],
            FiscalReceiptType::SALE => [FiscalReceiptType::ADVANCE],
            FiscalReceiptType::ADVANCE => [],
        };
        if (!in_array($original->type, $expected, true)) {
            throw FiscalException::originalReceipt(match ($type) {
                FiscalReceiptType::REFUND => "Faqat sotuv yoki bo'nak chekini qaytarish mumkin.",
                FiscalReceiptType::CREDIT => "Kredit cheki faqat sotuv chekiga bog'lanadi.",
                FiscalReceiptType::SALE => "Sotuv cheki faqat bo'nak (avans) chekiga bog'lanadi.",
                FiscalReceiptType::ADVANCE => "Bo'nak cheki boshqa chekka bog'lanmaydi.",
            });
        }

        if ($type === FiscalReceiptType::REFUND) {
            if ($original->status !== FiscalReceiptStatus::ISSUED) {
                throw FiscalException::originalReceipt("Asl chek hali ro'yxatdan o'tmagan: qaytarishni keyinroq qiling.");
            }

            $refunded = Money::of(FiscalReceipt::where('original_receipt_id', $original->id)
                ->where('type', FiscalReceiptType::REFUND->value)
                ->where('status', '!=', FiscalReceiptStatus::FAILED->value)
                ->where('idempotency_key', '!=', $document->idempotencyKey)
                ->sum('total'));
            $left = Money::of($original->total)->minus($refunded);
            if ($document->total->isGreaterThan($left)) {
                throw FiscalException::originalReceipt("Qaytarish summasi asl chekdan qolgan summadan ({$left}) oshib ketdi.");
            }
        }
    }
}
