<?php

namespace Tests\Unit;

use App\Domain\Pos\StaffPay\PayComponents;
use App\Domain\Pos\StaffPay\PayPeriod;
use App\Domain\Pos\StaffPay\StaffPayCalculator;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P6-23: what each wage rule component gives, alone and together, the guarantee's two modes per shift and per period,
 * a rule changed mid-period, rounding to the so'm; the pay periods' business-day ranges; the components' checks.
 */
class StaffPayCalculatorTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $components
     */
    private static function rule(array $components, string $uuid = 'r1'): array
    {
        return ['uuid' => $uuid, 'components' => PayComponents::normalize($components)];
    }

    /**
     * A day: worked or not, minutes, own sales, service charge, events by kind, tips.
     *
     * @param  array<string, int>  $events
     */
    private static function day(string $date, ?array $rule, bool $worked = true, int $minutes = 0, string $sales = '0', string $service = '0', array $events = [], string $tips = '0'): array
    {
        return ['date' => $date, 'rule' => $rule, 'worked' => $worked, 'minutes' => $minutes, 'sales' => Money::of($sales), 'service_charge' => Money::of($service), 'events' => $events, 'tips' => Money::of($tips)];
    }

    private function amounts(array $result): array
    {
        return array_column($result['components'], 'amount', 'type');
    }

    public function test_shift_rate_pays_each_shift_worked(): void
    {
        $rule = self::rule([['type' => 'shift_rate', 'amount' => '150000']]);
        $result = (new StaffPayCalculator())->calculate([
            self::day('2026-11-01', $rule),
            self::day('2026-11-02', $rule, worked: false),
            self::day('2026-11-03', $rule),
        ], 30);

        $this->assertSame(['shift_rate' => '300000.00'], $this->amounts($result));
        $this->assertSame('300000.00', $result['gross']);
        $this->assertSame('2', $result['components'][0]['quantity']);
    }

    public function test_hourly_rate_counts_minutes_and_rounds_to_the_som(): void
    {
        $rule = self::rule([['type' => 'hourly_rate', 'amount' => '20000']]);
        // 7 h 25 min + 4 h 10 min = 695 min = 11.5833 h × 20 000 = 231 666.67 → 231 667
        $result = (new StaffPayCalculator())->calculate([self::day('2026-11-01', $rule, minutes: 445), self::day('2026-11-02', $rule, minutes: 250)], 30);

        $this->assertSame('231667.00', $result['gross']);
        $this->assertSame('11.58', $result['components'][0]['quantity']);
    }

    public function test_sales_and_service_charge_percents(): void
    {
        $rule = self::rule([['type' => 'sales_percent', 'percent' => '5'], ['type' => 'service_charge_percent', 'percent' => '50']]);
        $result = (new StaffPayCalculator())->calculate([
            self::day('2026-11-01', $rule, sales: '1234567', service: '123456'),
            self::day('2026-11-02', $rule, sales: '800000', service: '80000'),
        ], 7);

        // 5% × 2 034 567 = 101 728.35 → 101 728; 50% × 203 456 = 101 728
        $this->assertSame(['sales_percent' => '101728.00', 'service_charge_percent' => '101728.00'], $this->amounts($result));
        $this->assertSame('203456.00', $result['gross']);
        $this->assertSame('2034567.00', $result['components'][0]['base']);
    }

    public function test_guarantee_per_shift_max_takes_the_larger_each_day(): void
    {
        $rule = self::rule([['type' => 'guarantee', 'amount' => '100000', 'per' => 'shift', 'mode' => 'max', 'percent' => '10', 'base' => 'sales']]);
        $result = (new StaffPayCalculator())->calculate([
            self::day('2026-11-01', $rule, sales: '600000'),   // 60 000 < 100 000 → 100 000
            self::day('2026-11-02', $rule, sales: '1500000'),  // 150 000
            self::day('2026-11-03', $rule, worked: false),     // no shift, nothing
        ], 30);

        $this->assertSame('250000.00', $result['gross']);
        $this->assertStringContainsString('1 tasida garant', $result['components'][0]['detail']);
    }

    public function test_guarantee_per_shift_plus_adds_the_percent(): void
    {
        $rule = self::rule([['type' => 'guarantee', 'amount' => '100000', 'per' => 'shift', 'mode' => 'plus', 'percent' => '10', 'base' => 'sales']]);
        $result = (new StaffPayCalculator())->calculate([self::day('2026-11-01', $rule, sales: '600000'), self::day('2026-11-02', $rule, sales: '1500000')], 30);

        $this->assertSame('410000.00', $result['gross']); // 2 × 100 000 + 10% × 2 100 000
    }

    public function test_guarantee_per_period_compares_the_whole_period(): void
    {
        $max = self::rule([['type' => 'guarantee', 'amount' => '3000000', 'per' => 'period', 'mode' => 'max', 'percent' => '10', 'base' => 'sales']]);
        $plus = self::rule([['type' => 'guarantee', 'amount' => '3000000', 'per' => 'period', 'mode' => 'plus', 'percent' => '10', 'base' => 'sales']]);
        $days = fn (array $rule, string $sales) => array_map(fn (int $d) => self::day(sprintf('2026-11-%02d', $d), $rule, worked: $d <= 20, sales: $d <= 20 ? $sales : '0'), range(1, 30));

        $calculator = new StaffPayCalculator();
        // 20 days × 1 000 000 = 20 000 000 × 10% = 2 000 000 < 3 000 000 → 3 000 000
        $this->assertSame('3000000.00', $calculator->calculate($days($max, '1000000'), 30)['gross']);
        // 20 days × 2 000 000 × 10% = 4 000 000 > 3 000 000
        $this->assertSame('4000000.00', $calculator->calculate($days($max, '2000000'), 30)['gross']);
        $this->assertSame('5000000.00', $calculator->calculate($days($plus, '1000000'), 30)['gross']);
    }

    public function test_guarantee_on_the_service_charge_base(): void
    {
        $rule = self::rule([['type' => 'guarantee', 'amount' => '50000', 'per' => 'shift', 'mode' => 'max', 'percent' => '40', 'base' => 'service_charge']]);
        $result = (new StaffPayCalculator())->calculate([self::day('2026-11-01', $rule, sales: '9000000', service: '200000')], 30);

        $this->assertSame('80000.00', $result['gross']); // 40% × 200 000 = 80 000 > 50 000; the sales do not count
    }

    public function test_event_rate_counts_the_chosen_kinds(): void
    {
        $all = self::rule([['type' => 'event_rate', 'amount' => '200000']]);
        $weddings = self::rule([['type' => 'event_rate', 'amount' => '200000', 'event_types' => ['ziyofat']]], 'r2');
        $events = ['ziyofat' => 1, 'tugilgan_kun' => 2, 'other' => 3];

        $calculator = new StaffPayCalculator();
        // Default: every kind but "other"
        $this->assertSame('600000.00', $calculator->calculate([self::day('2026-11-01', $all, events: $events)], 30)['gross']);
        $this->assertSame('200000.00', $calculator->calculate([self::day('2026-11-01', $weddings, events: $events)], 30)['gross']);
    }

    public function test_tips_count_only_with_the_tips_component(): void
    {
        $without = self::rule([['type' => 'shift_rate', 'amount' => '100000']]);
        $with = self::rule([['type' => 'shift_rate', 'amount' => '100000'], ['type' => 'tips']], 'r2');

        $calculator = new StaffPayCalculator();
        $this->assertSame('100000.00', $calculator->calculate([self::day('2026-11-01', $without, tips: '45000')], 1)['gross']);
        $this->assertSame('145000.00', $calculator->calculate([self::day('2026-11-01', $with, tips: '45000')], 1)['gross']);
    }

    public function test_components_add_up(): void
    {
        $rule = self::rule([
            ['type' => 'shift_rate', 'amount' => '50000'],
            ['type' => 'hourly_rate', 'amount' => '10000'],
            ['type' => 'sales_percent', 'percent' => '3'],
            ['type' => 'service_charge_percent', 'percent' => '20'],
            ['type' => 'event_rate', 'amount' => '100000'],
            ['type' => 'tips'],
        ]);
        $result = (new StaffPayCalculator())->calculate([self::day('2026-11-01', $rule, minutes: 480, sales: '2000000', service: '200000', events: ['ziyofat' => 1], tips: '30000')], 1);

        // 50 000 + 8 × 10 000 + 60 000 + 40 000 + 100 000 + 30 000
        $this->assertSame('360000.00', $result['gross']);
        $this->assertCount(6, $result['components']);
    }

    public function test_a_rule_changed_mid_period_pays_each_part_with_its_version(): void
    {
        $old = self::rule([['type' => 'shift_rate', 'amount' => '100000'], ['type' => 'guarantee', 'amount' => '3000000', 'per' => 'period', 'mode' => 'max', 'percent' => '0']], 'old');
        $new = self::rule([['type' => 'shift_rate', 'amount' => '150000']], 'new');
        $days = [];
        foreach (range(1, 30) as $d) {
            $days[] = self::day(sprintf('2026-11-%02d', $d), $d <= 10 ? $old : $new, worked: $d % 2 === 0);
        }
        $result = (new StaffPayCalculator())->calculate($days, 30);

        // old: 5 shifts × 100 000 + guarantee 3 000 000 × 10/30 = 1 000 000; new: 10 shifts × 150 000
        $this->assertSame(['old', 'old', 'new'], array_column($result['components'], 'rule_uuid'));
        $this->assertSame(['500000.00', '1000000.00', '1500000.00'], array_column($result['components'], 'amount'));
        $this->assertSame('3000000.00', $result['gross']);
    }

    public function test_days_without_a_rule_pay_nothing(): void
    {
        $rule = self::rule([['type' => 'shift_rate', 'amount' => '100000']]);
        $result = (new StaffPayCalculator())->calculate([self::day('2026-11-01', null), self::day('2026-11-02', $rule)], 30);

        $this->assertSame('100000.00', $result['gross']);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function periods(): array
    {
        return [
            'day' => ['day', '2026-11-30', '2026-11-30', '2026-11-30'],
            'week mid' => ['week', '2026-11-04', '2026-11-02', '2026-11-08'],
            'week across a month' => ['week', '2026-12-01', '2026-11-30', '2026-12-06'],
            'week on sunday' => ['week', '2026-11-08', '2026-11-02', '2026-11-08'],
            'half 15th' => ['half_month', '2026-11-15', '2026-11-01', '2026-11-15'],
            'half 16th' => ['half_month', '2026-11-16', '2026-11-16', '2026-11-30'],
            'half february' => ['half_month', '2027-02-20', '2027-02-16', '2027-02-28'],
            'month' => ['month', '2026-12-31', '2026-12-01', '2026-12-31'],
            'month leap february' => ['month', '2028-02-10', '2028-02-01', '2028-02-29'],
        ];
    }

    #[DataProvider('periods')]
    public function test_pay_period_ranges(string $period, string $date, string $from, string $to): void
    {
        $this->assertSame([$from, $to], PayPeriod::from($period)->range($date));
    }

    public function test_components_are_checked(): void
    {
        $bad = [
            [['type' => 'bonus']],
            [['type' => 'shift_rate', 'amount' => '-5']],
            [['type' => 'shift_rate', 'amount' => '100000001']],
            [['type' => 'sales_percent', 'percent' => '101']],
            [['type' => 'sales_percent', 'percent' => '1.234']],
            [['type' => 'guarantee', 'amount' => '1000', 'mode' => 'min']],
            [['type' => 'guarantee', 'amount' => '1000', 'per' => 'month']],
            [['type' => 'event_rate', 'amount' => '1000', 'event_types' => ['wedding']]],
            [['type' => 'tips'], ['type' => 'tips']],
            'not a list',
        ];
        foreach ($bad as $components) {
            try {
                PayComponents::normalize($components);
                $this->fail('Accepted: ' . json_encode($components));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([
            ['type' => 'shift_rate', 'amount' => '150000.00'],
            ['type' => 'guarantee', 'amount' => '100000.00', 'per' => 'shift', 'mode' => 'max', 'percent' => '0.00', 'base' => 'sales'],
        ], PayComponents::normalize([['type' => 'shift_rate', 'amount' => 150000], ['type' => 'guarantee', 'amount' => '100000']]));
    }
}
