<?php

namespace App\Services\Admin\Orders;

use App\Models\Setting;

/**
 * P4-14: the marketplace SLA limits in minutes, kept in the platform settings (`settings` table, the setting() helper):
 *  - accept: from checkout (a cash or keshbek order; an online order only once paid) until the store accepts it;
 *  - prepare: from acceptance until the food is ready;
 *  - deliver: from the courier's pickup (on_the_way / delivery) until it is handed over.
 * A stage is `warning` from WARNING_RATIO of its limit and `late` from the limit (OrderSla). Edited through
 * PUT /admin/orders/sla (admin.system.manage, step-up, reason).
 */
final class OrderSlaSettings
{
    public const ACCEPT = 'accept';

    public const PREPARE = 'prepare';

    public const DELIVER = 'deliver';

    public const WARNING_RATIO = 0.8;

    /** @var array<string, string> stage => settings key */
    public const KEYS = [
        self::ACCEPT => 'orders.sla.accept_minutes',
        self::PREPARE => 'orders.sla.prepare_minutes',
        self::DELIVER => 'orders.sla.deliver_minutes',
    ];

    /** @var array<string, int> */
    public const DEFAULTS = [
        self::ACCEPT => 5,
        self::PREPARE => 30,
        self::DELIVER => 45,
    ];

    /** @var array<string, array{int, int}> stage => [min, max] minutes */
    public const BOUNDS = [
        self::ACCEPT => [1, 120],
        self::PREPARE => [5, 300],
        self::DELIVER => [5, 300],
    ];

    /**
     * @return array{accept: int, prepare: int, deliver: int}
     */
    public function limits(): array
    {
        $limits = [];
        foreach (self::KEYS as $stage => $key) {
            $value = setting($key);
            [$min, $max] = self::BOUNDS[$stage];
            $limits[$stage] = is_numeric($value) && (int) $value >= $min && (int) $value <= $max ? (int) $value : self::DEFAULTS[$stage];
        }

        return [
            self::ACCEPT => $limits[self::ACCEPT],
            self::PREPARE => $limits[self::PREPARE],
            self::DELIVER => $limits[self::DELIVER],
        ];
    }

    /**
     * @param  array<string, int>  $minutes  any of accept, prepare, deliver (already validated)
     * @return array{before: array{accept: int, prepare: int, deliver: int}, after: array{accept: int, prepare: int, deliver: int}}
     */
    public function update(array $minutes): array
    {
        $before = $this->limits();

        foreach (self::KEYS as $stage => $key) {
            if (array_key_exists($stage, $minutes)) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) (int) $minutes[$stage]]);
            }
        }

        return ['before' => $before, 'after' => $this->limits()];
    }

    public static function label(string $stage): string
    {
        return match ($stage) {
            self::ACCEPT => 'Qabul qilish',
            self::PREPARE => 'Tayyorlash',
            self::DELIVER => 'Yetkazish',
            default => $stage,
        };
    }
}
