<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Billing\PosActionException;
use App\Models\BanquetOpsSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * P6-54: the store's banquet operations settings, with the defaults (one banquet_ops_settings row per store, made on
 * the first save):
 *
 *  - reserve_percent: the production sheet's reserve on the billed guests (0–50 %);
 *  - osh: the osh calculator (OshCalculator): persons per kg of osh (5–6), the ingredients per kg (rice, meat, carrot,
 *    oil…, editable), the kazan sizes and the menu products that are osh;
 *  - portion_weights: grams of one portion per menu product, so the sheet shows kg for portion lines;
 *  - mxik: the final sale receipt's codes for the package, the hall rent and the extras (accountant to confirm);
 *  - contract_template: the contract (shartnoma) text with placeholders (BanquetContracts::PLACEHOLDERS).
 *
 * Every change is audited (banquet.ops_settings_updated) with the optimistic `version`.
 */
final class BanquetOpsSettings
{
    public const SHAPE = 'array{reserve_percent: string, osh: ' . OshCalculator::SETTINGS . ', portion_weights: list<array{product_id: int, grams: int}>, mxik: array{package: array{mxik: string|null, package_code: string|null}, hall: array{mxik: string|null, package_code: string|null}, extra: array{mxik: string|null, package_code: string|null}}, contract_template: string, contract_template_is_default: bool, contract_placeholders: list<string>, version: int}';

    public const MXIK_PARTS = ['package', 'hall', 'extra'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function row(int $storeId): ?BanquetOpsSetting
    {
        return BanquetOpsSetting::where('store_id', $storeId)->first();
    }

    /**
     * @return array<string, mixed> SHAPE
     */
    public function of(int $storeId): array
    {
        $row = $this->row($storeId);
        $template = $row?->contract_template;

        return [
            'reserve_percent' => BigDecimal::of((string) ($row->reserve_percent ?? '0'))->toScale(2)->__toString(),
            'osh' => OshCalculator::normalize($row?->osh),
            'portion_weights' => (array_map(fn (array $weight) => ['product_id' => (int) $weight['product_id'], 'grams' => (int) $weight['grams']], $row->portion_weights ?? [])),
            'mxik' => [
                'package' => ['mxik' => $row?->package_mxik, 'package_code' => $row?->package_package_code],
                'hall' => ['mxik' => $row?->hall_mxik, 'package_code' => $row?->hall_package_code],
                'extra' => ['mxik' => $row?->extra_mxik, 'package_code' => $row?->extra_package_code],
            ],
            'contract_template' => filled($template) ? (string) $template : BanquetContracts::DEFAULT_TEMPLATE,
            'contract_template_is_default' => blank($template),
            'contract_placeholders' => BanquetContracts::PLACEHOLDERS,
            'version' => (int) ($row->version ?? 0),
        ];
    }

    /**
     * Grams of one portion by product id.
     *
     * @return array<int, int>
     */
    public function portionWeights(int $storeId): array
    {
        $weights = [];
        foreach ($this->of($storeId)['portion_weights'] as $weight) {
            $weights[$weight['product_id']] = $weight['grams'];
        }

        return $weights;
    }

    /**
     * Saves the fields given (validated by the controller), under the row lock and the optimistic version.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> SHAPE
     */
    public function update(int $storeId, array $data, ?int $version, User $actor): array
    {
        DB::transaction(function () use ($storeId, $data, $version, $actor) {
            $row = BanquetOpsSetting::where('store_id', $storeId)->lockForUpdate()->first();
            $current = (int) ($row->version ?? 0);
            if ($version !== null && $version !== $current) {
                throw PosActionException::conflict('version_conflict', "Sozlamalar boshqa joyda o'zgartirilgan: sahifani yangilang.", ['version' => $current]);
            }
            $before = $this->of($storeId);
            $row ??= new BanquetOpsSetting(['store_id' => $storeId, 'version' => 0]);

            if (array_key_exists('reserve_percent', $data)) {
                $row->reserve_percent = BigDecimal::of((string) $data['reserve_percent'])->toScale(2)->__toString();
            }
            if (array_key_exists('osh', $data)) {
                $osh = OshCalculator::normalize(is_array($data['osh']) ? $data['osh'] : []);
                $osh['product_ids'] = array_values(array_unique(array_map('intval', Product::where('store_id', $storeId)->whereIn('id', $osh['product_ids'])->pluck('id')->all())));
                $row->osh = $osh;
            }
            if (array_key_exists('portion_weights', $data)) {
                $known = Product::where('store_id', $storeId)->whereIn('id', array_map(fn ($weight) => (int) ($weight['product_id'] ?? 0), (array) $data['portion_weights']))->pluck('id')->all();
                $weights = [];
                foreach ((array) $data['portion_weights'] as $weight) {
                    $id = (int) ($weight['product_id'] ?? 0);
                    if (in_array($id, $known, false)) {
                        $weights[$id] = ['product_id' => $id, 'grams' => (int) $weight['grams']];
                    }
                }
                $row->portion_weights = array_values($weights);
            }
            foreach (self::MXIK_PARTS as $part) {
                if (array_key_exists("{$part}_mxik", $data)) {
                    $row->setAttribute("{$part}_mxik", filled($data["{$part}_mxik"]) ? (string) $data["{$part}_mxik"] : null);
                }
                if (array_key_exists("{$part}_package_code", $data)) {
                    $row->setAttribute("{$part}_package_code", filled($data["{$part}_package_code"]) ? (string) $data["{$part}_package_code"] : null);
                }
            }
            if (array_key_exists('contract_template', $data)) {
                $text = trim((string) ($data['contract_template'] ?? ''));
                $row->contract_template = $text === '' || $text === BanquetContracts::DEFAULT_TEMPLATE ? null : $text;
            }
            $row->version = $current + 1;
            $row->save();

            $after = $this->of($storeId);
            $auditAfter = self::auditable($after);
            if ($before['contract_template'] !== $after['contract_template']) {
                $auditAfter['contract_template']['changed_lines'] = self::changedLines((string) $before['contract_template'], (string) $after['contract_template']);
            }
            $this->audit->record('banquet.ops_settings_updated', $row, self::auditable($before), $auditAfter, [], ['actor' => $actor, 'store_id' => $storeId]);
        });

        return $this->of($storeId);
    }

    /**
     * The settings as the audit log keeps them. The contract template (it can be long) as a diff summary: its length,
     * line count and sha1, and on the new side the first changed line numbers (1-based, at most 20: `changed_lines`).
     * An osh ingredient's `key` is logged as `slug` (the redactor drops any key named like a secret).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function auditable(array $settings): array
    {
        unset($settings['contract_placeholders'], $settings['contract_template_is_default']);
        $template = (string) $settings['contract_template'];
        $settings['contract_template'] = ['length' => mb_strlen($template), 'lines' => substr_count($template, "\n") + 1, 'sha1' => sha1($template)];
        if (is_array($settings['osh'] ?? null) && is_array($settings['osh']['ingredients'] ?? null)) {
            $settings['osh']['ingredients'] = array_map(
                fn (array $ingredient) => ['slug' => $ingredient['key'] ?? null] + array_diff_key($ingredient, ['key' => true]),
                $settings['osh']['ingredients'],
            );
        }

        return $settings;
    }

    /**
     * The first line numbers (1-based) where two texts differ, at most 20.
     *
     * @return list<int>
     */
    private static function changedLines(string $before, string $after): array
    {
        $old = explode("\n", $before);
        $new = explode("\n", $after);
        $changed = [];
        for ($i = 0, $n = max(count($old), count($new)); $i < $n && count($changed) < 20; $i++) {
            if (($old[$i] ?? null) !== ($new[$i] ?? null)) {
                $changed[] = $i + 1;
            }
        }

        return $changed;
    }

    /**
     * The reserve as a decimal fraction multiplier (1 + reserve / 100).
     */
    public static function reserveFactor(string $percent): BigDecimal
    {
        return BigDecimal::one()->plus(BigDecimal::of($percent)->dividedBy(100, 4, \Brick\Math\RoundingMode::HalfUp));
    }

    public static function money(mixed $amount): string
    {
        return Money::of((string) $amount)->toString();
    }
}
