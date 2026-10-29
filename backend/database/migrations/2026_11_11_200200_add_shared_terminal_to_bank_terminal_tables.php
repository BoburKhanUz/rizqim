<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review P6 C#M2, S#5 (App\Domain\Pos\TerminalFlow\BankTerminalReconciler):
     *  - branch_terminal_setups.shared_terminal: the branch's tills share one bank terminal (or a till runs several
     *    shifts a day) settled once per business day. Off (the default): one report per shift, as before. On: one
     *    report per branch and business day (`bank_terminal_reports.shared`) whose Rizqim side is the card money of
     *    every shift of that day without a report of its own; at most one such report per branch and day;
     *  - bank_terminal_reports.alerted_difference: the difference the owner was told about (an alert is sent once all
     *    the report's shifts are closed; the same difference never again);
     *  - bank_terminal_reports.history: the earlier entries a correction replaced ({source, bank_total, bank_count,
     *    difference, over_threshold, entered_by, entered_at}); a correction never clears an alert.
     */
    public function up(): void
    {
        Schema::table('branch_terminal_setups', function (Blueprint $table) {
            $table->boolean('shared_terminal')->default(false);
        });
        Schema::table('bank_terminal_reports', function (Blueprint $table) {
            $table->boolean('shared')->default(false);
            $table->decimal('alerted_difference', 14, 2)->nullable();
            $table->jsonb('history')->nullable();
        });
        DB::table('bank_terminal_reports')->whereNotNull('alerted_at')->update(['alerted_difference' => DB::raw('difference')]);
        DB::statement('CREATE UNIQUE INDEX bank_terminal_reports_shared_day_unique ON bank_terminal_reports (branch_id, business_date) WHERE shared');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bank_terminal_reports_shared_day_unique');
        Schema::table('bank_terminal_reports', function (Blueprint $table) {
            $table->dropColumn(['shared', 'alerted_difference', 'history']);
        });
        Schema::table('branch_terminal_setups', function (Blueprint $table) {
            $table->dropColumn('shared_terminal');
        });
    }
};
