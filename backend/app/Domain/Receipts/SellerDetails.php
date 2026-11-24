<?php

namespace App\Domain\Receipts;

use App\Models\Store;

/**
 * Wave P (receipts): who sells the food, as a customer is shown it before ordering and on the order (consumer law
 * art. 28-1: the seller's name, address and how to complain; ZRU-792 art. 16; VM 943 1-ilova 7: the supplier's legal
 * address). The restaurant is the seller; RIZQIM is the marketplace operator.
 *
 * Exactly these fields reach a customer (the public store payload's `seller`, an order's `seller` and its
 * orders.seller_snapshot): legal name (set by RIZQIM when the store is admitted), STIR (`tin`, else the older
 * `tax_number`; 9 digits only, never a sole proprietor's personal 14-digit JShShIR), legal address and complaint
 * contact (the owner's), phone (the store's, else the branch's). An empty value is null; the Mini App leaves a
 * missing field out.
 */
final class SellerDetails
{
    public const FIELDS = ['legal_name', 'tin', 'legal_address', 'phone', 'complaint_contact'];

    /**
     * @return array{legal_name: string|null, tin: string|null, legal_address: string|null, phone: string|null, complaint_contact: string|null}
     */
    public static function of(Store $store, ?string $branchPhone = null): array
    {
        return [
            'legal_name' => self::text($store->getAttribute('legal_name')),
            'tin' => self::tin($store->getAttribute('tin')) ?? self::tin($store->getAttribute('tax_number')),
            'legal_address' => self::text($store->getAttribute('legal_address')),
            'phone' => self::text($store->getAttribute('phone')) ?? self::text($branchPhone),
            'complaint_contact' => self::text($store->getAttribute('complaint_contact')),
        ];
    }

    /**
     * A stored snapshot read back: only the known fields, as strings or null (never anything else it might hold).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{legal_name: string|null, tin: string|null, legal_address: string|null, phone: string|null, complaint_contact: string|null}
     */
    public static function fromSnapshot(array $snapshot): array
    {
        return [
            'legal_name' => self::text($snapshot['legal_name'] ?? null),
            'tin' => self::tin($snapshot['tin'] ?? null),
            'legal_address' => self::text($snapshot['legal_address'] ?? null),
            'phone' => self::text($snapshot['phone'] ?? null),
            'complaint_contact' => self::text($snapshot['complaint_contact'] ?? null),
        ];
    }

    private static function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 500);
    }

    /**
     * A STIR (9 digits) as stored; anything else is not shown. A 14-digit JShShIR (PINFL) is a sole proprietor's
     * personal ID: it never reaches the public payload, an order's `seller` or a snapshot read back.
     */
    private static function tin(mixed $value): ?string
    {
        $value = self::text($value);

        return $value !== null && preg_match('/^\d{9}$/', $value) === 1 ? $value : null;
    }
}
