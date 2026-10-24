<?php

namespace App\Domain\Pos\Booking;

use App\Domain\Pos\Hall\BusinessCalendar;
use App\Domain\Pos\Hall\RoomTariffResolver;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\DayType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\OrderChannel;
use App\Enums\PlaceFeeMode;
use App\Enums\UserRole;
use App\Jobs\SendBookingConfirmation;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Order\DiningFeeCalculator;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P3-38: the booking confirmation the guest receives (docs/POS_SPEC.md §2.7, docs/API.md "Bron tasdig'i"): one text in
 * plain Uzbek with the place, the time, the room, what the place will cost, the pre-ordered dishes, the deposit, the
 * branch's cancellation rule and how to reach the branch.
 *
 * The charges are announced by the rules the order will use later (PosChargesCalculator): the room fee is the room
 * tariff (RoomTariffResolver) of the booked room for the day type (BusinessCalendar: holidays from pos_settings, the
 * weekend days) of the business date the booking starts on; the minimum spend is the booking's own (agreed with the
 * guest) or else the room's (× guests when per person); the service charge and the place fee are the room's hall's
 * settings, else the branch's or the store's (DiningFeeCalculator), as they apply to the hall channel. A booking
 * without a room yet says "keyin belgilanadi" and has no room fee or room minimum.
 *
 * Sending: `telegram` when the contact phone belongs to a customer of the store with a Telegram chat with the bot (and
 * order updates over Telegram not switched off), `sms` when the branch allows SMS (pos_settings.booking_confirmation.sms,
 * an SMS costs money) and there is a phone; `auto` takes Telegram first, then SMS. The message is queued
 * (SendBookingConfirmation), the booking remembers when and how (confirmation_sent_at, confirmation_channel) and the
 * audit log gets `booking.confirmation_sent`.
 */
class BookingConfirmationService
{
    public const LINE_KINDS = ['header', 'place', 'time', 'room', 'room_fee', 'min_spend', 'service_charge', 'place_fee', 'pre_order', 'deposit', 'cancel_policy', 'contact'];

    public const CHANNELS = ['auto', 'telegram', 'sms'];

    /** The OpenAPI shape of the confirmation (GET .../confirmation). */
    public const CONFIRMATION = "array{text: string, lines: list<array{kind: 'header'|'place'|'time'|'room'|'room_fee'|'min_spend'|'service_charge'|'place_fee'|'pre_order'|'deposit'|'cancel_policy'|'contact', label: string, value: string}>, room_fee: array{amount: string, day_type: 'weekday'|'weekend'|'holiday', tariff_id: int}|null, channels: array{telegram: bool, sms: bool}}";

    /** The icon in front of each kind of line in the text (none on the place line; the header's comes after it). */
    private const ICONS = [
        'time' => '📅',
        'room' => '🚪',
        'room_fee' => '💵',
        'min_spend' => '💵',
        'service_charge' => '💵',
        'place_fee' => '💵',
        'pre_order' => '🍽',
        'deposit' => '💳',
        'cancel_policy' => 'ℹ️',
        'contact' => '📞',
    ];

    private const WEEKDAYS = [1 => 'Dushanba', 2 => 'Seshanba', 3 => 'Chorshanba', 4 => 'Payshanba', 5 => 'Juma', 6 => 'Shanba', 7 => 'Yakshanba'];

    private const MONTHS = [1 => 'yanvar', 2 => 'fevral', 3 => 'mart', 4 => 'aprel', 5 => 'may', 6 => 'iyun', 7 => 'iyul', 8 => 'avgust', 9 => 'sentabr', 10 => 'oktabr', 11 => 'noyabr', 12 => 'dekabr'];

    public function __construct(
        protected BusinessCalendar $calendar,
        protected RoomTariffResolver $tariffs,
        protected DiningFeeCalculator $fees,
        protected TelegramMessenger $telegram,
        protected NotificationPreferenceService $preferences,
        protected AuditLogger $audit,
    ) {}

    /**
     * The confirmation as the apps show, copy and print it.
     *
     * @return array{text: string, lines: list<array{kind: string, label: string, value: string}>, room_fee: array{amount: string, day_type: string, tariff_id: int}|null, channels: array{telegram: bool, sms: bool}}
     */
    public function build(Booking $booking): array
    {
        $branch = Branch::with('store')->findOrFail($booking->branch_id);
        $store = $branch->store ?? Store::findOrFail($booking->store_id);
        $tz = StoreTimezone::of($store);
        $guests = max(1, (int) $booking->guests_planned);

        $lines = [];
        $add = function (string $kind, string $label, string $value) use (&$lines): void {
            $lines[] = ['kind' => $kind, 'label' => $label, 'value' => $value];
        };

        $add('header', '', $this->headline($booking));
        $add('place', '', implode(', ', array_filter([
            trim((string) $store->name),
            trim((string) ($branch->address ?: $branch->name)),
        ], fn (string $part) => $part !== '')));

        $start = CarbonImmutable::instance($booking->starts_at)->setTimezone($tz);
        $end = CarbonImmutable::instance($booking->ends_at)->setTimezone($tz);
        $add('time', '', self::WEEKDAYS[$start->isoWeekday()] . ', ' . $start->day . '-' . self::MONTHS[$start->month] . ', ' . $start->format('H:i') . '–' . $end->format('H:i'));

        $tableIds = $this->tableIds($booking);
        $tables = DiningTable::withTrashed()->whereIn('id', $tableIds)->get()->keyBy('id');
        $main = $booking->dining_table_id !== null ? $tables->get((int) $booking->dining_table_id) : null;

        if ($main === null) {
            $add('room', 'Xona', 'keyin belgilanadi · ' . $guests . ' kishi');
        } else {
            $names = array_map(fn (int $id) => '"' . $tables->get($id)?->name . '"', array_values(array_filter($tableIds, fn (int $id) => $tables->has($id))));
            $seats = count($names) === 1 && $main->seats !== null && $main->seats > 0 ? ' (' . $main->seats . ' kishilik)' : '';
            $add('room', count($names) > 1 ? 'Xonalar' : 'Xona', implode(', ', $names) . $seats . ' · ' . $guests . ' kishi');
        }

        // The room fee: the booked room's tariff for the day type of the business date the booking starts on
        $roomFee = null;
        if ($main !== null) {
            $date = $this->calendar->businessDate($branch, $booking->starts_at);
            $dayType = $this->calendar->dayType($branch, $date);
            $tariff = $this->tariffs->resolve($main, $date, $dayType);
            if ($tariff !== null) {
                $amount = Money::of($tariff->price)->roundTo(0);
                $roomFee = ['amount' => $amount->toString(), 'day_type' => $dayType->value, 'tariff_id' => (int) $tariff->id];
                if ($amount->isPositive()) {
                    $add('room_fee', 'Xona puli', self::money($amount) . ' (' . self::dayTypePrice($dayType) . ')');
                }
            }
        }

        // The minimum spend: the booking's own, else the room's — the order's own rule (DiningFeeCalculator)
        $minimum = $this->fees->minimumSpend($booking->min_spend, $main, $guests);
        if ($minimum !== null) {
            $add('min_spend', 'Minimal buyurtma', $minimum['per_person'] !== null
                ? self::money($minimum['per_person']) . ' kishi boshiga (jami ' . self::money($minimum['amount']) . ')'
                : self::money($minimum['amount']));
        }

        // The service charge and the place fee of the room's hall (else the branch's, the store's), in the hall channel
        $settings = $this->fees->settingsForHall((int) $booking->store_id, (int) $booking->branch_id, $main?->hall_id !== null ? (int) $main->hall_id : null);
        if ($settings !== null) {
            $announced = $this->fees->announced($settings, OrderChannel::POS_HALL, $guests);
            if (Money::of($announced['service_charge_percent'])->isPositive()) {
                $add('service_charge', 'Xizmat haqi', self::percent($announced['service_charge_percent']) . '%');
            }
            if ($announced['place_fee']->isPositive()) {
                $add('place_fee', 'Joy narxi', $settings->place_fee_mode === PlaceFeeMode::PER_TABLE
                    ? self::money($announced['place_fee'])
                    : self::money(Money::of($settings->place_fee_amount)->roundTo(0)) . ' kishi boshiga (jami ' . self::money($announced['place_fee']) . ')');
            }
        }

        $items = $booking->items()->orderBy('due_at')->orderBy('id')->get();
        if ($items->isNotEmpty()) {
            $add('pre_order', 'Oldindan buyurtma', $items->map(fn (BookingItem $item) => trim((string) $item->product_name)
                . ($item->variant_name ? ' (' . trim((string) $item->variant_name) . ')' : '')
                . ' ' . Quantity::of($item->quantity)->format() . ' ' . $item->unit->label())->implode(', '));
        }

        // Deposits taken, net of what was given back
        $deposit = BookingPayment::where('booking_id', $booking->id)->get()
            ->reduce(fn (Money $sum, BookingPayment $payment) => $sum->plus($payment->amount)->minus($payment->refunded_amount), Money::zero());
        if ($deposit->isPositive()) {
            $add('deposit', "Bo'nak olindi", self::money($deposit));
        }

        $policy = $this->settings($branch)['cancel_policy'];
        if ($policy !== null) {
            $add('cancel_policy', '', $policy);
        }

        $phone = trim((string) ($branch->phone ?: $store->phone));
        $add('contact', '', ($phone !== '' ? $phone . ' · ' : '') . 'Bron raqami: ' . self::shortCode($booking));

        return [
            'text' => self::render($lines, true),
            'lines' => $lines,
            'room_fee' => $roomFee,
            'channels' => [
                'telegram' => $this->telegramChat($booking) !== null,
                'sms' => $this->smsPhone($booking, $branch) !== null,
            ],
        ];
    }

    /**
     * Queues the confirmation over `$channel` (auto: Telegram, else SMS) and returns the channel used. 422 `no_channel`
     * when it cannot go that way; only a requested or confirmed booking (422 `booking_not_editable`).
     */
    public function send(Booking $booking, string $channel, User $actor, bool $auto = false): string
    {
        if (!in_array($booking->status, [BookingStatus::REQUESTED, BookingStatus::CONFIRMED], true)) {
            throw new BookingException("Bron \"{$booking->status->label()}\" holatida: tasdiq yuborilmaydi.", 'booking_not_editable');
        }

        $branch = Branch::findOrFail($booking->branch_id);
        $chat = in_array($channel, ['auto', 'telegram'], true) ? $this->telegramChat($booking) : null;
        $phone = $chat === null && in_array($channel, ['auto', 'sms'], true) ? $this->smsPhone($booking, $branch) : null;

        if ($chat === null && $phone === null) {
            throw new BookingException(match ($channel) {
                'telegram' => "Mehmonning Telegrami topilmadi: bu raqam bilan botga kirgan mijoz yo'q.",
                'sms' => $booking->contact_phone === null
                    ? "Mehmonning telefon raqami yo'q."
                    : "Bu filialda SMS yoqilmagan (POS sozlamalari: bron tasdig'i).",
                default => "Tasdiqni yuborib bo'lmaydi: mehmonning Telegrami yo'q, SMS esa yoqilmagan. Matnni nusxalab o'zingiz yuboring.",
            }, 'no_channel');
        }

        $used = $chat !== null ? 'telegram' : 'sms';
        $built = $this->build($booking);
        $text = $used === 'telegram' ? $built['text'] : self::render($built['lines'], false);

        $booking = DB::transaction(function () use ($booking, $used, $actor, $auto, $text, $chat, $phone) {
            $fresh = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $before = ['confirmation_sent_at' => $fresh->confirmation_sent_at?->toJSON(), 'confirmation_channel' => $fresh->confirmation_channel];

            // Not a change of the booking itself: its version stays, so no till holding it gets a 409
            $fresh->forceFill(['confirmation_sent_at' => now(), 'confirmation_channel' => $used])->save();

            $this->audit->record('booking.confirmation_sent', $fresh, $before, [
                'confirmation_sent_at' => $fresh->confirmation_sent_at?->toJSON(),
                'confirmation_channel' => $used,
            ], ['channel' => $used, 'auto' => $auto], ['actor' => $actor]);

            SendBookingConfirmation::dispatch((int) $fresh->id, $used, (string) ($chat ?? $phone), $text);

            return $fresh;
        });

        event(new BookingChangedBroadcast($booking));

        return $used;
    }

    /**
     * The booking just became confirmed: send the confirmation by itself when the branch wants it (auto_send). Never
     * fails the confirmation: no channel or any error is only logged.
     */
    public function autoSend(Booking $booking, User $actor): void
    {
        try {
            $branch = Branch::find($booking->branch_id);
            if ($branch === null || !$this->settings($branch)['auto_send']) {
                return;
            }

            $this->send($booking->refresh(), 'auto', $actor, true);
        } catch (BookingException $e) {
            Log::info("Booking {$booking->id}: the confirmation was not sent by itself ({$e->reason}).");
        } catch (Throwable $e) {
            Log::warning("Booking {$booking->id}: sending the confirmation by itself failed: {$e->getMessage()}");
        }
    }

    /**
     * The customer's Telegram chat for the contact phone: a customer of this store (or of no store yet) with that phone
     * and a real Telegram account, who has not switched Telegram off for order updates; null when there is none or the
     * bot is not set up. The store's own customer first, then one whose phone is verified (a phone shared from the
     * bot's contact button is not marked verified, so an unverified one is still used).
     */
    public function telegramChat(Booking $booking): ?string
    {
        $phone = UzPhone::normalize($booking->contact_phone);
        if ($phone === null || !$this->telegram->isConfigured()) {
            return null;
        }

        $customers = User::query()
            ->where('role', UserRole::CUSTOMER->value)
            ->whereIn('phone', [$phone, ltrim($phone, '+')])
            ->where(fn ($query) => $query->where('store_id', $booking->store_id)->orWhereNull('store_id'))
            ->whereNotNull('telegram_id')
            ->orderByRaw('store_id IS NULL')
            ->orderByRaw('phone_verified_at IS NULL')
            ->orderByDesc('id')
            ->get();

        foreach ($customers as $customer) {
            if ($customer->hasTelegram() && $this->preferences->allows($customer, NotificationType::ORDER_UPDATES, NotificationChannel::TELEGRAM)) {
                return (string) $customer->telegram_id;
            }
        }

        return null;
    }

    /**
     * The phone to text when the branch allows SMS for confirmations; null otherwise.
     */
    public function smsPhone(Booking $booking, Branch $branch): ?string
    {
        $phone = UzPhone::normalize($booking->contact_phone);

        return $phone !== null && $this->settings($branch)['sms'] ? $phone : null;
    }

    /**
     * The text from the lines: one line each, `label: value` (only the value when there is no label). With icons for
     * Telegram and the apps; without them for SMS (an emoji makes every SMS part shorter, so dearer).
     *
     * @param  list<array{kind: string, label: string, value: string}>  $lines
     */
    public static function render(array $lines, bool $icons): string
    {
        return implode("\n", array_map(function (array $line) use ($icons) {
            $body = $line['label'] !== '' ? $line['label'] . ': ' . $line['value'] : $line['value'];
            if (!$icons) {
                return $body;
            }
            if ($line['kind'] === 'header') {
                return $body . ' ' . match (true) {
                    str_contains($body, 'tasdiqlandi') => '✅',
                    str_contains($body, 'bekor') => '❌',
                    default => '📝',
                };
            }

            return isset(self::ICONS[$line['kind']]) ? self::ICONS[$line['kind']] . ' ' . $body : $body;
        }, $lines));
    }

    /**
     * The short number the guest quotes: the last six characters of the booking's uuid, upper case.
     */
    public static function shortCode(Booking $booking): string
    {
        return strtoupper(substr(str_replace('-', '', (string) $booking->uuid), -6));
    }

    /**
     * "300 000 so'm" (whole so'm).
     */
    public static function money(Money $amount): string
    {
        return number_format((float) $amount->roundTo(0)->toString(), 0, '.', ' ') . " so'm";
    }

    /**
     * @return array{auto_send: bool, sms: bool, cancel_policy: string|null}
     */
    private function settings(Branch $branch): array
    {
        $stored = $branch->getAttribute('pos_settings');
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return PosSettings::resolve(is_array($stored) ? $stored : null)['booking_confirmation'];
    }

    private function headline(Booking $booking): string
    {
        return match ($booking->status) {
            BookingStatus::REQUESTED => 'Bron qabul qilindi',
            BookingStatus::CANCELED, BookingStatus::NO_SHOW => 'Bron bekor qilindi',
            default => 'Bron tasdiqlandi',
        };
    }

    /**
     * @return list<int> the main room first, then the merged ones
     */
    private function tableIds(Booking $booking): array
    {
        $main = $booking->dining_table_id !== null ? (int) $booking->dining_table_id : null;
        $ids = $booking->tables()->orderBy('id')->pluck('dining_table_id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_unique(array_merge($main !== null ? [$main] : [], $ids)));
    }

    private static function dayTypePrice(DayType $type): string
    {
        return match ($type) {
            DayType::WEEKDAY => 'hafta ichi narxi',
            DayType::WEEKEND => 'dam olish kuni narxi',
            DayType::HOLIDAY => 'bayram kuni narxi',
        };
    }

    /**
     * "10.00" → "10", "12.50" → "12.5".
     */
    private static function percent(string $value): string
    {
        return rtrim(rtrim(Money::of($value)->toString(), '0'), '.');
    }
}
