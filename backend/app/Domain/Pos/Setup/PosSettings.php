<?php

namespace App\Domain\Pos\Setup;

use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * The branch's POS settings (branches.pos_settings, P3-13, POS_SPEC 10.3): the keys a branch may set, their defaults
 * and how a stored value reads. Unknown keys are never kept; a stored value that no longer fits (a hand edit, an older
 * shape) reads as the default instead of reaching a terminal.
 *
 * - `mode`: counter (quick till), table (waiters and a table map) or hybrid.
 * - `printing_device_id`: the public id (uuid) of the "chop etuvchi" till, which prints the tickets of online orders.
 * - `screen_lock_minutes`: idle minutes until the terminal locks its screen (0: never).
 * - `queue_numbers`: a queue number for every counter order.
 * - `cashier_discount_max_percent` (P3-37): the discount a cashier (pos.take_payment without orders.discount) may give
 *   alone, "0.00"-"100.00"; above it, or when null or 0, a manager's PIN.
 * - `alerts`: thresholds that alert the owner/manager; null switches one off. `open_room_hours` (a room open for
 *   this long), `void_amount` (a voided line worth at least this), `discount_percent` (a discount of at least this
 *   percent), `unaccepted_online_minutes` (an online order nobody accepted for this long), `cash_out_amount` (P3-37:
 *   one cash movement out of the drawer — cash out, wage, purchase — of at least this).
 * - `holidays`: dates (YYYY-MM-DD) the branch's holiday tariff applies on.
 * - `booking_slot_minutes`: how long a booking holds its room when it has no end time (30-1440); null: the default of
 *   config/pos_bookings.php (read by App\Domain\Pos\Booking\PosSettingsReader).
 * - `booking_confirmation` (P3-38): the confirmation the guest is sent (App\Domain\Pos\Booking\BookingConfirmationService).
 *   `auto_send` (default true): sent by itself when a booking becomes confirmed; `sms` (default false, an SMS costs
 *   money): SMS may be used when the guest has no Telegram; `cancel_policy`: the branch's cancellation rule shown in it
 *   (up to 300 characters, null: none). Merged key by key like `alerts`.
 */
final class PosSettings
{
    public const MODES = ['counter', 'table', 'hybrid'];

    /** Keys written by the API, in the order they are shown. */
    public const KEYS = ['mode', 'printing_device_id', 'screen_lock_minutes', 'queue_numbers', 'cashier_discount_max_percent', 'alerts', 'holidays', 'booking_slot_minutes', 'booking_confirmation'];

    public const ALERT_KEYS = ['open_room_hours', 'void_amount', 'discount_percent', 'unaccepted_online_minutes', 'cash_out_amount'];

    /** Alert thresholds that are money or a percent (decimal strings); the others are whole numbers. */
    public const DECIMAL_ALERT_KEYS = ['void_amount', 'discount_percent', 'cash_out_amount'];

    public const BOOKING_CONFIRMATION_KEYS = ['auto_send', 'sms', 'cancel_policy'];

    public const CANCEL_POLICY_MAX = 300;

    public const DEFAULT_BUSINESS_DAY_START = '05:00';

    public const MAX_HOLIDAYS = 400;

    /**
     * @return array{mode: string, printing_device_id: string|null, screen_lock_minutes: int, queue_numbers: bool, cashier_discount_max_percent: string|null, alerts: array{open_room_hours: int|null, void_amount: string|null, discount_percent: string|null, unaccepted_online_minutes: int|null, cash_out_amount: string|null}, holidays: list<string>, booking_slot_minutes: int|null, booking_confirmation: array{auto_send: bool, sms: bool, cancel_policy: string|null}}
     */
    public static function defaults(): array
    {
        return [
            'mode' => 'hybrid',
            'printing_device_id' => null,
            'screen_lock_minutes' => 5,
            'queue_numbers' => false,
            'cashier_discount_max_percent' => '10.00',
            'alerts' => [
                'open_room_hours' => 6,
                'void_amount' => '200000.00',
                'discount_percent' => '10.00',
                'unaccepted_online_minutes' => 3,
                'cash_out_amount' => '500000.00',
            ],
            'holidays' => [],
            'booking_slot_minutes' => null,
            'booking_confirmation' => [
                'auto_send' => true,
                'sms' => false,
                'cancel_policy' => null,
            ],
        ];
    }

    /**
     * The settings as a terminal and the panel read them: every key present, stored values over the defaults.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array{mode: string, printing_device_id: string|null, screen_lock_minutes: int, queue_numbers: bool, cashier_discount_max_percent: string|null, alerts: array{open_room_hours: int|null, void_amount: string|null, discount_percent: string|null, unaccepted_online_minutes: int|null, cash_out_amount: string|null}, holidays: list<string>, booking_slot_minutes: int|null, booking_confirmation: array{auto_send: bool, sms: bool, cancel_policy: string|null}}
     */
    public static function resolve(?array $stored): array
    {
        $settings = self::defaults();
        $stored ??= [];

        if (isset($stored['mode']) && is_string($stored['mode']) && in_array($stored['mode'], self::MODES, true)) {
            $settings['mode'] = $stored['mode'];
        }
        if (array_key_exists('printing_device_id', $stored)) {
            $settings['printing_device_id'] = is_string($stored['printing_device_id']) && $stored['printing_device_id'] !== '' ? $stored['printing_device_id'] : null;
        }
        if (isset($stored['screen_lock_minutes']) && is_int($stored['screen_lock_minutes']) && $stored['screen_lock_minutes'] >= 0 && $stored['screen_lock_minutes'] <= 240) {
            $settings['screen_lock_minutes'] = $stored['screen_lock_minutes'];
        }
        if (isset($stored['queue_numbers']) && is_bool($stored['queue_numbers'])) {
            $settings['queue_numbers'] = $stored['queue_numbers'];
        }
        if (array_key_exists('cashier_discount_max_percent', $stored)) {
            $value = $stored['cashier_discount_max_percent'];
            if ($value === null) {
                $settings['cashier_discount_max_percent'] = null;
            } elseif (is_numeric($value) && (float) $value >= 0 && (float) $value <= 100) {
                $settings['cashier_discount_max_percent'] = Money::of($value)->toString();
            }
        }

        $alerts = is_array($stored['alerts'] ?? null) ? $stored['alerts'] : [];
        foreach (self::ALERT_KEYS as $key) {
            if (!array_key_exists($key, $alerts)) {
                continue; // not set: the default
            }
            $value = $alerts[$key];
            $settings['alerts'][$key] = match (true) {
                $value === null => null, // switched off
                !in_array($key, self::DECIMAL_ALERT_KEYS, true) => is_int($value) && $value > 0 ? $value : $settings['alerts'][$key],
                is_numeric($value) && (float) $value >= 0 => Money::of($value)->toString(),
                default => $settings['alerts'][$key],
            };
        }

        if (isset($stored['booking_slot_minutes']) && is_int($stored['booking_slot_minutes']) && $stored['booking_slot_minutes'] >= 30 && $stored['booking_slot_minutes'] <= 1440) {
            $settings['booking_slot_minutes'] = $stored['booking_slot_minutes'];
        }

        $holidays = is_array($stored['holidays'] ?? null) ? $stored['holidays'] : [];
        $settings['holidays'] = self::normalizeHolidays(array_filter($holidays, 'is_string'));

        $confirmation = is_array($stored['booking_confirmation'] ?? null) ? $stored['booking_confirmation'] : [];
        foreach (['auto_send', 'sms'] as $key) {
            if (isset($confirmation[$key]) && is_bool($confirmation[$key])) {
                $settings['booking_confirmation'][$key] = $confirmation[$key];
            }
        }
        if (array_key_exists('cancel_policy', $confirmation)) {
            $settings['booking_confirmation']['cancel_policy'] = self::cancelPolicy($confirmation['cancel_policy']);
        }

        return $settings;
    }

    /**
     * What to store after a change: the current settings with the request's keys applied. `alerts` merges key by key
     * (a key sent as null switches that alert off); every other key replaces its value.
     *
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, mixed>  $changes  validated input (only the keys of KEYS)
     * @return array<string, mixed>
     */
    public static function merge(?array $stored, array $changes): array
    {
        $settings = self::resolve($stored);

        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $changes)) {
                continue;
            }

            if ($key === 'alerts') {
                foreach (self::ALERT_KEYS as $alert) {
                    if (array_key_exists($alert, $changes['alerts'])) {
                        $value = $changes['alerts'][$alert];
                        $settings['alerts'][$alert] = $value === null ? null : (in_array($alert, self::DECIMAL_ALERT_KEYS, true) ? Money::of($value)->toString() : (int) $value);
                    }
                }
                continue;
            }

            if ($key === 'booking_confirmation') {
                foreach (self::BOOKING_CONFIRMATION_KEYS as $field) {
                    if (array_key_exists($field, $changes['booking_confirmation'])) {
                        $value = $changes['booking_confirmation'][$field];
                        $settings['booking_confirmation'][$field] = $field === 'cancel_policy' ? self::cancelPolicy($value) : (bool) $value;
                    }
                }
                continue;
            }

            $settings[$key] = match ($key) {
                'holidays' => self::normalizeHolidays($changes['holidays']),
                'screen_lock_minutes' => (int) $changes['screen_lock_minutes'],
                'queue_numbers' => (bool) $changes['queue_numbers'],
                'booking_slot_minutes' => $changes['booking_slot_minutes'] === null ? null : (int) $changes['booking_slot_minutes'],
                'cashier_discount_max_percent' => $changes['cashier_discount_max_percent'] === null ? null : Money::of($changes['cashier_discount_max_percent'])->toString(),
                default => $changes[$key],
            };
        }

        return $settings;
    }

    /**
     * The cancellation rule as stored: trimmed text up to CANCEL_POLICY_MAX characters, null when empty or not text.
     */
    public static function cancelPolicy(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, self::CANCEL_POLICY_MAX);
    }

    /**
     * Valid, distinct dates, oldest first.
     *
     * @param  iterable<mixed>  $dates
     * @return list<string>
     */
    public static function normalizeHolidays(iterable $dates): array
    {
        $result = [];
        foreach ($dates as $date) {
            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && CarbonImmutable::createFromFormat('!Y-m-d', $date)?->format('Y-m-d') === $date) {
                $result[$date] = $date;
            }
        }
        ksort($result);

        return array_values($result);
    }

    /**
     * "05:00:00" (a time column) or "5:00" as "HH:MM".
     */
    public static function timeOf(mixed $value): string
    {
        return is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $match) === 1
            ? str_pad($match[1], 2, '0', STR_PAD_LEFT) . ':' . $match[2]
            : self::DEFAULT_BUSINESS_DAY_START;
    }

    /**
     * The business date (YYYY-MM-DD) a moment belongs to: the local date, minus a day before the branch's day start
     * (with a 05:00 start, 02:00 on Friday is still Thursday's business day).
     */
    public static function businessDate(\DateTimeInterface $at, string $timezone, string $startsAt): string
    {
        [$hour, $minute] = array_map('intval', explode(':', self::timeOf($startsAt)));
        $local = CarbonImmutable::instance($at)->setTimezone(new DateTimeZone($timezone));
        $start = $local->setTime($hour, $minute);

        return ($local->lessThan($start) ? $local->subDay() : $local)->format('Y-m-d');
    }
}
