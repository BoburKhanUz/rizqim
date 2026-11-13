<?php

namespace App\Domain\Pos\QrPayments\UzQr;

use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;

/**
 * P6-42: the UzQR sandbox. The payload is a deterministic EMVCo merchant-presented QR (point of initiation 12:
 * dynamic; merchant account template 26 with a sandbox GUID and the request's id; currency 860, amount, country UZ,
 * merchant name and city, reference label in 62.05, CRC-16/CCITT-FALSE in 63) that no real bank app can pay.
 * It is confirmed by POST /pos/qr/{uuid}/simulate-paid; status() stays `pending` (the simulation confirms directly);
 * refunds always succeed, in part too.
 */
final class SandboxUzQrGateway implements UzQrGateway
{
    public const GUID = 'uz.rizqim.sandbox';

    public function createDynamicQr(Money $amount, PosQrPayment $qr, Store $store): UzQrDynamicQr
    {
        $id = 'sbx-' . str_replace('-', '', (string) $qr->uuid);

        return new UzQrDynamicQr($id, self::payload($amount, $id, (string) $store->name, (string) config('qr_payments.uzqr.sandbox_city', 'TASHKENT'), substr(str_replace('-', '', (string) $qr->uuid), 0, 25)));
    }

    public function status(string $id, Store $store): array
    {
        return ['status' => 'pending', 'transaction_id' => null];
    }

    public function cancel(string $id, Store $store): void {}

    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function refund(string $id, Money $amount, Store $store): QrRefundResult
    {
        return QrRefundResult::done('sbx-refund-' . substr(sha1($id . $amount->toString() . microtime()), 0, 12));
    }

    /**
     * The EMV string (tag + two-digit length + value), with its CRC.
     */
    public static function payload(Money $amount, string $id, string $merchant, string $city, string $reference): string
    {
        $ascii = fn (string $value, int $max) => mb_substr(trim((string) preg_replace('/[^A-Za-z0-9 .\-]/', '', \Illuminate\Support\Str::ascii($value))), 0, $max) ?: 'RIZQIM';
        $tlv = fn (string $tag, string $value) => $tag . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;

        $body = $tlv('00', '01')
            . $tlv('01', '12')
            . $tlv('26', $tlv('00', self::GUID) . $tlv('01', mb_substr($id, 0, 40)))
            . $tlv('52', '5812')
            . $tlv('53', '860')
            . $tlv('54', $amount->toString())
            . $tlv('58', 'UZ')
            . $tlv('59', $ascii($merchant, 25))
            . $tlv('60', $ascii($city, 15))
            . $tlv('62', $tlv('05', $reference));
        $body .= '6304';

        return $body . self::crc($body);
    }

    /**
     * CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF), as EMVCo requires, 4 upper hex digits.
     */
    public static function crc(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
