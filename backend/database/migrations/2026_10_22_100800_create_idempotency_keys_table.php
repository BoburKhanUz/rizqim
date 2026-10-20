<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency-Key header (P1-21): the first request with a key claims the row (the unique index is the lock),
     * a repeat with the same key and payload within 24 hours gets the stored response back instead of running again.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64); // the endpoint, e.g. orders.store
            $table->string('actor_type', 16); // user | device
            $table->unsignedBigInteger('actor_id');
            $table->string('key', 255);
            $table->char('request_hash', 64); // sha256 of method, path and body
            $table->timestamp('locked_until')->nullable(); // set while the first request runs
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->string('response_content_type', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'actor_type', 'actor_id', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
