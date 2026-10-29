<?php

namespace App\Domain\Pos\Hall;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P6-26 (C5): the hostess' manual state of a place on the floor map. Only one is kept: `cleaning` ("tozalanmoqda":
 * the guests left, the table is not ready). It shows (FloorState `service_state`) while the place has no open order and
 * no order has been opened there since it was marked, so it never outlives the next guests and never blocks seating
 * or an order. Marking a place free clears it. Each change is audited and announced on the branch's floor channel.
 */
class TableStates
{
    public const CLEANING = 'cleaning';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Marks the place `cleaning`, or clears the mark (`$state` null).
     */
    public function set(DiningTable $table, ?string $state, User $actor): DiningTable
    {
        $table = DB::transaction(function () use ($table, $state, $actor) {
            $table = DiningTable::whereKey($table->id)->lockForUpdate()->firstOrFail();
            $before = $table->service_state;
            $table->forceFill([
                'service_state' => $state,
                'service_state_at' => $state !== null ? now() : null,
                'service_state_by' => $state !== null ? $actor->id : null,
            ])->save();

            // Marking it again (a stale mark from earlier guests) renews the mark, so that is logged too
            $this->audit->record('table.service_state_changed', $table, ['service_state' => $before], ['service_state' => $state], ['renewed' => $before === $state && $state !== null], ['actor' => $actor]);

            return $table;
        });

        event(new TableStateChangedBroadcast($table));

        return $table;
    }

    /**
     * The places (by id) that are marked `cleaning` and still count: no order was created there since the mark.
     *
     * @param  Collection<int, DiningTable>  $tables
     * @return array<int, CarbonInterface>
     */
    public static function cleaning(Collection $tables): array
    {
        $marked = $tables->filter(fn (DiningTable $table) => $table->service_state === self::CLEANING && $table->service_state_at !== null);
        if ($marked->isEmpty()) {
            return [];
        }

        $reused = Order::query()
            ->whereIn('table_id', $marked->pluck('id'))
            ->groupBy('table_id')
            ->selectRaw('table_id, max(created_at) as latest')
            ->pluck('latest', 'table_id');

        $result = [];
        foreach ($marked as $table) {
            $latest = $reused->get($table->id);
            if ($latest === null || Carbon::parse($latest)->lessThanOrEqualTo($table->service_state_at)) {
                $result[(int) $table->id] = $table->service_state_at;
            }
        }

        return $result;
    }
}
