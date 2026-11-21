<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave M5 S5-a (docs/scale/M5_DESIGN.md section 6): the registry of POS app releases. Platform-wide (no store): the
     * RIZQIM team publishes versions from the control centre; terminals are told what is new
     * (GET /terminal `app_release`), pos.rizqim.uz reads the public manifest (GET /pos/releases). A version is
     * withdrawn, never deleted by a till's request. `is_minimum`: versions below it are unsupported; `minimum_from`:
     * the day the notice turns red (14 days after the declaration unless staff set another day, owner answer 8).
     */
    public function up(): void
    {
        Schema::create('pos_app_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('app', 32); // App\Enums\DeviceApp
            $table->string('platform', 16); // android | windows
            $table->string('version', 32); // 1.4.0
            $table->unsignedInteger('build')->default(0);
            $table->string('file_url', 500);
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->text('notes')->nullable(); // Uzbek, shown in the app
            $table->timestamp('released_at');
            $table->boolean('is_minimum')->default(false);
            $table->timestamp('minimum_from')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['app', 'platform', 'version', 'build']);
            $table->index(['app', 'platform', 'withdrawn_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_app_releases');
    }
};
