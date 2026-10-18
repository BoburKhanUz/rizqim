<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges order_status_logs into order_status_histories, the table every reader uses. Transitions used to be written
 * to both, so most log rows have a history twin: same order, status and user. Within such a group logs and histories
 * pair up in time order; a twin takes the log's from_status, and a log without one is copied as a history row that
 * keeps the log's id in order_status_log_id. Re-running copies nothing twice; down() deletes only those copies.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_status_histories', 'order_status_log_id')) {
            Schema::table('order_status_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('order_status_log_id')->nullable();
            });
        }

        if (!Schema::hasTable('order_status_logs')) {
            return;
        }

        DB::table('order_status_logs')->distinct()->orderBy('order_id')->pluck('order_id')
            ->chunk(1000)
            ->each(fn (Collection $orderIds) => $this->merge($orderIds->values()->all()));
    }

    public function down(): void
    {
        if (!Schema::hasColumn('order_status_histories', 'order_status_log_id')) {
            return;
        }

        DB::table('order_status_histories')->whereNotNull('order_status_log_id')->delete();

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropColumn('order_status_log_id');
        });
    }

    private function merge(array $orderIds): void
    {
        $group = fn (object $row, string $status) => "{$row->order_id}|{$status}|{$row->user_id}";

        $histories = DB::table('order_status_histories')
            ->whereIn('order_id', $orderIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'order_id', 'user_id', 'status', 'from_status', 'order_status_log_id']);

        $copied = $histories->pluck('order_status_log_id')->filter()->flip();
        $twins = $histories->whereNull('order_status_log_id')->groupBy(fn ($history) => $group($history, $history->status));

        $logs = DB::table('order_status_logs')
            ->whereIn('order_id', $orderIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->reject(fn ($log) => $copied->has($log->id))
            ->groupBy(fn ($log) => $group($log, $log->to_status));

        $copies = [];
        foreach ($logs as $key => $groupLogs) {
            $groupTwins = $twins->get($key, collect())->values();

            foreach ($groupLogs->values() as $rank => $log) {
                $twin = $groupTwins->get($rank);

                if (!$twin) {
                    $copies[] = [
                        'order_id' => $log->order_id,
                        'user_id' => $log->user_id,
                        'from_status' => $log->from_status,
                        'status' => $log->to_status,
                        'notes' => $log->comment,
                        'created_at' => $log->created_at,
                        'updated_at' => $log->created_at,
                        'order_status_log_id' => $log->id,
                    ];
                } elseif ($twin->from_status === null && $log->from_status !== null) {
                    DB::table('order_status_histories')->where('id', $twin->id)->update(['from_status' => $log->from_status]);
                }
            }
        }

        foreach (array_chunk($copies, 500) as $rows) {
            DB::table('order_status_histories')->insert($rows);
        }
    }
};
