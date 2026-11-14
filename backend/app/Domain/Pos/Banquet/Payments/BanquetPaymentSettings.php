<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Models\BanquetPaymentSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P6-53: the store's banquet payment rules with their defaults (a store that saved nothing gets them):
 *
 *  - schedule template: the rows a new schedule is planned from — `percent` of the event total (whole so'm, the last
 *    row takes the rest: `percent` null) due `days_before` the event date (null: at booking, the deposit). Default:
 *    30 % deposit at booking, 40 % 14 days before, the rest on the day;
 *  - cancellation rules: days before the event → retained % of what was paid; the band with the largest `min_days`
 *    not above the days left applies (a date already past: the smallest band). Default: ≥ 30 days 0 %, 14–29 days 30 %,
 *    < 14 days 100 % (UNVERIFIED: the owner sets his own; the contract states them);
 *  - reminders: the owner's chat hears N days before each due date (default 3 and 1) and, once overdue, on the first
 *    day after and then weekly.
 *
 * Every change is audited (banquet.payment_settings_updated).
 */
final class BanquetPaymentSettings
{
    public const DEFAULT_TEMPLATE = [
        ['kind' => 'deposit', 'percent' => '30', 'days_before' => null],
        ['kind' => 'interim', 'percent' => '40', 'days_before' => 14],
        ['kind' => 'final', 'percent' => null, 'days_before' => 0],
    ];

    public const DEFAULT_CANCELLATION = [
        ['min_days' => 30, 'retained_percent' => 0],
        ['min_days' => 14, 'retained_percent' => 30],
        ['min_days' => 0, 'retained_percent' => 100],
    ];

    public const DEFAULT_REMINDER_DAYS = [3, 1];

    public const SHAPE = 'array{schedule_template: list<array{kind: string, percent: string|null, days_before: int|null}>, cancellation_rules: list<array{min_days: int, retained_percent: string}>, reminder_days: list<int>, overdue_reminders: bool, version: int}';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{schedule_template: list<array{kind: string, percent: string|null, days_before: int|null}>, cancellation_rules: list<array{min_days: int, retained_percent: string}>, reminder_days: list<int>, overdue_reminders: bool, version: int}
     */
    public function of(int $storeId): array
    {
        $row = BanquetPaymentSetting::where('store_id', $storeId)->first();

        return [
            'schedule_template' => self::template($row?->schedule_template ?: self::DEFAULT_TEMPLATE),
            'cancellation_rules' => self::rules($row?->cancellation_rules ?: self::DEFAULT_CANCELLATION),
            'reminder_days' => array_map('intval', $row->reminder_days ?? self::DEFAULT_REMINDER_DAYS),
            'overdue_reminders' => $row->overdue_reminders ?? true,
            'version' => $row->version ?? 0,
        ];
    }

    /**
     * Saves what the owner sent (already validated field by field in the action); checks the rules across rows.
     *
     * @param  array{schedule_template?: list<array<string, mixed>>, cancellation_rules?: list<array<string, mixed>>, reminder_days?: list<int>, overdue_reminders?: bool, version?: int|null}  $data
     * @return array<string, mixed>
     */
    public function update(Store $store, array $data, User $actor): array
    {
        if (isset($data['schedule_template'])) {
            $template = self::template($data['schedule_template']);
            $kinds = array_column($template, 'kind');
            $last = $template[count($template) - 1];
            if ($last['kind'] !== BanquetPaymentKind::FINAL->value || $last['percent'] !== null) {
                throw ValidationException::withMessages(['schedule_template' => "Oxirgi qator yakuniy to'lov bo'lsin va qolgan summani olsin (foiz bo'sh)."]);
            }
            if (count(array_keys($kinds, BanquetPaymentKind::FINAL->value, true)) !== 1 || count(array_keys($kinds, BanquetPaymentKind::DEPOSIT->value, true)) > 1) {
                throw ValidationException::withMessages(['schedule_template' => "Jadvalda bitta yakuniy to'lov va ko'pi bilan bitta zaklad bo'ladi."]);
            }
            $sum = 0.0;
            foreach ($template as $index => $row) {
                if ($row['kind'] !== BanquetPaymentKind::FINAL->value && $row['percent'] === null) {
                    throw ValidationException::withMessages(["schedule_template.{$index}.percent" => "Foizni kiriting."]);
                }
                if ($row['days_before'] === null && $row['kind'] !== BanquetPaymentKind::DEPOSIT->value) {
                    throw ValidationException::withMessages(["schedule_template.{$index}.days_before" => "Muddatni kiriting: tadbirdan necha kun oldin."]);
                }
                $sum += (float) ($row['percent'] ?? 0);
            }
            if ($sum > 100) {
                throw ValidationException::withMessages(['schedule_template' => "Foizlar yig'indisi 100 dan oshmasin."]);
            }
        }
        if (isset($data['cancellation_rules'])) {
            $rules = self::rules($data['cancellation_rules']);
            $days = array_column($rules, 'min_days');
            if (count(array_unique($days)) !== count($days)) {
                throw ValidationException::withMessages(['cancellation_rules' => 'Har bir kun chegarasi bir marta yozilsin.']);
            }
            if (!in_array(0, $days, true)) {
                throw ValidationException::withMessages(['cancellation_rules' => "0 kunlik qator bo'lsin: tadbirga oz qolganda nima ushlab qolinadi."]);
            }
        }

        return DB::transaction(function () use ($store, $data, $actor) {
            $row = BanquetPaymentSetting::where('store_id', $store->id)->lockForUpdate()->first();
            if (array_key_exists('version', $data) && $data['version'] !== null && (int) $data['version'] !== (int) ($row->version ?? 0)) {
                throw ValidationException::withMessages(['version' => "Sozlamalar boshqa joyda o'zgartirilgan: sahifani yangilang."]);
            }
            $before = $this->of((int) $store->id);
            $row ??= new BanquetPaymentSetting(['store_id' => $store->id, 'version' => 0]);
            if (isset($data['schedule_template'])) {
                $row->schedule_template = self::template($data['schedule_template']);
            }
            if (isset($data['cancellation_rules'])) {
                $row->cancellation_rules = self::rules($data['cancellation_rules']);
            }
            if (isset($data['reminder_days'])) {
                $days = array_values(array_unique(array_map('intval', $data['reminder_days'])));
                rsort($days);
                $row->reminder_days = $days;
            }
            if (array_key_exists('overdue_reminders', $data)) {
                $row->overdue_reminders = (bool) $data['overdue_reminders'];
            }
            $row->version = (int) $row->version + 1;
            $row->save();

            $after = $this->of((int) $store->id);
            $this->audit->record('banquet.payment_settings_updated', $store, array_diff_key($before, ['version' => 1]), array_diff_key($after, ['version' => 1]), [], ['actor' => $actor]);

            return $after;
        });
    }

    /**
     * The retained % of what was paid for a cancellation `days` before the event.
     *
     * @param  list<array{min_days: int, retained_percent: string}>  $rules
     */
    public static function retainedPercent(array $rules, int $days): string
    {
        usort($rules, fn ($a, $b) => $b['min_days'] <=> $a['min_days']);
        foreach ($rules as $rule) {
            if ($days >= $rule['min_days']) {
                return $rule['retained_percent'];
            }
        }

        return $rules !== [] ? $rules[count($rules) - 1]['retained_percent'] : '0';
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{kind: string, percent: string|null, days_before: int|null}>
     */
    private static function template(array $rows): array
    {
        return array_values(array_map(fn ($row) => [
            'kind' => (string) ($row['kind'] ?? BanquetPaymentKind::INTERIM->value),
            'percent' => isset($row['percent']) && $row['percent'] !== '' ? self::number($row['percent']) : null,
            'days_before' => isset($row['days_before']) && $row['days_before'] !== '' ? (int) $row['days_before'] : null,
        ], $rows));
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{min_days: int, retained_percent: string}>
     */
    private static function rules(array $rows): array
    {
        $rules = array_values(array_map(fn ($row) => [
            'min_days' => (int) ($row['min_days'] ?? 0),
            'retained_percent' => self::number($row['retained_percent'] ?? 0),
        ], $rows));
        usort($rules, fn ($a, $b) => $b['min_days'] <=> $a['min_days']);

        return $rules;
    }

    private static function number(mixed $value): string
    {
        $number = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $number === '' || $number === '-0' ? '0' : $number;
    }
}
