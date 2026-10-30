<?php

namespace App\Domain\Pos\StaffPay;

use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\Branch;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\StaffWorkSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P6-23: work time (docs/POS_SPEC.md "Ish haqi qoidalari", the minimal clock-in/out). Rizqim had none: a PIN session
 * ends whenever somebody else signs in on the same terminal, so it says nothing about who is at work. Now:
 *
 *  - a staff member clocks in and out from their own PIN session on a paired terminal of the branch (POST
 *    /pos/clock/in, /pos/clock/out); the session belongs to the business day of its start; one open session per
 *    person; clocking in while a session of an earlier business day is still open sets that one aside
 *    (`abandoned_at`: not counted until a manager gives it an end);
 *  - a manager adds, corrects or removes sessions in the panel with a reason (audited); a session is at most 24 hours
 *    and never overlaps another of the same person;
 *  - the time a cashier's till shift was open (opened by them) counts as their work time too, merged with their
 *    sessions so an overlap is counted once.
 */
final class WorkTime
{
    public const MAX_MINUTES = 24 * 60;

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function current(User $user): ?StaffWorkSession
    {
        return StaffWorkSession::query()->where('user_id', $user->id)->whereNull('clock_out_at')->whereNull('abandoned_at')->first();
    }

    /**
     * @throws PosActionException 409 already_clocked_in, 422 terminal_without_branch
     */
    public function clockIn(User $user, Device $device, ?Closure $applied = null): StaffWorkSession
    {
        $branch = $device->branch_id !== null ? Branch::find($device->branch_id) : null;
        if ($branch === null) {
            throw PosActionException::invalid('terminal_without_branch', 'Ish vaqti filialga ulangan terminalda belgilanadi.');
        }
        $today = BusinessDay::of($branch);

        try {
            return DB::transaction(function () use ($user, $device, $branch, $today, $applied) {
                $open = StaffWorkSession::query()->where('user_id', $user->id)->whereNull('clock_out_at')->whereNull('abandoned_at')->lockForUpdate()->first();
                if ($open !== null) {
                    if ($open->business_date->toDateString() >= $today) {
                        throw PosActionException::conflict('already_clocked_in', 'Siz allaqachon ishdasiz (kelgan vaqt belgilangan).', ['session_uuid' => $open->uuid]);
                    }
                    // Forgot to clock out on an earlier day: set aside for a manager, not counted
                    $open->forceFill(['abandoned_at' => now()])->save();
                    $this->audit->record('staff_pay.session_abandoned', $open, [], [], ['staff_name' => $user->name], ['actor' => $user, 'device' => $device]);
                }

                $session = StaffWorkSession::create([
                    'store_id' => $branch->store_id,
                    'branch_id' => $branch->id,
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'business_date' => $today,
                    'clock_in_at' => now(),
                    'source' => WorkSessionSource::PIN->value,
                ]);
                $this->audit->record('staff_pay.clock_in', $session, [], [], ['staff_name' => $user->name, 'business_date' => $today], ['actor' => $user, 'device' => $device]);

                if ($applied) {
                    $applied();
                }

                return $session;
            });
        } catch (UniqueConstraintViolationException) {
            throw PosActionException::conflict('already_clocked_in', 'Siz allaqachon ishdasiz (kelgan vaqt belgilangan).');
        }
    }

    /**
     * @throws PosActionException 409 not_clocked_in
     */
    public function clockOut(User $user, ?Device $device, ?Closure $applied = null): StaffWorkSession
    {
        return DB::transaction(function () use ($user, $device, $applied) {
            $open = StaffWorkSession::query()->where('user_id', $user->id)->whereNull('clock_out_at')->whereNull('abandoned_at')->lockForUpdate()->first();
            if ($open === null) {
                throw PosActionException::conflict('not_clocked_in', 'Siz hali ishga kelganingizni belgilamagansiz.');
            }
            $end = now();
            if ($end->getTimestamp() - $open->clock_in_at->getTimestamp() > self::MAX_MINUTES * 60) {
                // Longer than a day: kept for a manager to give the real end, as a forgotten one
                $open->forceFill(['abandoned_at' => $end])->save();
            } else {
                $open->forceFill(['clock_out_at' => $end])->save();
            }
            $this->audit->record('staff_pay.clock_out', $open, [], [], ['staff_name' => $user->name, 'minutes' => $open->minutes()], ['actor' => $user, 'device' => $device]);

            if ($applied) {
                $applied();
            }

            return $open;
        });
    }

    /**
     * A manager's session for a person (source manual), with the reason.
     *
     * @throws ValidationException
     */
    public function add(Branch $branch, User $person, Carbon $in, ?Carbon $out, string $reason, User $actor): StaffWorkSession
    {
        // Stored as UTC moments (a time given with +05:00 would otherwise be written as its local digits)
        [$in, $out] = [$in->copy()->utc(), $out?->copy()->utc()];
        $this->assertTimes($person, $in, $out, null);

        $session = StaffWorkSession::create([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'user_id' => $person->id,
            'business_date' => BusinessDay::of($branch, $in),
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'source' => WorkSessionSource::MANUAL->value,
            'note' => $reason,
            'edited_by' => $actor->id,
        ]);
        $this->audit->record('staff_pay.session_added', $session, [], self::times($session), ['staff_name' => $person->name, 'reason' => $reason], ['actor' => $actor]);

        return $session;
    }

    /**
     * @throws ValidationException
     */
    public function correct(StaffWorkSession $session, Branch $branch, Carbon $in, ?Carbon $out, string $reason, User $actor): StaffWorkSession
    {
        [$in, $out] = [$in->copy()->utc(), $out?->copy()->utc()];
        $person = User::findOrFail($session->user_id);
        $this->assertTimes($person, $in, $out, $session);
        $before = self::times($session);

        $session->forceFill([
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'abandoned_at' => $out === null ? $session->abandoned_at : null,
            'business_date' => BusinessDay::of($branch, $in),
            'note' => $reason,
            'edited_by' => $actor->id,
        ]);
        try {
            DB::transaction(fn () => $session->save());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['clock_out_at' => 'Xodimning boshqa ochiq ish vaqti bor: avval uni yoping.']);
        }
        $this->audit->record('staff_pay.session_corrected', $session, $before, self::times($session), ['staff_name' => $person->name, 'reason' => $reason], ['actor' => $actor]);

        return $session;
    }

    public function remove(StaffWorkSession $session, string $reason, User $actor): void
    {
        $this->audit->record('staff_pay.session_removed', $session, self::times($session), [], ['reason' => $reason, 'staff_name' => User::whereKey($session->user_id)->value('name')], ['actor' => $actor]);
        $session->delete();
    }

    /**
     * Minutes of work per person and business day in the branch: closed sessions and the closed till shifts each person
     * opened, merged (an overlap counted once), each piece at most 24 hours. Also how many sessions were left open or
     * set aside (not counted).
     *
     * @return array{minutes: array<int, array<string, int>>, days: array<int, array<string, true>>, uncounted: array<int, int>}
     */
    public function byDay(Branch $branch, string $from, string $to): array
    {
        $intervals = [];
        $days = [];
        $uncounted = [];

        $sessions = StaffWorkSession::query()->where('branch_id', $branch->id)->whereBetween('business_date', [$from, $to])->get();
        foreach ($sessions as $session) {
            $date = $session->business_date->toDateString();
            $days[(int) $session->user_id][$date] = true;
            if ($session->clock_out_at === null) {
                $uncounted[(int) $session->user_id] = ($uncounted[(int) $session->user_id] ?? 0) + 1;

                continue;
            }
            $intervals[(int) $session->user_id][$date][] = [$session->clock_in_at->getTimestamp(), min($session->clock_out_at->getTimestamp(), $session->clock_in_at->getTimestamp() + self::MAX_MINUTES * 60)];
        }

        $shifts = PosShift::query()->where('branch_id', $branch->id)->whereBetween('business_date', [$from, $to])->whereNotNull('opened_by')->get(['opened_by', 'business_date', 'opened_at', 'closed_at']);
        foreach ($shifts as $shift) {
            $userId = (int) $shift->getAttribute('opened_by');
            $date = $shift->business_date->toDateString();
            $days[$userId][$date] = true;
            if ($shift->closed_at !== null) {
                $intervals[$userId][$date][] = [$shift->opened_at->getTimestamp(), min($shift->closed_at->getTimestamp(), $shift->opened_at->getTimestamp() + self::MAX_MINUTES * 60)];
            }
        }

        $minutes = [];
        foreach ($intervals as $userId => $byDate) {
            foreach ($byDate as $date => $list) {
                $minutes[$userId][$date] = intdiv(self::merged($list), 60);
            }
        }

        return ['minutes' => $minutes, 'days' => $days, 'uncounted' => $uncounted];
    }

    /**
     * Seconds covered by the intervals, overlaps once.
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    private static function merged(array $intervals): int
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $total = 0;
        $end = null;
        $start = null;
        foreach ($intervals as [$s, $e]) {
            if ($e <= $s) {
                continue;
            }
            if ($end === null || $s > $end) {
                $total += $end !== null ? $end - $start : 0;
                [$start, $end] = [$s, $e];
            } else {
                $end = max($end, $e);
            }
        }

        return $total + ($end !== null ? $end - $start : 0);
    }

    /**
     * @throws ValidationException
     */
    private function assertTimes(User $person, Carbon $in, ?Carbon $out, ?StaffWorkSession $self): void
    {
        if ($in->isFuture()) {
            throw ValidationException::withMessages(['clock_in_at' => "Kelgan vaqt kelajakda bo'lishi mumkin emas."]);
        }
        if ($out !== null && ($out->lessThanOrEqualTo($in) || $out->isFuture())) {
            throw ValidationException::withMessages(['clock_out_at' => "Ketgan vaqt kelgan vaqtdan keyin va hozirdan oldin bo'lishi kerak."]);
        }
        if ($out !== null && $out->getTimestamp() - $in->getTimestamp() > self::MAX_MINUTES * 60) {
            throw ValidationException::withMessages(['clock_out_at' => "Bitta ish vaqti 24 soatdan oshmaydi."]);
        }

        $overlaps = StaffWorkSession::query()
            ->where('user_id', $person->id)
            ->when($self !== null, fn ($query) => $query->whereKeyNot($self->id))
            ->where('clock_in_at', '<', $out ?? now()->addYears(10))
            ->where(fn ($query) => $query->where('clock_out_at', '>', $in)->orWhere(fn ($open) => $open->whereNull('clock_out_at')->whereNull('abandoned_at')))
            ->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['clock_in_at' => "Bu vaqt xodimning boshqa ish vaqti bilan ustma-ust tushadi."]);
        }
    }

    /**
     * @return array{clock_in_at: string, clock_out_at: string|null, business_date: string}
     */
    private static function times(StaffWorkSession $session): array
    {
        return [
            'clock_in_at' => $session->clock_in_at->toIso8601String(),
            'clock_out_at' => $session->clock_out_at?->toIso8601String(),
            'business_date' => $session->business_date->toDateString(),
        ];
    }
}
