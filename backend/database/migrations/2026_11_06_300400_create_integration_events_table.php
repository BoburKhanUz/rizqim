<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31 (docs/PHASE5_PLAN.md 4.3, 4.4): the integration journal. Every call to or from an outside system is one row:
     * its direction (inbound / outbound), type (order.push, menu.sync, webhook.status...), idempotency key, the record it
     * is about (subject_type + subject_id: an order, a product...), the request and response bodies (writers never put
     * tokens or secrets in them; the admin API shows them through AuditRedactor), the HTTP status, the signature check,
     * attempts and the next attempt, and the state: pending, sent, acknowledged, failed, dead.
     * App\Domain\Integrations\IntegrationEvent. Phase 4 only reads it (Integratsiyalar → Hodisalar jurnali).
     */
    public function up(): void
    {
        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // An inbound call that matched no connection is still journaled
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('direction', 16);
            $table->string('type', 40);
            $table->string('idempotency_key', 120)->nullable();
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['integration_connection_id', 'id']);
            $table->index(['status', 'next_attempt_at']);
            $table->index(['type', 'id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });

        // A retried delivery never opens a second row (or a second order) for the same key on the same connection
        DB::statement('CREATE UNIQUE INDEX integration_events_idempotency_unique ON integration_events (integration_connection_id, direction, idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
    }
};
