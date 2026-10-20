<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A branch's stop-list (P1-21): a product, variant or modifier that is unavailable there, optionally until a
     * time, with a reason. Removing the entry (or `until` passing) makes it available again.
     */
    public function up(): void
    {
        Schema::create('stop_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 16); // App\Enums\StopListSubject
            $table->unsignedBigInteger('subject_id');
            $table->string('reason', 255)->nullable();
            $table->timestamp('until')->nullable(); // null: until removed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'subject_type', 'subject_id']);
            $table->index(['store_id', 'branch_id']);
            $table->index('until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stop_list_entries');
    }
};
