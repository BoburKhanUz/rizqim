<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Fiscal\FiscalVat;
use App\Domain\Fiscal\TaxRegime;
use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Models\BanquetSetting;
use App\Models\Store;
use App\Support\Money;
use App\Support\Quantity;

/**
 * P6-53: the receipt lines of banquet money. An advance (deposit, interim) is the ADVANCE receipt's one line; a
 * retained part of a cancelled event is the "Bekor qilish haqi" service line of a SALE receipt. The MXIK codes come
 * from the banquet settings (advance_mxik, cancellation_mxik; UNVERIFIED: the accountant confirms the codes); when a
 * store set none, the store's fiscal charges `deposit` and `booking_fee` are used (in fiscal mode a missing code
 * refuses the money before it is recorded, FiscalException::MXIK_MISSING).
 */
final class BanquetFiscalLines
{
    public function advance(Store $store, Money $amount, BanquetPaymentKind $kind): FiscalLine
    {
        $name = match ($kind) {
            BanquetPaymentKind::DEPOSIT => 'Tadbir zakladi (avans)',
            BanquetPaymentKind::INTERIM => "Tadbir uchun oraliq to'lov (avans)",
            BanquetPaymentKind::FINAL => "Tadbir uchun to'lov (avans)",
        };
        $setting = BanquetSetting::where('store_id', $store->id)->first();

        return $this->line($store, 'deposit', $amount, $name, $setting?->advance_mxik, $setting?->advance_package_code);
    }

    public function cancellation(Store $store, Money $amount): FiscalLine
    {
        $setting = BanquetSetting::where('store_id', $store->id)->first();

        return $this->line($store, 'booking_fee', $amount, 'Bekor qilish haqi', $setting?->cancellation_mxik, $setting?->cancellation_package_code);
    }

    private function line(Store $store, string $charge, Money $amount, string $name, ?string $mxik, ?string $packageCode): FiscalLine
    {
        if (blank($mxik)) {
            $line = app(FiscalLineBuilder::class)->forCharge($store, $charge, $amount);

            return new FiscalLine($name, $line->mxikCode, $line->packageCode, $line->unit, $line->quantity, $line->unitPrice, $line->amount, $line->vatRate, $line->vatAmount);
        }
        $rate = TaxRegime::ofStore($store->tax_regime)->defaultVatRate();

        return new FiscalLine(
            name: $name,
            mxikCode: (string) $mxik,
            packageCode: filled($packageCode) ? (string) $packageCode : null,
            unit: FiscalSettings::of($store)->charge($charge)['unit'],
            quantity: Quantity::of(1),
            unitPrice: $amount,
            amount: $amount,
            vatRate: $rate,
            vatAmount: FiscalVat::included($amount, $rate),
        );
    }
}
