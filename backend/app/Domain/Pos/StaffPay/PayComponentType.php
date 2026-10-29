<?php

namespace App\Domain\Pos\StaffPay;

/**
 * P6-23 (owner's decision 29.10.2026): the parts of a wage rule; a rule has one or more and they add up
 * (PayComponents holds their fields, StaffPayCalculator what each gives):
 *
 *  - shift_rate: so'm per shift worked (a business day the person worked in the branch);
 *  - hourly_rate: so'm per hour of work time (clock-in/out and till shifts), by the minute;
 *  - sales_percent: % of the person's own sales (paid bills of their orders: lines less discount, without the
 *    service charge, room fee, place fee or tips; refunds taken off);
 *  - service_charge_percent: % of the service charge their paid bills collected;
 *  - guarantee: a guaranteed minimum per shift or per period with a % of own sales (or of the service charge) on
 *    top: "max(guarantee, %)" or "guarantee + %";
 *  - event_rate: so'm per event (a banquet booking of the chosen kinds served by the person);
 *  - tips: the person's tips of the period (only when the owner adds this part: tips are not pay by default).
 */
enum PayComponentType: string
{
    case SHIFT_RATE = 'shift_rate';
    case HOURLY_RATE = 'hourly_rate';
    case SALES_PERCENT = 'sales_percent';
    case SERVICE_CHARGE_PERCENT = 'service_charge_percent';
    case GUARANTEE = 'guarantee';
    case EVENT_RATE = 'event_rate';
    case TIPS = 'tips';

    public function label(): string
    {
        return match ($this) {
            self::SHIFT_RATE => 'Smena stavkasi',
            self::HOURLY_RATE => 'Soatbay stavka',
            self::SALES_PERCENT => 'Sotuvdan foiz',
            self::SERVICE_CHARGE_PERCENT => 'Xizmat haqidan foiz',
            self::GUARANTEE => 'Garant + foiz',
            self::EVENT_RATE => 'Tadbir stavkasi',
            self::TIPS => 'Choy puli',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
