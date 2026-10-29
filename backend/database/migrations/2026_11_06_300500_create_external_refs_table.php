<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31 (docs/PHASE5_PLAN.md 4.3): RIZQIM record ↔ the outside system's id, per connection. entity_type: branch,
     * category, product, variant, modifier, order. Unique both ways: one RIZQIM record has one outside id on a connection
     * and one outside id points at one RIZQIM record. App\Domain\Integrations\ExternalRef; Menyu xaritasi reads it in
     * phase 5.
     */
    public function up(): void
    {
        Schema::create('external_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_connection_id')->constrained('integration_connections')->cascadeOnDelete();
            $table->string('entity_type', 24);
            $table->unsignedBigInteger('entity_id');
            $table->string('external_id', 191);
            // What the outside system calls it (a dish name, a price) when the map was last synced
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['integration_connection_id', 'entity_type', 'entity_id'], 'external_refs_internal_unique');
            $table->unique(['integration_connection_id', 'entity_type', 'external_id'], 'external_refs_external_unique');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_refs');
    }
};
