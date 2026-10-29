<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31: the RIZQIM support side of a complaint (Shikoyatlar qutisi), next to the customer ↔ store flow that
     * `status` keeps (pending → owner_replied → in_review → closed, the Mini App and the partner panel read it):
     * support_status new → in_progress → answered → resolved, who took it (assigned_to), when support answered and
     * resolved it. Closed complaints count as resolved. Indexes for the inbox (newest first per status) and the user
     * card (a customer's complaints).
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('support_status', 16)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('support_status_changed_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['support_status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        DB::table('complaints')->where('status', 'closed')->update([
            'support_status' => 'resolved',
            'resolved_at' => DB::raw('updated_at'),
            'support_status_changed_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['support_status', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['support_status', 'support_status_changed_at', 'answered_at', 'resolved_at']);
        });
    }
};
