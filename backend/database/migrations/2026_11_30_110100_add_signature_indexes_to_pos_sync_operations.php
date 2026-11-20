<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wave M5 S3-d: two readers of `pos_sync_operations.sig_status` (docs/scale/M5_DESIGN.md §4):
 *  - (received_at) over the `bad` and `chain_gap` rows only: the monitoring rule `pos_sync_signature` reads the last
 *    day's problems every minute (PosSyncSignatureCheck), and a batch with a `chain_gap` asks whether the owner was
 *    told about this till in the last hour (SignaturePolicy). A handful of rows: it costs a write only when a
 *    signature fails;
 *  - (device_id, sig_status, received_at) over the rows that carry a status: the control centre's device rows count a
 *    till's last week by status (PosDevices), answered from the index alone.
 * Without them both would scan every operation of the till (the unique (device_id, seq) index) or the whole table.
 *
 * Built CONCURRENTLY (outside a transaction), like the other index migrations here; an INVALID leftover of an
 * interrupted build is rebuilt, so the migration can be run again.
 */
return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    /** @var array<string, string> */
    private const INDEXES = [
        'pos_sync_operations_sig_problem_index' => "(received_at) WHERE sig_status IN ('bad', 'chain_gap')",
        'pos_sync_operations_sig_device_index' => '(device_id, sig_status, received_at) WHERE sig_status IS NOT NULL',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $definition) {
            if (DB::getDriverName() !== 'pgsql') {
                DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON pos_sync_operations {$definition}");

                continue;
            }
            if (ConcurrentIndex::isInvalid($name)) {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
            }
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} ON pos_sync_operations {$definition}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement(DB::getDriverName() === 'pgsql' ? "DROP INDEX CONCURRENTLY IF EXISTS {$name}" : "DROP INDEX IF EXISTS {$name}");
        }
    }
};
