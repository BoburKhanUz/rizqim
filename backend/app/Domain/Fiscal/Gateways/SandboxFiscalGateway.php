<?php

namespace App\Domain\Fiscal\Gateways;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Fiscal\FiscalSnapshot;
use App\Models\Device;
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
 *
 * P6-16 (D5 b): it also plays a provider's smart kassas (SmartKassaGateway): a receipt from a POS terminal flagged
 * `smart_kassa` is answered with that kassa's id (`devices.fiscal_kassa_id`) as its terminal; a kassa is "registered"
 * once it has an id that is the store's (kassaBelongsTo: the id starts with the store's STIR, or the platform's
 * config('fiscal.sandbox.kassas') lists it under that STIR); its card totals are the card parts of its issued sale receipts less its refund receipts.
 */
class SandboxFiscalGateway implements SmartKassaGateway
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
     * Review P6 S#4, kept simple: a sandbox kassa is the taxpayer's when its id starts with the STIR (a provider's
     * kassa ids usually carry it), or when config('fiscal.sandbox.kassas') — the platform's table of sandbox kassas,
     * kassa id => STIR — lists it under that STIR. The owner cannot add to that table.
     */
    public function kassaBelongsTo(string $kassaId, string $tin): bool
    {
        $kassaId = strtoupper(trim($kassaId));
        $tin = trim($tin);
        if ($kassaId === '' || $tin === '') {
            return false;
        }
        $table = array_change_key_case((array) config('fiscal.sandbox.kassas', []), CASE_UPPER);

        return str_starts_with($kassaId, $tin) || (string) ($table[$kassaId] ?? '') === $tin;
    }

    public function kassaStatus(Device $device, Store $store): SmartKassaStatus
    {
        $kassaId = $device->getAttribute('fiscal_kassa_id');
        if (blank($kassaId)) {
            return new SmartKassaStatus(false, null, "Sinov provayderi: kassa raqami kiritilmagan.");
        }

        return filled($store->tin) && $this->kassaBelongsTo((string) $kassaId, (string) $store->tin)
            ? new SmartKassaStatus(true, (string) $kassaId, 'Sinov provayderi: kassa ulangan.')
            : new SmartKassaStatus(false, (string) $kassaId, "Sinov provayderi: bu kassa do'kon STIRiga tegishli emas.");
    }

    public function cardTotals(Device $device, Store $store, CarbonImmutable $from, CarbonImmutable $to): ?TerminalCardTotals
    {
        // Only what this provider registered (the driver names this class is configured under)
        $names = array_keys(array_filter((array) config('fiscal.drivers', []), fn ($class) => $class === static::class));
        $receipts = FiscalReceipt::query()
            ->where('store_id', $store->id)
            ->where('device_id', $device->id)
            ->whereIn('driver', $names)
            ->where('status', FiscalReceiptStatus::ISSUED->value)
            ->whereIn('type', [FiscalReceiptType::SALE->value, FiscalReceiptType::REFUND->value, FiscalReceiptType::ADVANCE->value, FiscalReceiptType::CREDIT->value])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->get(['type', 'payments']);

        $total = Money::zero();
        $count = 0;
        $transactions = [];
        foreach ($receipts as $receipt) {
            foreach ((array) $receipt->payments as $row) {
                $payment = FiscalSnapshot::payment($row);
                if ($payment->form !== FiscalPaymentForm::CARD || $payment->prepayment || $payment->credit) {
                    continue;
                }
                if ($receipt->type === FiscalReceiptType::REFUND) {
                    $total = $total->minus($payment->amount);
                    continue;
                }
                $total = $total->plus($payment->amount);
                $count++;
                $transactions[] = ['rrn' => $payment->transactionId, 'amount' => $payment->amount->toString()];
            }
        }

        return new TerminalCardTotals($total, $count, $transactions);
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(FiscalReceipt $receipt, FiscalDocument $document, Store $store): array
    {
        $terminalId = 'SANDBOX-' . str_pad((string) $store->id, 6, '0', STR_PAD_LEFT);
        // P6-16: a smart kassa's receipt is registered on its own kassa
        $device = $receipt->device_id !== null ? Device::find($receipt->device_id) : null;
        if ($device !== null && (bool) $device->getAttribute('smart_kassa') && filled($device->getAttribute('fiscal_kassa_id'))
            && filled($store->tin) && $this->kassaBelongsTo((string) $device->getAttribute('fiscal_kassa_id'), (string) $store->tin)) {
            $terminalId = (string) $device->getAttribute('fiscal_kassa_id');
        }
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
