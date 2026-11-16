<?php

namespace App\Domain\Marketplace;

use App\Models\Order;
use App\Models\User;

/**
 * What a cached store card gets at read time: "open now" from its schedule, the customer's own cash rule, and
 * `accepting_orders` (additive: open now, not order-locked, reachable).
 */
final class StoreCards
{
    public function __construct(private readonly StoreReachability $reachability) {}

    /**
     * Whether the customer may not pay cash (Order::cashPickupBanApplies, the rule checkout enforces): read once.
     */
    public static function cashBanned(?User $user): bool
    {
        return $user !== null && Order::cashPickupBanApplies($user->id);
    }

    /**
     * @param  array<string, mixed>  $card  a store's toArray()
     * @param  array{flag: bool, open: string|null, close: string|null, tz: string}  $schedule
     * @return array<string, mixed>
     */
    public function present(array $card, array $schedule, ?int $offlineMinutes, bool $cashBanned, bool $acceptingField = true): array
    {
        $open = StoreSchedule::isOpen($schedule);
        $card['is_open'] = $open;

        if ($cashBanned) {
            $card['payment_settings'] = self::withoutCash($card['payment_settings'] ?? []);
        }

        if ($acceptingField) {
            $card['accepting_orders'] = $open
                && !($card['is_order_locked'] ?? false)
                && $this->reachability->isReachableCached((int) $card['id'], $offlineMinutes);
        }

        return $card;
    }

    /**
     * The old controllers' cash override, unchanged: cash shown switched off.
     *
     * @param  mixed  $settings
     * @return array<string, mixed>
     */
    public static function withoutCash(mixed $settings): array
    {
        $settings = is_array($settings) ? $settings : [];
        if (isset($settings['cash'])) {
            if (is_array($settings['cash'])) {
                $settings['cash']['enabled'] = false;
            } else {
                $settings['cash'] = false;
            }
        } else {
            $settings['cash'] = false;
        }

        return $settings;
    }
}
