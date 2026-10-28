<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-15: Monitoring → Ogohlantirishlar. An alert is one episode of a problem found by a rule: the same rule and
     * subject (a queue, a device, "global") never has two unresolved alerts at once (partial unique index); when the
     * problem goes away the evaluator resolves it, and if it comes back that is a new alert.
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $table->string('kind', 40);
            // What the problem is about: "global", a queue name, a device uuid
            $table->string('subject_key', 120);
            $table->string('severity', 16);
            $table->string('state', 16)->default('open');
            $table->string('title', 200);
            $table->json('details')->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('count')->default(1);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledge_note', 500)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolve_note', 500)->nullable();
            // manual (a team member) | auto (the problem went away) | rule_disabled
            $table->string('resolution', 16)->nullable();
            // The ops group heard about it: the resolve message is sent only for an alert whose open message went out
            $table->timestamp('telegram_opened_at')->nullable();
            $table->timestamp('telegram_resolved_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'severity', 'id']);
            $table->index(['kind', 'state']);
            $table->index('resolved_at');
        });

        DB::statement("CREATE UNIQUE INDEX alerts_one_unresolved_per_subject ON alerts (alert_rule_id, subject_key) WHERE state <> 'resolved'");
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
