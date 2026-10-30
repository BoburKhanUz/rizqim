<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-23: work time of a staff member in a branch (the minimal clock-in/out of docs/POS_SPEC.md "Ish haqi
     * qoidalari"): clocked in and out from their PIN session on a paired terminal (`source` pin), or added and
     * corrected by a manager in the panel (`manual`, with a reason; audited). It belongs to the business day of its
     * start. An open session (no `clock_out_at`) is not counted; one left open on an earlier business day is set aside
     * (`abandoned_at`) when its person clocks in again, until a manager gives it an end. One open session per person.
     */
    public function up(): void
    {
        Schema::create('staff_work_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date');
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->string('source', 16)->default('pin'); // App\Domain\Pos\StaffPay\WorkSessionSource
            $table->string('note', 500)->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'business_date']);
            $table->index(['user_id', 'business_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX staff_work_sessions_one_open_per_user ON staff_work_sessions (user_id) WHERE clock_out_at IS NULL AND abandoned_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_work_sessions');
    }
};
