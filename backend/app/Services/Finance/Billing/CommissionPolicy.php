<?php

namespace App\Services\Finance\Billing;

use App\Enums\CommissionChannel;
use App\Enums\CourierType;
use App\Enums\OrderChannel;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\TariffPlan;

/**
 * P4-21: RIZQIM's commission on an order, by channel (docs/BILLING.md, "Komissiya"; owner decision 24.10.2026):
 * RIZQIM courier 15%, the store's own delivery 10%, pickup 5%, the hall / the store's own till 0%, aggregators 0%.
 *
 * The rate of a channel, the first that applies:
 * 1. the store's own rate for that channel (stores.commission_overrides; set by an admin, audited);
 * 2. for an online channel (Mini App / mobile: courier, own delivery, pickup), the legacy rules unchanged: the current
 *    P1-23 plan's commission_percent, else the store's flat commission_rate when it has one (every store created
 *    before Billing v2 has one until `billing:migrate-plans` moves it to the channel rates);
 * 3. config('billing.commission') for the channel.
 *
 * CreateOrderAction snapshots the channel, rate and amount on the order; the ledger charges that amount on delivery,
 * so a change only reaches new orders.
 */
class CommissionPolicy
{
    /**
     * Which channel an order placed through CreateOrderAction pays: a POS order the store's till rate, an aggregator's
     * its rate, a pickup the pickup rate, a delivery the RIZQIM courier rate when the store's deliveries go to the
     * RIZQIM fleet (stores.delivery_courier), else the own-delivery rate.
     */
    public function channelFor(Store $store, OrderChannel $channel, string $orderType): CommissionChannel
    {
        return match (true) {
            $channel->isPos() || $orderType === 'dine_in' => CommissionChannel::POS,
            $channel === OrderChannel::AGGREGATOR => CommissionChannel::AGGREGATOR,
            $orderType === 'pickup' => CommissionChannel::PICKUP,
            $this->courierOf($store) === CourierType::FLEET => CommissionChannel::RIZQIM_COURIER,
            default => CommissionChannel::OWN_DELIVERY,
        };
    }

    /**
     * The channel of an existing order: its snapshot, else worked out from its channel and type.
     */
    public function channelOfOrder(Order $order, Store $store): CommissionChannel
    {
        $snapshot = $order->commission_channel ?? null;
        if (is_string($snapshot) && ($channel = CommissionChannel::tryFrom($snapshot)) !== null) {
            return $channel;
        }

        // An order built in memory may lack both (the columns' defaults are only in the database)
        $channel = $order->getAttribute('channel');
        $type = $order->getAttribute('order_type');
        $orderChannel = $channel instanceof OrderChannel ? $channel : (is_string($channel) ? OrderChannel::tryFrom($channel) : null) ?? OrderChannel::TELEGRAM;
        $type = $type instanceof \BackedEnum ? (string) $type->value : (is_string($type) ? $type : 'delivery');

        return $this->channelFor($store, $orderChannel, $type);
    }

    /**
     * @return numeric-string the percent, e.g. "10.00"
     */
    public function rateFor(Store $store, CommissionChannel $channel): string
    {
        $override = $this->overrides($store)[$channel->value] ?? null;
        if ($override !== null) {
            return $override;
        }

        if ($channel->isOnline()) {
            $legacy = $this->legacyPlanRate($store) ?? $this->flatRate($store);
            if ($legacy !== null) {
                return $legacy;
            }
        }

        return $this->defaultRate($channel);
    }

    /**
     * Every channel's rate for the store and where it comes from (override | plan | flat | default), for the owner and
     * admin screens.
     *
     * @return array<string, array{rate: string, source: string, default: string}>
     */
    public function table(Store $store): array
    {
        $overrides = $this->overrides($store);
        $plan = $this->legacyPlanRate($store);
        $flat = $this->flatRate($store);
        $rows = [];

        foreach (CommissionChannel::cases() as $channel) {
            $source = match (true) {
                isset($overrides[$channel->value]) => 'override',
                $channel->isOnline() && $plan !== null => 'plan',
                $channel->isOnline() && $flat !== null => 'flat',
                default => 'default',
            };
            $rows[$channel->value] = [
                'rate' => $this->rateFor($store, $channel),
                'source' => $source,
                'default' => $this->defaultRate($channel),
            ];
        }

        return $rows;
    }

    /**
     * @return numeric-string
     */
    public function defaultRate(CommissionChannel $channel): string
    {
        return self::normalize(config("billing.commission.{$channel->value}", '0')) ?? '0.00';
    }

    /**
     * The store's own rates, valid ones only.
     *
     * @return array<string, numeric-string>
     */
    public function overrides(Store $store): array
    {
        $rates = [];
        foreach ((array) ($store->commission_overrides ?? []) as $channel => $rate) {
            if (CommissionChannel::tryFrom((string) $channel) !== null && ($rate = self::normalize($rate)) !== null) {
                $rates[(string) $channel] = $rate;
            }
        }

        return $rates;
    }

    /**
     * @return numeric-string|null
     */
    public function flatRate(Store $store): ?string
    {
        return self::normalize($store->getAttribute('commission_rate'));
    }

    /**
     * The commission_percent of the store's current plan when it is a P1-23 plan that sets one.
     *
     * @return numeric-string|null
     */
    public function legacyPlanRate(Store $store): ?string
    {
        if (!$store->exists) {
            return null;
        }

        $plan = TariffPlan::query()
            ->whereIn('id', StoreSubscription::query()->current()->where('store_id', $store->id)->select('tariff_plan_id'))
            ->first(['id', 'version', 'commission_percent']);

        return $plan !== null && !$plan->isV2() ? self::normalize($plan->commission_percent) : null;
    }

    public function courierOf(Store $store): CourierType
    {
        $courier = $store->getAttribute('delivery_courier');

        return $courier instanceof CourierType ? $courier : (CourierType::tryFrom((string) $courier) ?? CourierType::OWN);
    }

    /**
     * A percent between 0 and 100 as a two-decimal string; null for anything else.
     *
     * @return numeric-string|null
     */
    public static function normalize(mixed $rate): ?string
    {
        if ($rate === null || $rate === '' || !is_numeric($rate)) {
            return null;
        }

        $value = \App\Support\Money::of((string) $rate);
        if ($value->isNegative() || $value->isGreaterThan(100)) {
            return null;
        }

        return $value->toString();
    }
}
