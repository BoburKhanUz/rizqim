<?php

namespace App\Domain\Pos\Waitlist;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\PosWaitlistEntry;
use App\Services\Audit\AuditRedactor;

/**
 * A waitlist entry as the hostess' screen and the owner's view read it (docs/API.md "Kutish ro'yxati"). The hostess
 * works with the guest's number (`guest_phone`, she calls them); the owner's view (`$masked`) gets it masked
 * (+99890***67). Times are UTC ISO 8601.
 */
class WaitlistPresenter
{
    public const ENTRY = 'array{uuid: string, branch_id: int, business_date: string, guest_name: string, guest_phone: string|null, guest_phone_masked: string|null, party_size: int, quoted_wait_minutes: int|null, note: string|null, status: string, status_label: string, position: int|null, waited_minutes: int, dining_table_id: int|null, table_name: string|null, order_uuid: string|null, notified_at: string|null, seated_at: string|null, closed_at: string|null, version: int, created_at: string|null}';

    public const SUMMARY = 'array{total: int, waiting: int, seated: int, canceled: int, no_show: int, party_size_total: int, avg_quoted_wait_minutes: int|null, avg_seated_wait_minutes: int|null, longest_waiting_minutes: int|null}';

    /**
     * @param  int|null  $position  1-based place in the queue of waiting entries (null when not waiting)
     * @return array<string, mixed>
     */
    public function entry(PosWaitlistEntry $entry, ?int $position = null, bool $masked = false): array
    {
        $end = $entry->closed_at ?? now();

        return [
            'uuid' => (string) $entry->uuid,
            'branch_id' => (int) $entry->branch_id,
            'business_date' => $entry->business_date->format('Y-m-d'),
            'guest_name' => (string) $entry->guest_name,
            'guest_phone' => $masked ? null : $entry->guest_phone,
            'guest_phone_masked' => AuditRedactor::maskPhone($entry->guest_phone),
            'party_size' => (int) $entry->party_size,
            'quoted_wait_minutes' => $entry->quoted_wait_minutes,
            'note' => $entry->note,
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
            'position' => $entry->status->isOpen() ? $position : null,
            'waited_minutes' => max(0, (int) $entry->created_at->diffInMinutes($end)),
            'dining_table_id' => $entry->dining_table_id !== null ? (int) $entry->dining_table_id : null,
            'table_name' => $entry->dining_table_id !== null ? DiningTable::withTrashed()->whereKey($entry->dining_table_id)->value('name') : null,
            'order_uuid' => $entry->order_id !== null ? Order::withTrashed()->whereKey($entry->order_id)->value('uuid') : null,
            'notified_at' => $entry->notified_at?->toJSON(),
            'seated_at' => $entry->seated_at?->toJSON(),
            'closed_at' => $entry->closed_at?->toJSON(),
            'version' => (int) $entry->version,
            'created_at' => $entry->created_at?->toJSON(),
        ];
    }

    /**
     * Entries with their queue positions (waiting ones, in the order they came).
     *
     * @param  iterable<PosWaitlistEntry>  $entries  in the order they came
     * @return list<array<string, mixed>>
     */
    public function entries(iterable $entries, bool $masked = false): array
    {
        $out = [];
        $position = 0;
        foreach ($entries as $entry) {
            $out[] = $this->entry($entry, $entry->status->isOpen() ? ++$position : null, $masked);
        }

        return $out;
    }

    /**
     * Counts and waits of a set of entries: how many are in each state, the guests, the average wait the hostess
     * quoted and the average wait of the parties that were seated (minutes from writing down to seating), and the
     * longest wait of those still waiting.
     *
     * @param  iterable<PosWaitlistEntry>  $entries
     * @return array<string, int|null>
     */
    public function summary(iterable $entries): array
    {
        $counts = ['waiting' => 0, 'seated' => 0, 'canceled' => 0, 'no_show' => 0];
        $guests = 0;
        $total = 0;
        $quoted = [];
        $seated = [];
        $longest = null;
        foreach ($entries as $entry) {
            $total++;
            $guests += (int) $entry->party_size;
            $counts[$entry->status->value]++;
            if ($entry->quoted_wait_minutes !== null) {
                $quoted[] = (int) $entry->quoted_wait_minutes;
            }
            if ($entry->seated_at !== null) {
                $seated[] = max(0, (int) $entry->created_at->diffInMinutes($entry->seated_at));
            }
            if ($entry->status->isOpen()) {
                $longest = max($longest ?? 0, max(0, (int) $entry->created_at->diffInMinutes(now())));
            }
        }

        return ['total' => $total] + $counts + [
            'party_size_total' => $guests,
            'avg_quoted_wait_minutes' => $quoted === [] ? null : (int) round(array_sum($quoted) / count($quoted)),
            'avg_seated_wait_minutes' => $seated === [] ? null : (int) round(array_sum($seated) / count($seated)),
            'longest_waiting_minutes' => $longest,
        ];
    }
}
