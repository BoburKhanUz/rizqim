<?php

namespace App\Domain\Pos\StaffPay;

use App\Domain\Pos\Booking\BookingEventType;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * P6-23: the components of a wage rule as stored (staff_pay_rules.components) and as the API takes them. Each is
 * {type, ...its fields} (PayComponentType):
 *
 *   shift_rate              {amount}                         so'm per shift
 *   hourly_rate             {amount}                         so'm per hour
 *   sales_percent           {percent}                        % of own sales
 *   service_charge_percent  {percent}                        % of the service charge collected
 *   guarantee               {amount, per, mode, percent, base} per: shift | period (GuaranteePer), mode: max | plus
 *                                                             (GuaranteeMode), base: sales | service_charge (PercentBase)
 *   event_rate              {amount, event_types}            so'm per event; event_types: the booking kinds that count
 *                                                             (BookingEventType values; empty or absent: every kind but
 *                                                             "other")
 *   tips                    {}                               the person's tips of the period
 *
 * Amounts are so'm (decimal strings, 0–100 000 000), percents 0–100 with two decimals at most. A type appears once.
 */
final class PayComponents
{
    public const MAX = 10;

    public const MAX_AMOUNT = '100000000';

    /** One component as the API shows it (fields a type does not use are absent). */
    public const SHAPE = 'array{type: string, label: string, amount?: string, percent?: string, per?: string, mode?: string, base?: string, event_types?: list<string>}';

    /**
     * The components, checked and normalized (amounts and percents as decimal strings).
     *
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public static function normalize(mixed $input, string $field = 'components'): array
    {
        if (!is_array($input) || !array_is_list($input)) {
            throw ValidationException::withMessages([$field => "Qoida qismlari ro'yxat bo'lishi kerak."]);
        }
        if (count($input) > self::MAX) {
            throw ValidationException::withMessages([$field => 'Qoidada ko\'pi bilan ' . self::MAX . ' ta qism bo\'ladi.']);
        }

        $seen = [];
        $out = [];
        foreach ($input as $index => $component) {
            $key = "{$field}.{$index}";
            if (!is_array($component)) {
                throw ValidationException::withMessages([$key => "Qism noto'g'ri."]);
            }
            $type = is_string($component['type'] ?? null) ? PayComponentType::tryFrom($component['type']) : null;
            if ($type === null) {
                throw ValidationException::withMessages(["{$key}.type" => "Qism turi noto'g'ri: " . implode(', ', PayComponentType::values()) . '.']);
            }
            if (isset($seen[$type->value])) {
                throw ValidationException::withMessages(["{$key}.type" => "\"{$type->label()}\" qoidada bir marta bo'ladi."]);
            }
            $seen[$type->value] = true;

            $out[] = match ($type) {
                PayComponentType::SHIFT_RATE, PayComponentType::HOURLY_RATE => ['type' => $type->value, 'amount' => self::amount($component, $key)],
                PayComponentType::SALES_PERCENT, PayComponentType::SERVICE_CHARGE_PERCENT => ['type' => $type->value, 'percent' => self::percent($component, $key)],
                PayComponentType::GUARANTEE => [
                    'type' => $type->value,
                    'amount' => self::amount($component, $key),
                    'per' => self::choice($component, 'per', GuaranteePer::class, GuaranteePer::SHIFT, $key)->value,
                    'mode' => self::choice($component, 'mode', GuaranteeMode::class, GuaranteeMode::MAX, $key)->value,
                    'percent' => self::percent($component, $key, required: false),
                    'base' => self::choice($component, 'base', PercentBase::class, PercentBase::SALES, $key)->value,
                ],
                PayComponentType::EVENT_RATE => ['type' => $type->value, 'amount' => self::amount($component, $key), 'event_types' => self::eventTypes($component, $key)],
                PayComponentType::TIPS => ['type' => $type->value],
            };
        }

        return $out;
    }

    /**
     * The stored components as the API shows them (with their labels).
     *
     * @param  list<array<string, mixed>>|null  $components
     * @return list<array<string, mixed>>
     */
    public static function present(?array $components): array
    {
        return array_map(function (array $component) {
            $type = PayComponentType::tryFrom((string) ($component['type'] ?? ''));

            return ['type' => (string) ($component['type'] ?? ''), 'label' => $type?->label() ?? (string) ($component['type'] ?? '')] + $component;
        }, $components ?? []);
    }

    /**
     * The booking kinds an event_rate counts.
     *
     * @param  array<string, mixed>  $component
     * @return list<string>
     */
    public static function countedEventTypes(array $component): array
    {
        $types = is_array($component['event_types'] ?? null) ? array_values($component['event_types']) : [];

        return $types !== [] ? $types : array_values(array_diff(BookingEventType::values(), [BookingEventType::OTHER->value]));
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private static function amount(array $component, string $key): string
    {
        $value = $component['amount'] ?? null;
        if (!self::isDecimal($value) || Money::of((string) $value)->isGreaterThan(self::MAX_AMOUNT)) {
            throw ValidationException::withMessages(["{$key}.amount" => "Summa 0 dan 100 000 000 so'mgacha bo'lishi kerak (ko'pi bilan 2 xona kasr)."]);
        }

        return Money::of((string) $value)->toString();
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private static function percent(array $component, string $key, bool $required = true): string
    {
        $value = $component['percent'] ?? null;
        if ($value === null && !$required) {
            return '0.00';
        }
        if (!self::isDecimal($value) || Money::of((string) $value)->isGreaterThan(100)) {
            throw ValidationException::withMessages(["{$key}.percent" => "Foiz 0 dan 100 gacha bo'lishi kerak (ko'pi bilan 2 xona kasr)."]);
        }

        return Money::of((string) $value)->toString();
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  array<string, mixed>  $component
     * @param  class-string<T>  $enum
     * @param  T  $default
     * @return T
     */
    private static function choice(array $component, string $field, string $enum, \BackedEnum $default, string $key): \BackedEnum
    {
        $value = $component[$field] ?? null;
        if ($value === null) {
            return $default;
        }
        $choice = is_string($value) ? $enum::tryFrom($value) : null;
        if ($choice === null) {
            throw ValidationException::withMessages(["{$key}.{$field}" => "Qiymat noto'g'ri: " . implode(', ', array_map(fn (\BackedEnum $case) => (string) $case->value, $enum::cases())) . '.']);
        }

        return $choice;
    }

    /**
     * @param  array<string, mixed>  $component
     * @return list<string>
     */
    private static function eventTypes(array $component, string $key): array
    {
        $value = $component['event_types'] ?? [];
        if (!is_array($value) || array_diff(array_map(fn ($type) => is_string($type) ? $type : '', $value), BookingEventType::values()) !== []) {
            throw ValidationException::withMessages(["{$key}.event_types" => "Tadbir turlari: " . implode(', ', BookingEventType::values()) . '.']);
        }

        return array_values(array_unique($value));
    }

    private static function isDecimal(mixed $value): bool
    {
        return (is_int($value) && $value >= 0) || (is_string($value) && preg_match('/^\d{1,12}(\.\d{1,2})?$/', $value) === 1)
            || (is_float($value) && $value >= 0 && preg_match('/^\d{1,12}(\.\d{1,2})?$/', (string) $value) === 1);
    }
}
