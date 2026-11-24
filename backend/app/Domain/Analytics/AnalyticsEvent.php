<?php

namespace App\Domain\Analytics;

use App\Enums\OrderType;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md A.3): the closed list of Mini App analytics events, stored as analytics_events.event
 * (smallint). The numbers are stored data: never renumber a case, only add new ones.
 *
 * spec() is the allowlist the ingestion holds every event to (EventIngestor): which top-level ids it carries (true =
 * required) and which props keys it may send, each with its value rule. Anything else drops the event. Nothing here
 * can carry free text: every string is from a fixed list or a UUID that is resolved and not kept.
 */
enum AnalyticsEvent: int
{
    case APP_OPEN = 1;
    case STORE_VIEW = 2;
    case CATEGORY_VIEW = 3;
    case PRODUCT_VIEW = 4;
    case ADD_TO_CART = 5;
    case CART_VIEW = 6;
    case CHECKOUT_START = 7;
    case CHECKOUT_ERROR = 8;
    case ORDER_PLACED = 9;
    case PAYMENT_REDIRECT = 10;
    case SEARCH = 11;
    case PROMO_BANNER_CLICK = 12;

    /** analytics_events.source of the Mini App (the landing page counts elsewhere, landing_daily_visits) */
    public const SOURCE_MINIAPP = 1;

    /** checkout_error.code values */
    public const ERROR_CODES = ['out_of_zone', 'min_order', 'store_closed', 'item_unavailable', 'validation', 'payment', 'network'];

    /** checkout_start.payment_method values (the checkout's own list, POST /orders) */
    public const PAYMENT_METHODS = ['cash', 'click', 'payme', 'uzum', 'paynet', 'paylov', 'keshbek'];

    /** The name the Mini App sends (`app_open`), or null for a name off the list. */
    public static function fromName(mixed $name): ?self
    {
        if (!is_string($name)) {
            return null;
        }
        foreach (self::cases() as $case) {
            if ($case->eventName() === $name) {
                return $case;
            }
        }

        return null;
    }

    public function eventName(): string
    {
        return strtolower($this->name);
    }

    /**
     * ids: top-level id fields the event may carry (true: required); props: allowed keys => rule
     * (EventIngestor::prop()), `!` in front of a rule marks the key required.
     *
     * @return array{ids: array<string, bool>, props: array<string, string>}
     */
    public function spec(): array
    {
        return match ($this) {
            self::APP_OPEN => ['ids' => [], 'props' => ['start_kind' => 'start_kind', 'campaign_code' => 'campaign_code']],
            self::STORE_VIEW, self::PAYMENT_REDIRECT => ['ids' => ['store_id' => true], 'props' => []],
            self::CATEGORY_VIEW => ['ids' => ['store_id' => true, 'category_id' => true], 'props' => []],
            self::PRODUCT_VIEW => ['ids' => ['store_id' => true, 'product_id' => true], 'props' => []],
            self::ADD_TO_CART => ['ids' => ['store_id' => true, 'product_id' => true], 'props' => ['qty' => 'quantity']],
            self::CART_VIEW => ['ids' => ['store_id' => true], 'props' => ['items' => 'count', 'value' => 'amount']],
            self::CHECKOUT_START => ['ids' => ['store_id' => true], 'props' => ['items' => 'count', 'value' => 'amount', 'payment_method' => 'payment_method', 'order_type' => 'order_type']],
            self::CHECKOUT_ERROR => ['ids' => ['store_id' => true], 'props' => ['code' => '!error_code']],
            self::ORDER_PLACED => ['ids' => [], 'props' => ['order_uuid' => '!uuid']],
            self::SEARCH => ['ids' => ['store_id' => false], 'props' => ['results' => 'count', 'length' => 'length']],
            self::PROMO_BANNER_CLICK => ['ids' => ['store_id' => false], 'props' => ['promo_id' => 'id']],
        };
    }

    /** @return list<string> */
    public static function orderTypes(): array
    {
        return array_map(fn (OrderType $type) => $type->value, OrderType::cases());
    }
}
