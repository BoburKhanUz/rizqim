<?php

namespace App\Domain\Fiscal;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A receipt that cannot be made: `reason` is a stable code callers and clients branch on, the message is Uzbek and
 * fit to show to the cashier. Left uncaught in an API request it answers 422 in the standard error shape
 * ({success: false, message, data: {reason, ...context}}).
 */
class FiscalException extends RuntimeException
{
    /** A menu item (or a charge) has no MXIK code while the store sells fiscally ("MXIK'siz sotilmaydi") */
    public const MXIK_MISSING = 'mxik_missing';
    /** The document does not add up or breaks a receipt type's rules (FiscalDocumentValidator) */
    public const INVALID_DOCUMENT = 'invalid_document';
    /** The idempotency key was already used for a different receipt (another type or total) */
    public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';
    /** A refund's original receipt is missing, another store's, not a sale, not issued, or already refunded */
    public const ORIGINAL_RECEIPT = 'original_receipt_invalid';
    /** FiscalLineBuilder::forCharge with a charge it does not know */
    public const UNKNOWN_CHARGE = 'unknown_charge';

    /**
     * @param  array<string, mixed>  $context  extra machine-readable fields for the response (`data`), next to `reason`
     */
    public function __construct(public readonly string $reason, string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }

    public static function missingMxik(string $name, ?int $productId = null): self
    {
        return new self(
            self::MXIK_MISSING,
            "\"{$name}\" uchun MXIK kodi kiritilmagan. Fiskal rejimda MXIK'siz taom yoki xizmat sotilmaydi: menyuda MXIK kodini kiriting.",
            array_filter(['product_id' => $productId, 'name' => $name], fn ($value) => $value !== null),
        );
    }

    public static function chargeWithoutMxik(string $charge, string $name): self
    {
        return new self(
            self::MXIK_MISSING,
            "\"{$name}\" uchun MXIK kodi sozlanmagan. Fiskal sozlamalarda shu xizmat uchun MXIK kodini kiriting.",
            ['charge' => $charge, 'name' => $name],
        );
    }

    public static function invalid(string $message, array $context = []): self
    {
        return new self(self::INVALID_DOCUMENT, $message, $context);
    }

    public static function idempotencyConflict(string $receiptUuid): self
    {
        return new self(
            self::IDEMPOTENCY_CONFLICT,
            "Bu kalit bilan boshqa chek allaqachon ro'yxatdan o'tgan (turi yoki summasi farq qiladi).",
            ['receipt_uuid' => $receiptUuid],
        );
    }

    public static function originalReceipt(string $message): self
    {
        return new self(self::ORIGINAL_RECEIPT, $message);
    }

    /**
     * Wave M3: the original receipt is in a month taken offline (archived after config partitions.tables
     * .fiscal_receipts.online_months): its row, status and earlier refunds are not in the database any more.
     */
    public static function originalArchived(string $month): self
    {
        return new self(
            self::ORIGINAL_RECEIPT,
            "Asl chek {$month} oyida berilgan va arxivga o'tkazilgan: uni tizim orqali qaytarib bo'lmaydi. Qaytarishni fiskal provayder kabinetida yoki buxgalter bilan qo'lda rasmiylashtiring.",
            ['archived_month' => $month],
        );
    }

    /**
     * Wave M3: the idempotency key belongs to a receipt in an archived month; a second receipt is never written under
     * it.
     */
    public static function keyArchived(string $receiptUuid, string $month): self
    {
        return new self(
            self::IDEMPOTENCY_CONFLICT,
            "Bu kalit bilan chek {$month} oyida berilgan va arxivga o'tkazilgan: ikkinchi chek yozilmaydi.",
            ['receipt_uuid' => $receiptUuid, 'archived_month' => $month],
        );
    }

    public static function unknownCharge(string $charge): self
    {
        return new self(self::UNKNOWN_CHARGE, "Noma'lum xizmat turi: {$charge}.", ['charge' => $charge]);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['reason' => $this->reason] + $this->context;
    }

    /**
     * Laravel renders the exception with this: a 422 for API requests, the default page otherwise.
     */
    public function render(Request $request): ?JsonResponse
    {
        if (!$request->is('api/*') && !$request->expectsJson()) {
            return null;
        }

        return response()->json(['success' => false, 'message' => $this->getMessage(), 'data' => $this->data()], 422);
    }
}
