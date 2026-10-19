<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why (App\Enums\CancelReason), with an optional note, and by whom an order was cancelled or rejected; the
     * system (unpaid online order expiry) leaves cancelled_by null. Older cancellations keep all three null and are
     * not backfilled: without a no_show reason they no longer count towards the cash pickup ban.
     */
    public function up(): void
    {
        $columns = [
            'cancel_reason' => fn (Blueprint $table) => $table->string('cancel_reason', 32)->nullable(),
            'cancel_note' => fn (Blueprint $table) => $table->string('cancel_note', 500)->nullable(),
            'cancelled_by' => fn (Blueprint $table) => $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete(),
        ];

        foreach ($columns as $column => $add) {
            if (!Schema::hasColumn('orders', $column)) {
                Schema::table('orders', $add);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'cancelled_by')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cancelled_by');
            });
        }

        foreach (['cancel_reason', 'cancel_note'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
