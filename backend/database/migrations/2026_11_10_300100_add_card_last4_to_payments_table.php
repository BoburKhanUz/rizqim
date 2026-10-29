<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-16 (D5 a): a card paid on a separate bank terminal in "faqat terminal" mode keeps the terminal's transaction
     * (RRN) number in the existing `transaction_id` and, optionally, the last four digits of the card as printed on the
     * terminal's slip. Never the full card number. Null for every other payment.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('card_last4', 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('card_last4');
        });
    }
};
