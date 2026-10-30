<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Domain\Fiscal\FiscalReadiness;
use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Fiscal\TaxRegime;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Models\Store;
use App\Services\Audit\AuditLogger;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P3-11: the store's fiscal settings (TIN, tax regime, fiscal mode and driver, MXIK codes of the charges) and what
 * still blocks fiscal selling (products and charges without MXIK). settings.manage, never from a terminal PIN session;
 * the readiness list also for menu.view.
 */
#[Group('Owner: fiscal', "Fiskal sozlamalar (STIR, soliq rejimi, drayver, xizmatlar MXIK kodlari), fiskal cheklar ro'yxati va qayta yuborish (P3-11).")]
class FiscalSettingsController extends ManagementController
{
    public const CHARGE = 'array{name: string, mxik: string|null, package_code: string|null, unit: string}';

    public const SETTINGS = 'array{tin: string|null, tax_regime: \'vat\'|\'vat_simplified\'|\'turnover\'|null, fiscal_settings: array{enabled: bool, driver: string, active_driver: string, smart_kassa_driver: string, charges: array{room_fee: ' . self::CHARGE . ', place_fee: ' . self::CHARGE . ', min_spend_topup: ' . self::CHARGE . ', service_charge: ' . self::CHARGE . ', deposit: ' . self::CHARGE . ', booking_fee: ' . self::CHARGE . ', credit: ' . self::CHARGE . ', delivery: ' . self::CHARGE . '}, sandbox: array{latency_ms: int, fail_attempts: int, failure_rate: int, reject: bool}, deposit_refund_receipt: bool|null, retained_deposit_receipt: bool|null, combo_fiscal_mode: ' . \App\Domain\Fiscal\ComboFiscalMode::class . '}, drivers: list<string>, tax_regimes: list<array{value: string, label: string, vat_rate: string}>}';

    private const CHARGE_IN = 'array{name?: string|null, mxik?: string|null, package_code?: string|null, unit?: string|null}';

    /**
     * Fiscal settings
     *
     * The store's TIN and tax regime, and its fiscal settings with every default filled in. `active_driver` is where
     * receipts go now (`null` while fiscal mode is off: receipts are recorded as "fiskal emas"); P6-16:
     * `smart_kassa_driver` is where a smart kassa's receipts go in fiscal mode.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::SETTINGS . '}')]
    public function show(Request $request): JsonResponse
    {
        return $this->success(self::present($this->currentStore($request)));
    }

    /**
     * Update fiscal settings
     *
     * Each field is optional (left out = kept). `tin` 9 digits; `tax_regime` vat (12%) | vat_simplified (6%) |
     * turnover (no VAT). `fiscal_settings.charges.<charge>` sets the receipt line of a charge (room_fee, place_fee,
     * min_spend_topup, service_charge, deposit, booking_fee, credit, delivery): `mxik` 17 digits, `package_code`
     * digits; a null field goes back to the default. `deposit_refund_receipt` / `retained_deposit_receipt`: whether a
     * booking deposit given back / kept gets a receipt (null: the default). P6-16: `smart_kassa_driver` — the driver
     * of POS terminals flagged smart kassa (the provider's virtual kassa bound to the device; null: `driver`). Turning
     * `enabled` on needs the TIN and the tax regime.
     */
    #[BodyParameter('tin', description: "STIR: 9 ta raqam (null — o'chirish).", required: false, type: 'string|null')]
    #[BodyParameter('tax_regime', description: "vat | vat_simplified | turnover.", required: false, type: "'vat'|'vat_simplified'|'turnover'|null")]
    #[BodyParameter('fiscal_settings', description: 'Fiskal sozlamalar (obyekt, qisman).', required: false, infer: false, type: 'array{enabled?: bool, driver?: string|null, smart_kassa_driver?: string|null, charges?: array{room_fee?: ' . self::CHARGE_IN . ', place_fee?: ' . self::CHARGE_IN . ', min_spend_topup?: ' . self::CHARGE_IN . ', service_charge?: ' . self::CHARGE_IN . ', deposit?: ' . self::CHARGE_IN . ', booking_fee?: ' . self::CHARGE_IN . ', credit?: ' . self::CHARGE_IN . ', delivery?: ' . self::CHARGE_IN . '}, sandbox?: array{latency_ms?: int, fail_attempts?: int, failure_rate?: int, reject?: bool}, deposit_refund_receipt?: bool|null, retained_deposit_receipt?: bool|null, combo_fiscal_mode?: ' . \App\Domain\Fiscal\ComboFiscalMode::class . '|null}')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::SETTINGS . '}')]
    public function update(Request $request, AuditLogger $audit): JsonResponse
    {
        $store = $this->currentStore($request);
        $before = self::auditView($store);

        $update = FiscalSettings::validatedUpdate($request->only(['tin', 'tax_regime', 'fiscal_settings']), $store);
        if ($update !== []) {
            $store->forceFill($update)->save();
            $audit->record('settings.fiscal_changed', $store, $before, self::auditView($store), [], ['store_id' => $store->id]);
        }

        return $this->success(self::present($store), 'Fiskal sozlamalar saqlandi.');
    }

    /**
     * Fiscal readiness
     *
     * What blocks selling in fiscal mode: products without a valid MXIK code (they cannot be put on a POS order
     * while fiscal mode is on) and charges without one. menu.view or settings.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{enabled: bool, tin_set: bool, tax_regime: string|null, products_missing_mxik: list<array{id: int, uuid: string, name: string, kind: string, status: string, category_id: int}>, charges_missing_mxik: list<string>}}')]
    public function readiness(Request $request, FiscalReadiness $readiness): JsonResponse
    {
        $store = $this->currentStore($request);

        return $this->success([
            'enabled' => $readiness->enabled($store),
            'tin_set' => filled($store->tin),
            'tax_regime' => $store->tax_regime,
            'products_missing_mxik' => $readiness->missingMxik($store),
            'charges_missing_mxik' => $readiness->chargesWithoutMxik($store),
        ]);
    }

    /**
     * The settings as GET /owner/fiscal/settings returns them (also part of GET /owner/settings).
     *
     * @return array<string, mixed>
     */
    public static function present(Store $store): array
    {
        return [
            'tin' => $store->tin,
            'tax_regime' => $store->tax_regime,
            'fiscal_settings' => FiscalSettings::of($store)->toArray(),
            'drivers' => array_keys((array) config('fiscal.drivers', [])),
            'tax_regimes' => array_map(fn (TaxRegime $regime) => [
                'value' => $regime->value,
                'label' => $regime->label(),
                'vat_rate' => $regime->defaultVatRate(),
            ], TaxRegime::cases()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function auditView(Store $store): array
    {
        return [
            'tin' => $store->tin,
            'tax_regime' => $store->tax_regime,
            'fiscal_settings' => $store->fiscal_settings,
        ];
    }
}
