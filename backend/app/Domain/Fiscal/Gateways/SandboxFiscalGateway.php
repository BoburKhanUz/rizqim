<?php

namespace App\Domain\Fiscal\Gateways;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalSettings;
use App\Models\FiscalReceipt;
use App\Models\Store;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * A fake virtual kassa for development, tests and demos. It answers like a provider would: a terminal id, a receipt
 * number, and for sales and refunds a 12-digit fiscal sign and a QR link; advance and credit receipts get neither.
 * All of it is derived from the receipt uuid, so the same receipt always gets the same answer. Every receipt is
 * marked non_fiscal: none of this has legal force. Like a provider it reports what part of a sale was not received
 * now: `prepaid` (the advance offset) and `credit` (sold on nasiya).
 *
 * Behaviour per store (stores.fiscal_settings.sandbox): `latency_ms` waits before answering (at most
 * config('fiscal.sandbox.max_latency_ms')); `fail_attempts` N makes the first N attempts of every receipt fail as
 * "unreachable" (retried); `failure_rate` P fails P% of attempts the same way (decided by uuid and attempt number,
 * so it is repeatable); `reject` refuses every receipt (not retried: the receipt becomes `failed`).
 */
class SandboxFiscalGateway implements FiscalGateway
{
    private const CACHE_PREFIX = 'fiscal:sandbox:';

    public function register(FiscalReceipt $receipt, FiscalDocument $document, Store $store): FiscalProviderResponse
    {
        $behaviour = FiscalSettings::of($store)->sandbox();

        if ($behaviour['latency_ms'] > 0) {
            usleep($behaviour['latency_ms'] * 1000);
        }

        if ($behaviour['reject']) {
            throw FiscalGatewayException::rejected('Sinov provayderi chekni rad etdi (sandbox: reject).');
        }

        $attempt = max(1, (int) $receipt->attempts);
        if ($attempt <= $behaviour['fail_attempts']
            || ($behaviour['failure_rate'] > 0 && crc32("{$receipt->uuid}:{$attempt}") % 100 < $behaviour['failure_rate'])) {
            throw FiscalGatewayException::unavailable("Sinov provayderi javob bermadi ({$attempt}-urinish).");
        }

        $answer = $this->answer($receipt, $document, $store);
        Cache::put(self::CACHE_PREFIX . $receipt->uuid, $answer, now()->addDays(2));

        return $this->response($answer);
    }

    public function status(FiscalReceipt $receipt, Store $store): ?FiscalProviderResponse
    {
        $answer = Cache::get(self::CACHE_PREFIX . $receipt->uuid);

        return is_array($answer) ? $this->response($answer) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(FiscalReceipt $receipt, FiscalDocument $document, Store $store): array
    {
        $terminalId = 'SANDBOX-' . str_pad((string) $store->id, 6, '0', STR_PAD_LEFT);
        $receiptNumber = (string) (hexdec(substr(sha1("receipt:{$receipt->uuid}"), 0, 8)) % 100000000);
        $time = CarbonImmutable::parse($receipt->created_at ?? now())->setTimezone('Asia/Tashkent');
        $fiscal = in_array($document->type, [FiscalReceiptType::SALE, FiscalReceiptType::REFUND], true);

        $fiscalSign = null;
        $qrUrl = null;
        if ($fiscal) {
            $fiscalSign = str_pad((string) (hexdec(substr(sha1("sign:{$receipt->uuid}"), 0, 12)) % 1000000000000), 12, '0', STR_PAD_LEFT);
            $qrUrl = rtrim((string) config('fiscal.sandbox.qr_base_url'), '?') . '?' . http_build_query([
                't' => $terminalId,
                'r' => $receiptNumber,
                'c' => $time->format('YmdHis'),
                's' => $fiscalSign,
            ]);
        }

        $prepaid = Money::zero();
        $credit = Money::zero();
        foreach ($document->payments as $payment) {
            if ($payment->prepayment) {
                $prepaid = $prepaid->plus($payment->amount);
            }
            if ($payment->credit) {
                $credit = $credit->plus($payment->amount);
            }
        }

        return [
            'sandbox' => true,
            'type' => $document->type->value,
            'terminal_id' => $terminalId,
            'receipt_number' => $receiptNumber,
            'fiscal_sign' => $fiscalSign,
            'qr_url' => $qrUrl,
            'date' => $time->toIso8601String(),
            'total' => $document->total->toString(),
            'prepaid' => $prepaid->toString(),
            'credit' => $credit->toString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function response(array $answer): FiscalProviderResponse
    {
        return new FiscalProviderResponse(
            fiscalSign: $answer['fiscal_sign'] ?? null,
            receiptNumber: $answer['receipt_number'] ?? null,
            qrUrl: $answer['qr_url'] ?? null,
            terminalId: $answer['terminal_id'] ?? null,
            nonFiscal: true,
            payload: $answer,
            issuedAt: CarbonImmutable::now(),
        );
    }
}
