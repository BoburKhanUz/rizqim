<?php

namespace App\Domain\Fiscal;

use App\Models\Store;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A store's fiscal settings (stores.fiscal_settings, jsonb) read with their defaults:
 *
 *     {
 *       "enabled": false,                 // fiscal mode: receipts go to `driver`; off: the `null` driver ("fiskal emas")
 *       "driver": "sandbox",              // a key of config('fiscal.drivers'); null: config('fiscal.default_driver')
 *       "charges": {                      // receipt lines that are not menu items (FiscalLineBuilder::forCharge)
 *         "room_fee": {"mxik": "1020200…", "package_code": null, "unit": "xizmat", "name": "Xona puli"},
 *         …                               // also place_fee, min_spend_topup, service_charge, deposit, booking_fee,
 *       },                                // credit, delivery
 *       "sandbox": {"latency_ms": 0, "fail_attempts": 0, "failure_rate": 0, "reject": false},
 *       "deposit_refund_receipt": null,   // P3-17 booking deposits (FLAGS); null: their default
 *       "retained_deposit_receipt": null
 *     }
 *
 * A charge field left out (or null) takes config('fiscal.charges.<key>.<field>').
 */
final class FiscalSettings
{
    /** The charges FiscalLineBuilder::forCharge knows, in display order */
    public const CHARGES = ['room_fee', 'place_fee', 'min_spend_topup', 'service_charge', 'deposit', 'booking_fee', 'credit', 'delivery'];

    /**
     * Receipt choices of the booking deposit flow (P3-17, App\Domain\Pos\Booking\PosSettingsReader): a REFUND
     * receipt when a deposit is given back, a SALE receipt (booking service) when it is retained. null: P3-17's
     * defaults (config/pos_bookings.php).
     */
    public const FLAGS = ['deposit_refund_receipt', 'retained_deposit_receipt'];

    public const MXIK_PATTERN = '/^\d{17}$/';

    public const PACKAGE_CODE_PATTERN = '/^\d{1,20}$/';

    public const TIN_PATTERN = '/^\d{9}$/';

    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(private readonly array $raw) {}

    public static function of(Store $store): self
    {
        $raw = $store->fiscal_settings;

        return new self(is_array($raw) ? $raw : []);
    }

    public function enabled(): bool
    {
        return (bool) ($this->raw['enabled'] ?? false);
    }

    /**
     * The driver chosen for fiscal mode (whether or not it is on).
     */
    public function configuredDriver(): string
    {
        $driver = $this->raw['driver'] ?? null;
        $drivers = array_keys((array) config('fiscal.drivers', []));

        return is_string($driver) && in_array($driver, $drivers, true) ? $driver : (string) config('fiscal.default_driver', 'sandbox');
    }

    /**
     * The driver receipts go to now: the configured one in fiscal mode, else `null`.
     */
    public function driver(): string
    {
        return $this->enabled() ? $this->configuredDriver() : 'null';
    }

    /**
     * @return array{name: string, mxik: string|null, package_code: string|null, unit: string}
     */
    public function charge(string $charge): array
    {
        if (!in_array($charge, self::CHARGES, true)) {
            throw FiscalException::unknownCharge($charge);
        }

        $own = is_array($this->raw['charges'][$charge] ?? null) ? $this->raw['charges'][$charge] : [];
        $default = (array) config("fiscal.charges.{$charge}", []);
        $pick = fn (string $field) => filled($own[$field] ?? null) ? (string) $own[$field] : (filled($default[$field] ?? null) ? (string) $default[$field] : null);

        return [
            'name' => $pick('name') ?? $charge,
            'mxik' => $pick('mxik'),
            'package_code' => $pick('package_code'),
            'unit' => $pick('unit') ?? 'xizmat',
        ];
    }

    /**
     * @return array{latency_ms: int, fail_attempts: int, failure_rate: int, reject: bool}
     */
    public function sandbox(): array
    {
        $own = is_array($this->raw['sandbox'] ?? null) ? $this->raw['sandbox'] : [];

        return [
            'latency_ms' => max(0, min((int) config('fiscal.sandbox.max_latency_ms', 3000), (int) ($own['latency_ms'] ?? 0))),
            'fail_attempts' => max(0, (int) ($own['fail_attempts'] ?? 0)),
            'failure_rate' => max(0, min(100, (int) ($own['failure_rate'] ?? 0))),
            'reject' => (bool) ($own['reject'] ?? false),
        ];
    }

    /**
     * Everything with its defaults, as the settings endpoints return it.
     *
     * @return array{enabled: bool, driver: string, active_driver: string, charges: array<string, array{name: string, mxik: string|null, package_code: string|null, unit: string}>, sandbox: array{latency_ms: int, fail_attempts: int, failure_rate: int, reject: bool}, deposit_refund_receipt: bool|null, retained_deposit_receipt: bool|null}
     */
    public function toArray(): array
    {
        $charges = [];
        foreach (self::CHARGES as $charge) {
            $charges[$charge] = $this->charge($charge);
        }

        return [
            'enabled' => $this->enabled(),
            'driver' => $this->configuredDriver(),
            'active_driver' => $this->driver(),
            'charges' => $charges,
            'sandbox' => $this->sandbox(),
            'deposit_refund_receipt' => $this->flag('deposit_refund_receipt'),
            'retained_deposit_receipt' => $this->flag('retained_deposit_receipt'),
        ];
    }

    /**
     * One of FLAGS as the store set it; null when it has not.
     */
    public function flag(string $flag): ?bool
    {
        $value = $this->raw[$flag] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * Validates the fiscal part of a settings request (`tin`, `tax_regime`, `fiscal_settings`; each optional, left
     * out = kept) and returns the store attributes to save. Turning fiscal mode on needs the TIN and the tax regime.
     *
     * @param  array<string, mixed>  $input
     * @return array{tin?: string|null, tax_regime?: string|null, fiscal_settings?: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public static function validatedUpdate(array $input, Store $store): array
    {
        $validator = Validator::make($input, [
            'tin' => ['sometimes', 'nullable', 'string', 'regex:' . self::TIN_PATTERN],
            'tax_regime' => ['sometimes', 'nullable', 'string', Rule::in(TaxRegime::values())],
            'fiscal_settings' => ['sometimes', 'array'],
            'fiscal_settings.enabled' => ['sometimes', 'boolean'],
            'fiscal_settings.driver' => ['sometimes', 'nullable', 'string', Rule::in(array_keys((array) config('fiscal.drivers', [])))],
            'fiscal_settings.charges' => ['sometimes', 'array'],
            'fiscal_settings.charges.*' => ['array'],
            'fiscal_settings.charges.*.name' => ['sometimes', 'nullable', 'string', 'max:128'],
            'fiscal_settings.charges.*.mxik' => ['sometimes', 'nullable', 'string', 'regex:' . self::MXIK_PATTERN],
            'fiscal_settings.charges.*.package_code' => ['sometimes', 'nullable', 'string', 'regex:' . self::PACKAGE_CODE_PATTERN],
            'fiscal_settings.charges.*.unit' => ['sometimes', 'nullable', 'string', 'max:32'],
            'fiscal_settings.sandbox' => ['sometimes', 'array'],
            'fiscal_settings.sandbox.latency_ms' => ['sometimes', 'integer', 'between:0,' . (int) config('fiscal.sandbox.max_latency_ms', 3000)],
            'fiscal_settings.sandbox.fail_attempts' => ['sometimes', 'integer', 'between:0,50'],
            'fiscal_settings.sandbox.failure_rate' => ['sometimes', 'integer', 'between:0,100'],
            'fiscal_settings.sandbox.reject' => ['sometimes', 'boolean'],
            'fiscal_settings.deposit_refund_receipt' => ['sometimes', 'nullable', 'boolean'],
            'fiscal_settings.retained_deposit_receipt' => ['sometimes', 'nullable', 'boolean'],
        ], \App\Support\UzValidation::messages([
            'tin.regex' => "STIR 9 ta raqamdan iborat bo'lishi kerak.",
            'tax_regime.in' => "Soliq rejimi noto'g'ri: vat, vat_simplified yoki turnover.",
            'fiscal_settings.driver.in' => "Fiskal drayver noto'g'ri.",
            'fiscal_settings.charges.*.mxik.regex' => "MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.",
            'fiscal_settings.charges.*.package_code.regex' => "Qadoq kodi faqat raqamlardan iborat bo'lishi kerak (ko'pi bilan 20 ta).",
        ]), [
            'tin' => 'STIR',
            'tax_regime' => 'Soliq rejimi',
            'fiscal_settings.enabled' => 'Fiskal rejim',
            'fiscal_settings.charges.*.name' => 'Xizmat nomi',
            'fiscal_settings.charges.*.unit' => "O'lchov birligi",
            'fiscal_settings.sandbox.latency_ms' => 'Kechikish (ms)',
            'fiscal_settings.sandbox.fail_attempts' => 'Xato urinishlar soni',
            'fiscal_settings.sandbox.failure_rate' => 'Xato ehtimoli (%)',
            'fiscal_settings.deposit_refund_receipt' => 'Avans qaytarilganda chek',
            'fiscal_settings.retained_deposit_receipt' => 'Avans ushlab qolinganda chek',
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($input, $store) {
            foreach (array_keys((array) ($input['fiscal_settings']['charges'] ?? [])) as $charge) {
                if (!in_array($charge, self::CHARGES, true)) {
                    $validator->errors()->add("fiscal_settings.charges.{$charge}", "Noma'lum xizmat turi: {$charge}.");
                }
            }

            $enabling = (bool) ($input['fiscal_settings']['enabled'] ?? self::of($store)->enabled());
            if ($enabling) {
                $tin = array_key_exists('tin', $input) ? $input['tin'] : $store->tin;
                $regime = array_key_exists('tax_regime', $input) ? $input['tax_regime'] : $store->tax_regime;
                if (blank($tin)) {
                    $validator->errors()->add('tin', "Fiskal rejimni yoqish uchun do'kon STIRini kiriting.");
                }
                if (blank($regime)) {
                    $validator->errors()->add('tax_regime', 'Fiskal rejimni yoqish uchun soliq rejimini tanlang.');
                }
            }
        });

        $data = $validator->validate();

        $update = [];
        foreach (['tin', 'tax_regime'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = filled($data[$field]) ? (string) $data[$field] : null;
            }
        }
        if (array_key_exists('fiscal_settings', $data)) {
            $update['fiscal_settings'] = self::merged(is_array($store->fiscal_settings) ? $store->fiscal_settings : [], (array) $data['fiscal_settings']);
        }

        return $update;
    }

    /**
     * The stored settings with the validated input applied: given keys replace, charges and sandbox merge field by
     * field (a null field goes back to the default).
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function merged(array $stored, array $input): array
    {
        if (array_key_exists('enabled', $input)) {
            $stored['enabled'] = (bool) $input['enabled'];
        }
        if (array_key_exists('driver', $input)) {
            $stored['driver'] = $input['driver'];
        }

        foreach ((array) ($input['charges'] ?? []) as $charge => $fields) {
            $current = is_array($stored['charges'][$charge] ?? null) ? $stored['charges'][$charge] : [];
            foreach (['name', 'mxik', 'package_code', 'unit'] as $field) {
                if (array_key_exists($field, (array) $fields)) {
                    $current[$field] = filled($fields[$field]) ? (string) $fields[$field] : null;
                }
            }
            $stored['charges'][$charge] = array_filter($current, fn ($value) => $value !== null);
        }

        foreach (self::FLAGS as $flag) {
            if (array_key_exists($flag, $input)) {
                if ($input[$flag] === null) {
                    unset($stored[$flag]);
                } else {
                    $stored[$flag] = (bool) $input[$flag];
                }
            }
        }

        $sandbox = (array) ($input['sandbox'] ?? []);
        foreach (['latency_ms', 'fail_attempts', 'failure_rate', 'reject'] as $field) {
            if (array_key_exists($field, $sandbox)) {
                $stored['sandbox'][$field] = $field === 'reject' ? (bool) $sandbox[$field] : (int) $sandbox[$field];
            }
        }

        return $stored;
    }
}
