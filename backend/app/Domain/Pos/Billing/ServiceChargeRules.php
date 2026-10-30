<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\OrderChannel;
use App\Enums\PlaceFeeMode;
use App\Models\Branch;
use App\Models\Order;
use App\Models\ServiceFeeSetting;
use App\Services\Order\DiningFeeCalculator;
use App\Support\Money;

/**
 * P6-22 (C1): the service charge variants of a service fee row (service_fee_settings.service_charge_rules, the
 * branch's `service_charge_rules` module), on top of its own percent and channels:
 *
 *  - `by_order_type` {hall|table_service|takeaway: percent}: the percent of that order type (ServiceOrderType),
 *    replacing the row's percent and channels for it ("0.00": none); a type left out keeps the row's own;
 *  - the large-group rule: an eat-in order (hall, table service) of `large_group_min_guests` guests or more takes
 *    `large_group_percent` instead (one percent for the whole order, never added to the other);
 *  - `cover_amount`: the restaurant cover per guest (× guests, at least 1) on eat-in orders, charged as the place fee
 *    line (its receipt name is the store's fiscal `place_fee` charge name; the bill shows "Kuver"), instead of the
 *    row's place fee;
 *  - the notice on the bill (`service_charge_notice`): the owner's own text, else worked out from the above.
 *
 * A stolovaya stays at 0% whatever the rules say (VM 75): the lock wins over every percent here. The amounts are
 * worked out by DiningFeeCalculator::apply() on the row as these rules resolve it (whole so'm, half-up).
 */
final class ServiceChargeRules
{
    /** The stored and presented shape. */
    public const SHAPE = 'array{by_order_type: array<string, string>, large_group_min_guests: int|null, large_group_percent: string|null, cover_amount: string|null}';

    public const MAX_PERCENT = 30;

    public const MAX_GUESTS = 500;

    /**
     * @param  array<string, string>  $byOrderType  ServiceOrderType value => percent
     */
    public function __construct(
        public readonly array $byOrderType = [],
        public readonly ?int $largeGroupMinGuests = null,
        public readonly ?string $largeGroupPercent = null,
        public readonly ?string $coverAmount = null,
    ) {}

    /**
     * The rules a row holds; a stored value that no longer fits reads as no rule.
     */
    public static function of(?ServiceFeeSetting $settings): self
    {
        return self::fromArray($settings?->getAttribute('service_charge_rules'));
    }

    public static function fromArray(mixed $raw): self
    {
        if (!is_array($raw)) {
            return new self();
        }

        $byType = [];
        foreach (is_array($raw['by_order_type'] ?? null) ? $raw['by_order_type'] : [] as $type => $percent) {
            if (ServiceOrderType::tryFrom((string) $type) !== null && self::percentOk($percent)) {
                $byType[(string) $type] = Money::of((string) $percent)->toString();
            }
        }

        $min = $raw['large_group_min_guests'] ?? null;
        $min = is_int($min) || (is_string($min) && ctype_digit($min)) ? (int) $min : null;
        $groupPercent = self::percentOk($raw['large_group_percent'] ?? null) ? Money::of((string) $raw['large_group_percent'])->toString() : null;
        if ($min === null || $min < 2 || $min > self::MAX_GUESTS || $groupPercent === null) {
            $min = null;
            $groupPercent = null;
        }

        $cover = $raw['cover_amount'] ?? null;
        $cover = is_numeric($cover) && Money::of((string) $cover)->isPositive() ? Money::of((string) $cover)->toString() : null;

        // In ServiceOrderType's order
        $ordered = [];
        foreach (ServiceOrderType::values() as $type) {
            if (isset($byType[$type])) {
                $ordered[$type] = $byType[$type];
            }
        }

        return new self($ordered, $min, $groupPercent, $cover);
    }

    private static function percentOk(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= 0 && (float) $value <= self::MAX_PERCENT;
    }

    public function isEmpty(): bool
    {
        return $this->byOrderType === [] && $this->largeGroupMinGuests === null && $this->coverAmount === null;
    }

    /**
     * @return array{by_order_type: array<string, string>, large_group_min_guests: int|null, large_group_percent: string|null, cover_amount: string|null}
     */
    public function toArray(): array
    {
        return [
            'by_order_type' => $this->byOrderType,
            'large_group_min_guests' => $this->largeGroupMinGuests,
            'large_group_percent' => $this->largeGroupPercent,
            'cover_amount' => $this->coverAmount,
        ];
    }

    /**
     * Whether any percent of these rules is positive (what a stolovaya may not hold).
     */
    public function chargesAny(): bool
    {
        foreach ($this->byOrderType as $percent) {
            if (Money::of($percent)->isPositive()) {
                return true;
            }
        }

        return $this->largeGroupPercent !== null && Money::of($this->largeGroupPercent)->isPositive();
    }

    /**
     * The percent an order of `$type` with `$guests` on `$channel` takes under `$settings` and these rules, and which
     * rule set it. `$locked`: the stolovaya's lock (always 0%).
     *
     * @return array{percent: string, rule: ServiceChargeRule}
     */
    public function percentFor(ServiceFeeSetting $settings, ServiceOrderType $type, OrderChannel $channel, ?int $guests, bool $locked): array
    {
        if ($locked || $channel === OrderChannel::TELEGRAM) {
            return ['percent' => '0.00', 'rule' => ServiceChargeRule::NONE];
        }

        if (isset($this->byOrderType[$type->value])) {
            $percent = $this->byOrderType[$type->value];
            $rule = ServiceChargeRule::ORDER_TYPE;
        } else {
            $channels = $settings->service_charge_channels ?? [OrderChannel::POS_HALL->value];
            $percent = in_array($channel->value, $channels, true) ? Money::of((string) $settings->service_charge_percent)->toString() : '0.00';
            $rule = ServiceChargeRule::BASE;
        }

        if ($type->isDineIn() && $this->largeGroupMinGuests !== null && $this->largeGroupPercent !== null && (int) $guests >= $this->largeGroupMinGuests) {
            $percent = $this->largeGroupPercent;
            $rule = ServiceChargeRule::LARGE_GROUP;
        }

        return Money::of($percent)->isPositive() ? ['percent' => $percent, 'rule' => $rule] : ['percent' => '0.00', 'rule' => ServiceChargeRule::NONE];
    }

    /**
     * A copy of `$settings` (never saved) as these rules resolve it for one order, for DiningFeeCalculator::apply():
     * the percent of the order applied on its channel, the cover as a per-guest place fee on eat-in orders.
     *
     * @return array{settings: ServiceFeeSetting, rule: ServiceChargeRule, cover: bool}
     */
    public function resolve(ServiceFeeSetting $settings, ServiceOrderType $type, OrderChannel $channel, ?int $guests, bool $locked): array
    {
        ['percent' => $percent, 'rule' => $rule] = $this->percentFor($settings, $type, $channel, $guests, $locked);

        $copy = clone $settings;
        $copy->setAttribute('service_charge_percent', $percent);
        $copy->setAttribute('service_charge_channels', [$channel->value]);

        $cover = $this->coverAmount !== null && $type->isDineIn();
        if ($cover) {
            $copy->setAttribute('place_fee_amount', $this->coverAmount);
            $copy->setAttribute('place_fee_mode', PlaceFeeMode::PER_PERSON);
            $copy->setAttribute('place_fee_channels', [$channel->value]);
        }

        return ['settings' => $copy, 'rule' => $rule, 'cover' => $cover];
    }

    /**
     * The notice printed on the bill and shown before ordering: the owner's text when set, else worked out from the
     * row and these rules ("Xizmat haqi 15%. 8 va undan ko'p mehmonli stolga 18%. Kuver: 5 000 so'm har bir mehmon
     * uchun."); null when nothing is charged (and always for a stolovaya, which charges none).
     *
     * `$withRules`: the branch's `service_charge_rules` module is on. Off, only the owner's own text is a notice: a
     * branch without the rules (choyxona, osh markazi) prints no notice line, as before P6-22 (review C8).
     */
    public function notice(?ServiceFeeSetting $settings, bool $locked, bool $withRules = true): ?string
    {
        if ($settings === null) {
            return null;
        }
        $own = trim((string) $settings->getAttribute('service_charge_notice'));
        if ($own !== '') {
            return $own;
        }
        if (!$withRules) {
            return null;
        }
        if ($locked) {
            return $this->coverAmount !== null ? self::coverSentence($this->coverAmount) : null;
        }

        $channelOf = fn (ServiceOrderType $type) => $type === ServiceOrderType::HALL ? OrderChannel::POS_HALL : OrderChannel::POS_TAKEAWAY;
        $entries = [];
        foreach (ServiceOrderType::cases() as $type) {
            // The percent of the type for a small table (the large-group rule is its own sentence)
            $percent = (new self($this->byOrderType, null, null, $this->coverAmount))->percentFor($settings, $type, $channelOf($type), 1, false)['percent'];
            if (Money::of($percent)->isPositive()) {
                $entries[$type->value] = $percent;
            }
        }

        $sentences = [];
        $where = [ServiceOrderType::HALL->value => 'zalda', ServiceOrderType::TABLE_SERVICE->value => 'stolga olib kelishda', ServiceOrderType::TAKEAWAY->value => 'olib ketishda'];
        if (count(array_unique($entries)) === 1 && ($entries === [ServiceOrderType::HALL->value => reset($entries)] || count($entries) === count(ServiceOrderType::cases()))) {
            $sentences[] = 'Xizmat haqi ' . self::percentText((string) reset($entries)) . '.';
        } elseif ($entries !== []) {
            $parts = [];
            foreach ($entries as $type => $percent) {
                $parts[] = $where[$type] . ' ' . self::percentText($percent);
            }
            $sentences[] = 'Xizmat haqi: ' . implode(', ', $parts) . '.';
        }
        if ($this->largeGroupMinGuests !== null && $this->largeGroupPercent !== null && Money::of($this->largeGroupPercent)->isPositive()) {
            $sentences[] = "{$this->largeGroupMinGuests} va undan ko'p mehmonli stolga xizmat haqi " . self::percentText($this->largeGroupPercent) . '.';
        }
        if ($this->coverAmount !== null) {
            $sentences[] = self::coverSentence($this->coverAmount);
        }

        return $sentences !== [] ? implode(' ', $sentences) : null;
    }

    /**
     * The notice of the rules in force for a POS order (its hall's row, else the branch's or the store's; the rules
     * and the worked-out notice only with the branch's module, else only the owner's own text); null for any other
     * order.
     */
    public static function noticeFor(Order $order): ?string
    {
        if (!$order->channel->isPos() || $order->branch_id === null) {
            return null;
        }
        $branch = Branch::find((int) $order->branch_id);
        if ($branch === null) {
            return null;
        }
        $modules = app(PosModules::class);
        $settings = app(DiningFeeCalculator::class)->settingsForHall((int) $order->store_id, (int) $branch->id, $order->hall_id);
        $withRules = $modules->enabled($branch, PosModule::SERVICE_CHARGE_RULES);
        $rules = $withRules ? self::of($settings) : new self();

        return $rules->notice($settings, $modules->serviceChargeLocked((int) $branch->id), $withRules);
    }

    private static function coverSentence(string $amount): string
    {
        return 'Kuver: ' . number_format(Money::of($amount)->toFloat(), 0, '.', ' ') . " so'm har bir mehmon uchun.";
    }

    /**
     * "15.00" → "15%", "12.50" → "12,5%".
     */
    public static function percentText(string $percent): string
    {
        $text = rtrim(rtrim(Money::of($percent)->toString(), '0'), '.');

        return str_replace('.', ',', $text) . '%';
    }
}
