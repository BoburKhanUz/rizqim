<?php

namespace App\Domain\Pos\Booking;

use App\Events\Broadcast\OrderBroadcast;
use App\Models\Booking;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * booking.changed (P3-17): a booking was created, changed, moved to another state or got a deposit. Sent to the
 * branch's floor channel (pos-branch.{id}: terminals and staff of the branch). Ids, status and times only: clients
 * refetch GET /pos/bookings/{uuid}. Queued on `broadcasts`, after commit, tried once and rescued, like OrderBroadcast.
 */
class BookingChangedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    public int $tries = 1;

    public string $uuid;

    public string $status;

    public int $branchId;

    public int $version;

    public string $startsAt;

    public string $endsAt;

    public function __construct(Booking $booking)
    {
        $this->uuid = (string) $booking->uuid;
        $this->status = $booking->status->value;
        $this->branchId = (int) $booking->branch_id;
        $this->version = (int) $booking->version;
        $this->startsAt = $booking->starts_at->toJSON();
        $this->endsAt = $booking->ends_at->toJSON();
    }

    public function broadcastQueue(): string
    {
        return OrderBroadcast::QUEUE;
    }

    public function broadcastAs(): string
    {
        return 'booking.changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("pos-branch.{$this->branchId}")];
    }

    /**
     * @return array{booking: array{uuid: string, status: string, branch_id: int, version: int, starts_at: string, ends_at: string}}
     */
    public function broadcastWith(): array
    {
        return [
            'booking' => [
                'uuid' => $this->uuid,
                'status' => $this->status,
                'branch_id' => $this->branchId,
                'version' => $this->version,
                'starts_at' => $this->startsAt,
                'ends_at' => $this->endsAt,
            ],
        ];
    }
}
