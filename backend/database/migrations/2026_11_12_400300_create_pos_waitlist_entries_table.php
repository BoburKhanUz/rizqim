<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-24 (C5): the hostess' waitlist ("kutish ro'yxati"): guests without a booking waiting for a table. A guest's
     * name and phone (+998XXXXXXXXX, shown masked), the party size, the wait the hostess quoted (minutes), and where
     * the entry went: seated (at `dining_table_id`, optionally with the opened order), cancelled by the guest, a
     * no-show after being called. `business_date` is the branch's business day it was written on (Asia/Tashkent day
     * start rules). `version` for the usual 409 on stale writes.
     */
    public function up(): void
    {
        Schema::create('pos_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('guest_name', 120);
            $table->string('guest_phone', 20)->nullable();
            $table->unsignedSmallInteger('party_size');
            $table->unsignedSmallInteger('quoted_wait_minutes')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 16)->default('waiting'); // App\Domain\Pos\Waitlist\WaitlistStatus
            $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('closed_at')->nullable(); // seated, cancelled or no-show
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['branch_id', 'business_date', 'status']);
            $table->index(['branch_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_waitlist_entries');
    }
};
