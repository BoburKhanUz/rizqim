<?php

namespace App\Domain\Pos\Waitlist;

use App\Domain\Pos\Booking\UzPhone;
use App\Domain\Pos\Contracts\PosOrderOpener;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\PosWaitlistEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * P6-24 (C5): the hostess' waitlist ("kutish ro'yxati"). A guest without a booking is written down with the party size
 * and the wait the hostess quoted; the entry is called (notified), then seated at a table (optionally opening the
 * order for the party), or closed as cancelled / no-show. Every write is audited (the phone is masked there) and
 * announced on the branch's floor channel; `version` makes a stale write a 409. Entries belong to the branch's
 * business day. Nothing here blocks an order, a payment or a shift: it is the hostess' notebook.
 */
class WaitlistService
{
    public const MAX_PARTY = 200;

    public const MAX_WAIT_MINUTES = 600;

    public function __construct(
        protected AuditLogger $audit,
        protected PosOrderOpener $orders,
    ) {}

    /**
     * @param  array{guest_name: string, guest_phone?: string|null, party_size: int, quoted_wait_minutes?: int|null, note?: string|null}  $data
     */
    public function create(Branch $branch, array $data, User $actor): PosWaitlistEntry
    {
        $entry = DB::transaction(function () use ($branch, $data, $actor) {
            $entry = PosWaitlistEntry::create([
                'store_id' => $branch->store_id,
                'branch_id' => $branch->id,
                'business_date' => BusinessDay::of($branch),
                'guest_name' => trim($data['guest_name']),
                'guest_phone' => $this->phone($data['guest_phone'] ?? null),
                'party_size' => $data['party_size'],
                'quoted_wait_minutes' => $data['quoted_wait_minutes'] ?? null,
                'note' => $this->note($data['note'] ?? null),
                'status' => WaitlistStatus::WAITING,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->audit->record('waitlist.created', $entry, [], $this->fields($entry), [], ['actor' => $actor]);

            return $entry;
        });

        $this->changed($entry);

        return $entry;
    }

    /**
     * Changes a waiting entry: only the keys sent change.
     *
     * @param  array<string, mixed>  $data  guest_name, guest_phone, party_size, quoted_wait_minutes, note
     */
    public function update(PosWaitlistEntry $entry, array $data, User $actor, ?int $version = null): PosWaitlistEntry
    {
        $entry = DB::transaction(function () use ($entry, $data, $actor, $version) {
            $entry = $this->lock($entry, $version);
            $this->assertWaiting($entry);

            $before = $this->fields($entry);
            foreach (['guest_name', 'party_size', 'quoted_wait_minutes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $entry->{$field} = $field === 'guest_name' ? trim((string) $data[$field]) : ($data[$field] !== null ? (int) $data[$field] : null);
                }
            }
            if (array_key_exists('guest_phone', $data)) {
                $entry->guest_phone = $this->phone($data['guest_phone']);
            }
            if (array_key_exists('note', $data)) {
                $entry->note = $this->note($data['note']);
            }
            $this->bump($entry, $actor);
            $this->audit->record('waitlist.updated', $entry, $before, $this->fields($entry), [], ['actor' => $actor]);

            return $entry;
        });

        $this->changed($entry);

        return $entry;
    }

    /**
     * The guest was called ("stol tayyor"): stamps `notified_at` (calling again moves it).
     */
    public function notify(PosWaitlistEntry $entry, User $actor, ?int $version = null): PosWaitlistEntry
    {
        $entry = DB::transaction(function () use ($entry, $actor, $version) {
            $entry = $this->lock($entry, $version);
            $this->assertWaiting($entry);

            $entry->notified_at = now();
            $this->bump($entry, $actor);
            $this->audit->record('waitlist.notified', $entry, [], [], [], ['actor' => $actor]);

            return $entry;
        });

        $this->changed($entry);

        return $entry;
    }

    /**
     * Seats the party at a table of the branch. With `$openOrder` the order of the party is opened there (a hall order,
     * `party_size` guests, `$waiterId` the waiter or the opener) and linked to the entry.
     */
    public function seat(PosWaitlistEntry $entry, int $tableId, User $actor, bool $openOrder = false, ?int $waiterId = null, ?int $deviceId = null, ?int $version = null): PosWaitlistEntry
    {
        $entry = DB::transaction(function () use ($entry, $tableId, $actor, $openOrder, $waiterId, $deviceId, $version) {
            $entry = $this->lock($entry, $version);
            $this->assertWaiting($entry);

            $table = DiningTable::where('branch_id', $entry->branch_id)->where('is_active', true)->find($tableId);
            if ($table === null) {
                throw new WaitlistException('Stol topilmadi yoki ishlamayapti.', 'table_not_found', 422);
            }

            $order = null;
            if ($openOrder) {
                $order = $this->orders->open((int) $entry->branch_id, 'pos_hall', (int) $table->id, (int) $entry->party_size, $waiterId, $deviceId, (int) $actor->id);
            }

            $entry->status = WaitlistStatus::SEATED;
            $entry->dining_table_id = $table->id;
            $entry->order_id = $order instanceof Order ? $order->id : null;
            $entry->seated_at = now();
            $entry->closed_at = now();
            $this->bump($entry, $actor);
            $this->audit->record('waitlist.seated', $entry, [], [], array_filter([
                'dining_table_id' => (int) $table->id,
                'order_uuid' => $order instanceof Order ? $order->uuid : null,
                'waited_minutes' => (int) $entry->created_at->diffInMinutes($entry->seated_at),
                'quoted_wait_minutes' => $entry->quoted_wait_minutes,
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            return $entry;
        });

        $this->changed($entry);

        return $entry;
    }

    public function cancel(PosWaitlistEntry $entry, ?string $reason, User $actor, ?int $version = null): PosWaitlistEntry
    {
        return $this->close($entry, WaitlistStatus::CANCELED, 'waitlist.canceled', $reason, $actor, $version);
    }

    public function noShow(PosWaitlistEntry $entry, User $actor, ?int $version = null): PosWaitlistEntry
    {
        return $this->close($entry, WaitlistStatus::NO_SHOW, 'waitlist.no_show', null, $actor, $version);
    }

    /**
     * The branch's entries of a business day (default today's) in the order they came, optionally of one status.
     *
     * @return Builder<PosWaitlistEntry>
     */
    public function query(Branch $branch, ?string $date = null, ?WaitlistStatus $status = null): Builder
    {
        return PosWaitlistEntry::query()
            ->where('branch_id', $branch->id)
            ->where('business_date', $date ?? BusinessDay::of($branch))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status?->value))
            ->orderBy('created_at')
            ->orderBy('id');
    }

    private function close(PosWaitlistEntry $entry, WaitlistStatus $status, string $action, ?string $reason, User $actor, ?int $version): PosWaitlistEntry
    {
        $entry = DB::transaction(function () use ($entry, $status, $action, $reason, $actor, $version) {
            $entry = $this->lock($entry, $version);
            $this->assertWaiting($entry);

            $entry->status = $status;
            $entry->closed_at = now();
            $this->bump($entry, $actor);
            $this->audit->record($action, $entry, [], [], array_filter(['reason' => $reason !== null ? mb_substr(trim($reason), 0, 255) : null]), ['actor' => $actor]);

            return $entry;
        });

        $this->changed($entry);

        return $entry;
    }

    private function lock(PosWaitlistEntry $entry, ?int $version): PosWaitlistEntry
    {
        $fresh = PosWaitlistEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
        if ($version !== null && $version !== (int) $fresh->version) {
            throw new WaitlistException("Yozuv boshqa qurilmada o'zgartirilgan. Yangilab, qayta urinib ko'ring.", 'stale_version', 409, ['version' => (int) $fresh->version]);
        }

        return $fresh;
    }

    private function assertWaiting(PosWaitlistEntry $entry): void
    {
        if (!$entry->status->isOpen()) {
            throw new WaitlistException("Bu yozuv \"{$entry->status->label()}\" holatida: uni o'zgartirib bo'lmaydi.", 'entry_closed', 409, ['status' => $entry->status->value]);
        }
    }

    private function bump(PosWaitlistEntry $entry, User $actor): void
    {
        $entry->updated_by = $actor->id;
        $entry->version = (int) $entry->version + 1;
        $entry->save();
    }

    private function phone(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $phone = UzPhone::normalize((string) $value);
        if ($phone === null) {
            throw new WaitlistException("Telefon raqami noto'g'ri: +998XXXXXXXXX yoki 9 xonali raqam.", 'phone_invalid', 422);
        }

        return $phone;
    }

    private function note(mixed $value): ?string
    {
        return $value !== null && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, 500) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(PosWaitlistEntry $entry): array
    {
        return [
            'status' => $entry->status->value,
            'guest_name' => $entry->guest_name,
            'guest_phone' => $entry->guest_phone,
            'party_size' => (int) $entry->party_size,
            'quoted_wait_minutes' => $entry->quoted_wait_minutes,
            'note' => $entry->note,
        ];
    }

    private function changed(PosWaitlistEntry $entry): void
    {
        event(new WaitlistChangedBroadcast($entry));
    }
}
