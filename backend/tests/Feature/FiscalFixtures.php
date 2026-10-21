<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalVat;
use App\Enums\OrderStatus;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;

/**
 * P3-11 helpers: a store in fiscal mode, order lines, receipt lines and documents.
 */
trait FiscalFixtures
{
    use ManagementFixtures;

    protected const OSH_MXIK = '10202001001000001';

    protected const CHOY_MXIK = '10202001005000001';

    protected const ROOM_MXIK = '10202002001000001';

    protected function setUpFiscal(array $settings = [], string $regime = 'vat'): void
    {
        $this->setUpManagement();
        $this->product->forceFill(['mxik_code' => self::OSH_MXIK, 'package_code' => '1234567'])->save();
        $this->fiscalMode($this->store, $settings, $regime);
    }

    protected function fiscalMode(Store $store, array $settings = [], string $regime = 'vat'): void
    {
        $store->forceFill([
            'tin' => '301234567',
            'tax_regime' => $regime,
            'fiscal_settings' => array_replace_recursive(['enabled' => true, 'driver' => 'sandbox'], $settings),
        ])->save();
    }

    protected function issuer(): FiscalIssuer
    {
        return app(FiscalIssuer::class);
    }

    protected function lines(): FiscalLineBuilder
    {
        return app(FiscalLineBuilder::class);
    }

    /**
     * An order line of the fixture product: $quantity × 50 000 (no modifiers) unless told otherwise.
     */
    protected function orderItem(string $quantity = '2', string $unitPrice = '50000', ?Order $order = null): OrderItem
    {
        $order ??= $this->makeOrder(OrderStatus::NEW, ['total_price' => Money::of($unitPrice)->times($quantity)->toString()]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Osh',
            'variant_name' => "To'y oshi",
            'quantity' => $quantity,
            'unit' => 'piece',
            'unit_price' => $unitPrice,
            'total_price' => Money::of($unitPrice)->times($quantity)->toString(),
        ]);
    }

    /**
     * A plain receipt line (no menu behind it) with the VAT its rate gives.
     */
    protected function line(string $amount, string $mxik = self::OSH_MXIK, string $rate = '12', string $name = 'Osh'): FiscalLine
    {
        return new FiscalLine(
            name: $name,
            mxikCode: $mxik,
            packageCode: null,
            unit: 'dona',
            quantity: Quantity::of(1),
            unitPrice: Money::of($amount),
            amount: Money::of($amount),
            vatRate: $rate,
            vatAmount: FiscalVat::included(Money::of($amount), $rate),
        );
    }

    protected function cash(string $amount, bool $prepayment = false): FiscalPayment
    {
        return new FiscalPayment(FiscalPaymentForm::CASH, Money::of($amount), $prepayment);
    }

    protected function card(string $amount, string $transactionId = 'TX-1'): FiscalPayment
    {
        return new FiscalPayment(FiscalPaymentForm::CARD, Money::of($amount), false, 'personal', $transactionId);
    }

    /**
     * @param  list<FiscalLine>|null  $lines
     * @param  list<FiscalPayment>|null  $payments
     */
    protected function document(
        FiscalReceiptType $type = FiscalReceiptType::SALE,
        ?array $lines = null,
        ?array $payments = null,
        ?string $total = null,
        ?string $key = null,
        ?string $original = null,
        ?Store $store = null,
        bool $withBranch = true,
        ?string $subjectType = 'order',
        ?int $subjectId = 1,
    ): FiscalDocument {
        $lines ??= [$this->line('100000')];
        $total ??= Money::sum(...array_map(fn (FiscalLine $line) => $line->amount, $lines))->toString();
        $payments ??= [$this->cash($total)];
        $store ??= $this->store;

        return new FiscalDocument(
            type: $type,
            storeId: (int) $store->id,
            branchId: $withBranch ? (int) ($store->is($this->store) ? $this->branch->id : $this->foreignBranch->id) : null,
            deviceId: null,
            lines: $lines,
            payments: $payments,
            total: Money::of($total),
            idempotencyKey: $key ?? 'key-' . bin2hex(random_bytes(6)),
            originalReceiptUuid: $original,
            subjectType: $subjectType,
            subjectId: $subjectId,
        );
    }

    protected function receipt(string $uuid): FiscalReceipt
    {
        return FiscalReceipt::where('uuid', $uuid)->firstOrFail();
    }
}
