<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time 6-digit codes an owner or manager makes to pair a POS/KDS terminal. Only an HMAC of the code is kept.
     */
    public function up(): void
    {
        Schema::create('device_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('type', 32); // pos or kds
            $table->string('name', 100)->nullable();
            $table->char('code_hash', 64)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairing_codes');
    }
};
