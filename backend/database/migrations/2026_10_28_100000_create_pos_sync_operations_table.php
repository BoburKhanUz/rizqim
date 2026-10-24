<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-31: the journal of operations a till sent from its offline queue (POST /pos/sync, docs/POS_SPEC.md §5): one row
 * per operation, unique by the device's `op_id` (a repeat is answered from here, never applied twice), in the device's
 * `seq` order. `status`: applied (done), conflict (done, but a manager should look: offline price on a stale menu,
 * oversold kazan, payment on a cancelled order, amount mismatch) or rejected (not applied: "Sinxronlash xatolari").
 * `result` keeps the server ids the operation created or touched; `resolved_*` the manager's decision on a rejected or
 * conflicting one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->uuid('op_id')->unique();
            $table->unsignedBigInteger('seq');
            $table->string('type', 32); // App\Domain\Pos\Sync\PosSyncOperationType
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->nullable(); // the device's clock
            $table->jsonb('payload');
            $table->string('status', 16); // applied | conflict | rejected
            $table->string('reason', 64)->nullable(); // machine code of a rejection or the first conflict
            $table->text('error')->nullable(); // the Uzbek message
            $table->jsonb('result')->nullable(); // server ids, flags
            $table->string('client_ref', 64)->nullable(); // the device's id of what the operation created (order, bill)
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('clock_skew_seconds')->nullable(); // server time minus the device's, when the batch was sent
            $table->timestamp('received_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution', 16)->nullable(); // resolved | dismissed
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'seq']);
            $table->index(['device_id', 'client_ref']);
            $table->index(['store_id', 'status', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sync_operations');
    }
};
