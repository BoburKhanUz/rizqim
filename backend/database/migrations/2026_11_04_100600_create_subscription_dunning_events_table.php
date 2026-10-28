<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-21: the unpaid-invoice routine's history (docs/BILLING.md, "Eslatmalar va cheklov"): each reminder, the lock
     * and the unlock, once per invoice and stage (unique), so the daily run never sends a reminder twice. `notified`
     * false: recorded on a catch-up run without a message (a later reminder went instead).
     */
    public function up(): void
    {
        Schema::create('subscription_dunning_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('subscription_invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();
            $table->string('stage', 16); // App\Enums\DunningStage
            $table->boolean('notified')->default(true);
            $table->unsignedSmallInteger('recipients')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['subscription_invoice_id', 'stage']);
            $table->index(['store_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_dunning_events');
    }
};
