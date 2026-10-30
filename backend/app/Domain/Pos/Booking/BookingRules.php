<?php

namespace App\Domain\Pos\Booking;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\DiningTableKind;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchBookingRules;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\StoreTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P6-26 (C6): a branch's table booking rules (branch_booking_rules, owner screen "Stol broni sozlamalari"):
 *
 *  - the slot a booking without an end holds, by the kind of the place (stol 90-240 min, default 120; a room or tapchan
 *    keeps the branch's `booking_slot_minutes` / the server's 5 hours unless a slot is set for it);
 *  - the least time ahead a booking may be made, per kind (choyxona rooms: "kamida 3 soat" = 180), and how many days
 *    ahead at most; a booking made too late is 422 `booking_too_soon`, too early `booking_too_far`; an owner or a
 *    manager may book a walk-in anyway (`skip_lead_time`);
 *  - an optional deposit for TABLE bookings (rooms and tapchans keep taking deposits by hand): none, one amount, or
 *    an amount per planned guest, from a party of N guests, and whether the booking waits for it to be confirmed
 *    (the existing deposit with its advance receipt, BookingDepositService, takes the money).
 *
 * The rules count only while the branch has the table_booking module and a row exists: a choyxona without either
 * books rooms exactly as before (no lead time, 5 hour default slot). Rules gate setup and new bookings only: a
 * booking already made, a payment, an arrival and a close never ask.
 */
final class BookingRules
{
    public const TABLE_SLOT_MIN = 30;

    public const TABLE_SLOT_MAX = 480;

    public const MAX_ADVANCE_MINUTES = 1440;

    public const MAX_ADVANCE_DAYS = 365;

    public const MAX_DEPOSIT = '99999999.99';

    public const VIEW = 'array{branch_id: int, configured: bool, table_slot_minutes: int, room_slot_minutes: int|null, tapchan_slot_minutes: int|null, table_min_advance_minutes: int, room_min_advance_minutes: int, tapchan_min_advance_minutes: int, max_advance_days: int|null, deposit_mode: string, deposit_mode_label: string, deposit_amount: string, deposit_from_guests: int, deposit_required_to_confirm: bool, updated_at: string|null}';

    public const FIELDS = [
        'table_slot_minutes', 'room_slot_minutes', 'tapchan_slot_minutes',
        'table_min_advance_minutes', 'room_min_advance_minutes', 'tapchan_min_advance_minutes', 'max_advance_days',
        'deposit_mode', 'deposit_amount', 'deposit_from_guests', 'deposit_required_to_confirm',
    ];

    /** @var array<int, string> table id => kind */
    private array $kinds = [];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PosModules $modules,
    ) {}

    /**
     * The row, only while the branch has the module (null: the choyxona's behaviour).
     */
    public function active(Branch $branch): ?BranchBookingRules
    {
        if (!$this->modules->enabled($branch, PosModule::TABLE_BOOKING)) {
            return null;
        }

        return $this->row($branch);
    }

    public function row(Branch $branch): ?BranchBookingRules
    {
        return BranchBookingRules::where('branch_id', $branch->id)->first();
    }

    /**
     * The rules as the owner screen and the till read them: every key, the stored row over the defaults.
     *
     * @return array<string, mixed>
     */
    public function view(Branch $branch): array
    {
        $row = $this->row($branch);
        $values = $row === null
            ? [
                'table_slot_minutes' => 120, 'room_slot_minutes' => null, 'tapchan_slot_minutes' => null,
                'table_min_advance_minutes' => 0, 'room_min_advance_minutes' => 0, 'tapchan_min_advance_minutes' => 0,
                'max_advance_days' => null, 'deposit_mode' => BookingDepositMode::OFF, 'deposit_amount' => '0',
                'deposit_from_guests' => 1, 'deposit_required_to_confirm' => false,
            ]
            : [
                'table_slot_minutes' => $row->table_slot_minutes, 'room_slot_minutes' => $row->room_slot_minutes,
                'tapchan_slot_minutes' => $row->tapchan_slot_minutes,
                'table_min_advance_minutes' => $row->table_min_advance_minutes, 'room_min_advance_minutes' => $row->room_min_advance_minutes,
                'tapchan_min_advance_minutes' => $row->tapchan_min_advance_minutes, 'max_advance_days' => $row->max_advance_days,
                'deposit_mode' => $row->deposit_mode, 'deposit_amount' => $row->deposit_amount,
                'deposit_from_guests' => $row->deposit_from_guests, 'deposit_required_to_confirm' => $row->deposit_required_to_confirm,
            ];
        $mode = $row === null ? BookingDepositMode::OFF : $row->deposit_mode;

        return [
            'branch_id' => (int) $branch->id,
            'configured' => $row !== null,
            'deposit_mode' => $mode->value,
            'deposit_mode_label' => $mode->label(),
            'deposit_amount' => Money::of($values['deposit_amount'])->toString(),
            'updated_at' => $row?->updated_at?->toJSON(),
        ] + $values;
    }

    /**
     * Saves the fields sent (audited); the others keep their value.
     *
     * @param  array<string, mixed>  $data  validated, only FIELDS
     * @return array<string, mixed> view()
     */
    public function update(Branch $branch, array $data, User $actor): array
    {
        $before = $this->view($branch);
        $attributes = array_intersect_key($data, array_flip(self::FIELDS));
        if (isset($attributes['deposit_amount'])) {
            $attributes['deposit_amount'] = Money::of($attributes['deposit_amount'])->toString();
        }

        $row = BranchBookingRules::where('branch_id', $branch->id)->first();
        if ($row === null) {
            $row = BranchBookingRules::create($attributes + ['store_id' => $branch->store_id, 'branch_id' => $branch->id]);
        } else {
            $row->update($attributes);
        }

        $after = $this->view($branch);
        $this->audit->record('settings.booking_rules_changed', $branch, $before, $after, [], ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id, 'actor' => $actor]);

        return $after;
    }

    /**
     * How long a booking without an end holds the main place of the kind: the rule's slot, else the branch's default.
     */
    public function slotMinutes(Branch $branch, ?string $kind): int
    {
        $rules = $this->active($branch);
        $slot = match ($kind) {
            DiningTableKind::TABLE->value => $rules?->table_slot_minutes,
            DiningTableKind::ROOM->value => $rules?->room_slot_minutes,
            DiningTableKind::TAPCHAN->value => $rules?->tapchan_slot_minutes,
            default => null,
        };

        return $slot ?? PosSettingsReader::slotMinutes($branch);
    }

    /**
     * The kind of a place of the branch ('table' when it is not found).
     */
    public function kindOf(?int $tableId): ?string
    {
        if ($tableId === null) {
            return null;
        }
        if (!isset($this->kinds[$tableId])) {
            $kind = DB::table('dining_tables')->where('id', $tableId)->value('kind');
            $this->kinds[$tableId] = is_string($kind) ? $kind : DiningTableKind::TABLE->value;
        }

        return $this->kinds[$tableId];
    }

    /**
     * Refuses a start that is sooner or later than the rules allow for the places booked.
     *
     * @param  list<int>  $tableIds  the places the booking holds
     * @param  bool  $skip  an owner or a manager books a walk-in anyway (not the furthest-ahead limit)
     *
     * @throws BookingException 422 `booking_too_soon`, `booking_too_far`
     */
    public function assertStart(Branch $branch, Carbon $start, array $tableIds, bool $skip = false): void
    {
        $rules = $this->active($branch);
        if ($rules === null) {
            return;
        }

        $lead = 0;
        foreach ($tableIds as $id) {
            $lead = max($lead, match ($this->kindOf($id)) {
                DiningTableKind::ROOM->value => $rules->room_min_advance_minutes,
                DiningTableKind::TAPCHAN->value => $rules->tapchan_min_advance_minutes,
                default => $rules->table_min_advance_minutes,
            });
        }

        $earliest = now()->addMinutes($lead);
        if (!$skip && $lead > 0 && $start->lessThan($earliest)) {
            $local = $earliest->copy()->timezone(StoreTimezone::of(Store::find($branch->store_id)))->format('d.m H:i');
            throw new BookingException(
                'Bron kamida ' . self::duration($lead) . " oldin qilinadi. Eng erta vaqt: {$local}.",
                'booking_too_soon',
                422,
                ['min_advance_minutes' => $lead, 'earliest_at' => $earliest->copy()->utc()->toJSON()],
            );
        }

        if ($rules->max_advance_days !== null && $start->greaterThan(now()->addDays($rules->max_advance_days))) {
            throw new BookingException(
                "Bron ko'pi bilan {$rules->max_advance_days} kun oldin qilinadi.",
                'booking_too_far',
                422,
                ['max_advance_days' => $rules->max_advance_days],
            );
        }
    }

    /**
     * The deposit the booking needs under the rules (0.00: none): table bookings only, from the party size the rule
     * names.
     */
    public function depositRequired(Branch $branch, Booking $booking): Money
    {
        $rules = $this->active($branch);
        if ($rules === null || $rules->deposit_mode === BookingDepositMode::OFF || !Money::of($rules->deposit_amount)->isPositive()) {
            return Money::zero();
        }
        if ($this->kindOf($booking->dining_table_id !== null ? (int) $booking->dining_table_id : null) !== DiningTableKind::TABLE->value) {
            return Money::zero();
        }
        $guests = max(1, (int) $booking->guests_planned);
        if ($guests < $rules->deposit_from_guests) {
            return Money::zero();
        }

        $amount = Money::of($rules->deposit_amount);

        return $rules->deposit_mode === BookingDepositMode::PER_GUEST ? Money::fromTiyin($amount->toTiyin() * $guests) : $amount;
    }

    /**
     * Whether a manager's rule keeps the booking unconfirmed until the deposit it needs is taken, and how much is
     * missing (0.00: confirm freely).
     */
    public function depositMissing(Branch $branch, Booking $booking, Money $taken): Money
    {
        $rules = $this->active($branch);
        if ($rules === null || !$rules->deposit_required_to_confirm) {
            return Money::zero();
        }
        $needed = $this->depositRequired($branch, $booking);

        return $needed->isGreaterThan($taken) ? $needed->minus($taken) : Money::zero();
    }

    public static function mayOverride(User $actor): bool
    {
        return in_array($actor->role, [UserRole::OWNER, UserRole::MANAGER, UserRole::SUPER_ADMIN], true);
    }

    private static function duration(int $minutes): string
    {
        if ($minutes >= 60 && $minutes % 60 === 0) {
            return intdiv($minutes, 60) . ' soat';
        }

        return $minutes >= 60 ? intdiv($minutes, 60) . ' soat ' . ($minutes % 60) . ' daqiqa' : "{$minutes} daqiqa";
    }
}
