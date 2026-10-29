<?php

namespace App\Domain\Pos\Display;

use App\Events\Broadcast\DisplayCartBroadcast;
use App\Models\Device;
use Illuminate\Support\Facades\Cache;

/**
 * P6-14 (A3, docs/POS_SPEC.md 2.14a): the cart a till shows on its customer display. The till publishes the cart it is
 * building (`POST /pos/display/cart`); it is kept briefly per till, and a hint (`display.cart`, ids and a sequence only)
 * goes to the branch channel `pos-branch.{id}`. The display refetches `GET /pos/display/state`, so line names and prices
 * never ride the staff channel, and a display that missed the hint (reconnecting, polling) reads the same state.
 *
 * Nothing here is a record of sale: the cache only holds what the customer already sees on the screen, for a few hours.
 */
class DisplayFeed
{
    /** How long a published cart stays readable (a till left on a paid screen should not show it tomorrow). */
    public const TTL_MINUTES = 360;

    public const STATES = ['idle', 'cart', 'payment', 'paid'];

    /**
     * Keep the till's cart and announce it. A stale publish (lower `seq` than the kept one, same cashier session) is ignored.
     *
     * @param  array<string, mixed>  $cart  validated (PosDisplayController::publish)
     * @return array<string, mixed>|null the kept cart; null when ignored as stale
     */
    public function publish(Device $till, array $cart): ?array
    {
        $key = self::cartKey((int) $till->id);
        $kept = Cache::get($key);
        $seq = (int) ($cart['seq'] ?? 0);
        if (is_array($kept) && $seq > 0 && $seq < (int) ($kept['seq'] ?? 0) && ($kept['session'] ?? null) === ($cart['session'] ?? null)) {
            return null;
        }

        $cart['terminal'] = (string) $till->uuid;
        $cart['updated_at'] = now()->toJSON();
        $ttl = now()->addMinutes(self::TTL_MINUTES);
        Cache::put($key, $cart, $ttl);
        Cache::put(self::latestKey((int) $till->branch_id), (int) $till->id, $ttl);

        event(new DisplayCartBroadcast((int) $till->branch_id, (int) $till->store_id, (string) $till->uuid, $seq, (string) $cart['state']));

        return $cart;
    }

    /**
     * The cart a display shows: its own till's when it follows one, else the branch's latest.
     *
     * @return array<string, mixed>|null
     */
    public function cartFor(Device $display, int $branchId): ?array
    {
        $tillId = $display->display_for_device_id !== null ? (int) $display->display_for_device_id : Cache::get(self::latestKey($branchId));
        if ($tillId === null) {
            return null;
        }
        $cart = Cache::get(self::cartKey((int) $tillId));

        return is_array($cart) ? $cart : null;
    }

    public static function cartKey(int $deviceId): string
    {
        return "pos-display:cart:{$deviceId}";
    }

    public static function latestKey(int $branchId): string
    {
        return "pos-display:latest:{$branchId}";
    }
}
