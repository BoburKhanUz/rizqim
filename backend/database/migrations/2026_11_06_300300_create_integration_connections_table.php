<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31 (docs/PHASE5_PLAN.md 4.3): one store's (or branch's) link to an outside system: iiko, Alipos, Yandex Eats,
     * Uzum Tezkor, a Partner API client. Phase 5 adapters write it; phase 4 only shows it in Integratsiyalar → Ulanishlar
     * and lets the team pause it. App\Domain\Integrations\IntegrationConnection.
     *
     *  - direction: rizqim_to_pos (A: a RIZQIM order goes to the venue's own POS) or aggregator_to_rizqim (B: an
     *    aggregator's order comes to Rizqim POS);
     *  - mode: sandbox or production; status: configuring, active, paused, disconnected;
     *  - credentials: Laravel-encrypted JSON, never serialised (the admin API shows only "configured" and the last 4
     *    characters); settings: plain JSON (terminal, payment type, price source...);
     *  - health: the last successful call, the last webhook, the last error, today's error count and the 0-100 score.
     */
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('direction', 24);
            $table->string('mode', 16)->default('sandbox');
            $table->string('status', 16)->default('configuring');
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();

            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedInteger('errors_today')->default(0);
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->timestamp('health_checked_at')->nullable();

            // Paused by the team (Integratsiyalar → pause, with a reason); resume goes back to the status before it
            $table->timestamp('paused_at')->nullable();
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pause_reason', 500)->nullable();
            $table->string('status_before_pause', 16)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'provider']);
            $table->index(['status', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
