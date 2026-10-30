<?php

namespace App\Services\Payment\Fiscal;

use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;
use Brick\Math\BigInteger;
use Throwable;

/**
 * Builds the receipt lines of an online-paid order (P3-14) with FiscalLineBuilder (P3-11): one line per order line,
 * the order's promo discount spread over them, then the service charge and place fee, then (P6-34) one line per
 * packaging line. The line amounts in tiyin add
 * up exactly to the order total, so the provider's receipt equals the money it took.
 */
class OnlineFiscalReceiptBuilder
{
    /** The charge FiscalLineBuilder::forCharge is asked for the delivery fee (needs a store-level MXIK code). */
    public const DELIVERY_CHARGE = 'delivery';

    /**
     * Whether the store issues fiscal receipts (stores.fiscal_settings.enabled, P3-11). Read defensively: the column
     * may not exist yet, or come back as raw JSON when it is not cast.
     */
    public function isEnabled(?Store $store): bool
    {
        if (!$store) {
            return false;
        }

        $settings = $store->getAttribute('fiscal_settings');
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        return is_array($settings) && filter_var($settings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The restaurant's TIN (9 digits, a company) or PINFL (14 digits, a sole proprietor) put on the receipt as the
     * komitent: stores.tin (P3-11), else the older stores.tax_number. Null when neither is a valid number.
     */
    public function commissionerTin(?Store $store): ?string
    {
        foreach (['tin', 'tax_number'] as $column) {
            $value = preg_replace('/\D/', '', (string) $store?->getAttribute($column));
            if ($value !== null && (strlen($value) === 9 || strlen($value) === 14)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  bool  $deliveryAsLine  Click needs the delivery fee as a receipt line with its own MXIK; Payme sends it
     *                                as `shipping` (title and price only)
     */
    public function build(Order $order, bool $deliveryAsLine, bool $evenIfDisabled = false): OnlineFiscalReceipt
    {
        $store = $order->store;
        $total = Money::of($order->total_price);

        if (!$store || (!$evenIfDisabled && !$this->isEnabled($store))) {
            return OnlineFiscalReceipt::disabled($total);
        }

        $problems = [];
        $details = [];
        $tin = $this->commissionerTin($store);
        if ($tin === null) {
            $problems[] = 'store_tin_missing';
        }

        if (!app()->bound(FiscalLineBuilder::class)) {
            $problems[] = 'fiscal_unavailable';

            return new OnlineFiscalReceipt(true, $tin, [], Money::zero(), null, $total, $problems);
        }

        $builder = app(FiscalLineBuilder::class);
        /** @var \Illuminate\Database\Eloquent\Collection<int, OrderItem> $items */
        // A voided line (P3-12, "O'CHIRILDI") is not sold
        $items = $order->items()->orderBy('id')->get()->filter(fn (OrderItem $item) => $item->getAttribute('voided_at') === null)->values();
        [$itemsAmount, $delivery] = $this->split($order, $items);
        $shares = $this->allocate($items->map(fn (OrderItem $item) => Money::of($item->total_price)->toTiyin())->all(), $itemsAmount->toTiyin());

        $lines = [];
        // P6-31: a combo is one line (its own MXIK) unless the store splits combos by their parts
        $splitCombos = \App\Domain\Fiscal\FiscalSettings::of($store)->comboFiscalMode() === \App\Domain\Fiscal\ComboFiscalMode::SPLIT;
        foreach ($items->values() as $index => $item) {
            $quantity = Quantity::of($item->getRawOriginal('quantity') ?? $item->quantity);
            $receiptParts = $splitCombos && $item->isComboLine()
                ? \App\Domain\Fiscal\ComboFiscalSplit::shares($item, $shares[$index], $quantity)
                : [['item' => $item, 'amount' => $shares[$index], 'quantity' => $quantity]];

            foreach ($receiptParts as $part) {
                $name = (string) $part['item']->product_name;

                try {
                    $line = $builder->forOrderItem($part['item'], $part['quantity'], Money::fromTiyin($part['amount']));
                } catch (Throwable $e) {
                    // FiscalLineBuilder refuses a dish or service without MXIK in fiscal mode
                    $problems[] = 'mxik_missing';
                    $details['items'][] = $name;

                    continue;
                }

                $lineProblems = $this->lineProblems($line);
                if ($lineProblems !== []) {
                    $problems = [...$problems, ...$lineProblems];
                    $details['items'][] = $name;
                }
                $lines[] = $this->withCommissioner($line, $tin);
            }
        }

        if ($items->isEmpty()) {
            $problems[] = 'items_missing';
        }

        foreach (['service_charge' => $order->service_charge_amount, 'place_fee' => $order->place_fee_amount] as $charge => $amount) {
            $amount = Money::of($amount);
            if (!$amount->isPositive()) {
                continue;
            }

            try {
                $line = $builder->forCharge($store, $charge, $amount);
            } catch (Throwable $e) {
                $problems[] = 'charge_mxik_missing';
                $details['charges'][] = $charge;

                continue;
            }

            $problems = [...$problems, ...$this->lineProblems($line)];
            $lines[] = $this->withCommissioner($line, $tin);
        }

        // P6-34: the packaging lines, each with its own MXIK (PackagingFiscal)
        if (Money::of($order->getAttribute('packaging_amount'))->isPositive()) {
            try {
                foreach (\App\Domain\Pos\Takeaway\PackagingFiscal::linesOf($store, $order) as $line) {
                    $problems = [...$problems, ...$this->lineProblems($line)];
                    $lines[] = $this->withCommissioner($line, $tin);
                }
            } catch (Throwable $e) {
                $problems[] = 'charge_mxik_missing';
                $details['charges'][] = \App\Domain\Pos\Takeaway\PackagingFiscal::CHARGE;
            }
        }

        $deliveryLine = null;
        if ($deliveryAsLine && $delivery->isPositive()) {
            try {
                $deliveryLine = $this->withCommissioner($builder->forCharge($store, self::DELIVERY_CHARGE, $delivery), $tin);
                $problems = [...$problems, ...$this->lineProblems($deliveryLine)];
            } catch (Throwable $e) {
                $problems[] = 'delivery_mxik_missing';
                $details['charges'][] = self::DELIVERY_CHARGE;
            }
        }

        $sum = Money::sum(...array_map(fn (FiscalLine $line) => $line->amount, $lines))->plus($deliveryLine ? $deliveryLine->amount : $delivery);
        if ($problems === [] && !$sum->equals($total)) {
            // The order's stored totals do not add up from its lines (e.g. edited by hand): never send a wrong receipt
            $problems[] = 'sum_mismatch';
        }

        return new OnlineFiscalReceipt(true, $tin, $lines, $delivery, $deliveryLine, $total, array_values(array_unique($problems)), $details);
    }

    /**
     * Lines alone, as complete as they can be, for recording a receipt the provider has already registered (whatever
     * the store's switch says now).
     *
     * @return list<FiscalLine>
     */
    public function recordedLines(Order $order): array
    {
        return $this->build($order, true, true)->linesWithDelivery();
    }

    /**
     * What the order's lines are paid after the promo discount, and the delivery fee, as CreateOrderAction totals
     * them: max(0, items - discount + delivery) + service charge + place fee. A discount above the items eats into
     * the delivery fee.
     *
     * @param  \Illuminate\Support\Collection<int, OrderItem>  $items
     * @return array{0: Money, 1: Money}
     */
    protected function split(Order $order, $items): array
    {
        $itemsTotal = Money::sum(...$items->map(fn (OrderItem $item) => $item->total_price)->all());
        $afterDiscount = $itemsTotal->minus($order->discount);
        $delivery = Money::of($order->delivery_price);

        if (!$afterDiscount->plus($delivery)->isPositive()) {
            return [Money::zero(), Money::zero()];
        }

        if ($afterDiscount->isNegative()) {
            return [Money::zero(), $afterDiscount->plus($delivery)];
        }

        return [$afterDiscount, $delivery];
    }

    /**
     * Splits $target tiyin over the weights in proportion (largest remainder, ties to the earlier line), so the
     * shares add up exactly and the same order always gets the same split.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    public function allocate(array $weights, int $target): array
    {
        $totalWeight = array_sum($weights);
        if ($weights === [] || $totalWeight <= 0) {
            return array_map(fn () => 0, $weights);
        }

        $shares = [];
        $remainders = [];
        foreach ($weights as $index => $weight) {
            $exact = BigInteger::of($target)->multipliedBy(max(0, $weight));
            $shares[$index] = $exact->quotient($totalWeight)->toInt();
            $remainders[$index] = $exact->remainder($totalWeight)->toInt();
        }

        $left = $target - array_sum($shares);
        $order = array_keys($remainders);
        usort($order, fn (int $a, int $b) => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        foreach (array_slice($order, 0, $left) as $index) {
            $shares[$index]++;
        }

        return array_values($shares);
    }

    /**
     * @return list<string>
     */
    protected function lineProblems(FiscalLine $line): array
    {
        $problems = [];
        if (trim($line->mxikCode) === '') {
            $problems[] = 'mxik_missing';
        }
        // Both providers require the package code with the MXIK code
        if (trim((string) $line->packageCode) === '') {
            $problems[] = 'package_code_missing';
        }

        return $problems;
    }

    protected function withCommissioner(FiscalLine $line, ?string $tin): FiscalLine
    {
        if ($line->commissionerTin !== null || $tin === null) {
            return $line;
        }

        return new FiscalLine(
            $line->name,
            $line->mxikCode,
            $line->packageCode,
            $line->unit,
            $line->quantity,
            $line->unitPrice,
            $line->amount,
            $line->vatRate,
            $line->vatAmount,
            $tin,
            $line->markingCode,
        );
    }
}
