<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-16 (D5 c, d): how a branch takes cards and what its installation checklist says
     * (App\Domain\Pos\TerminalFlow):
     *  - `card_mode`: terminal_only (a bank terminal its TXKM switched to "faqat terminal"), smart_kassa (Rizqim runs
     *    on an Android smart kassa whose receipts go through the provider's virtual kassa), none (no cards); null: not
     *    chosen yet;
     *  - `checklist`: item => {done, done_at, done_by} (old kassa not used for sales, bank terminal mode set, 2D
     *    scanner present);
     *  - `difference_threshold`: a bank terminal day total that differs from the Z's card total by more than this
     *    alerts the owner (0: any difference);
     *  - `ask_bank_total`: the till asks for the bank terminal's day total at the Z.
     * One row per branch, written on the first change; a branch without one reads as the defaults.
     */
    public function up(): void
    {
        Schema::create('branch_terminal_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('card_mode', 16)->nullable();
            $table->jsonb('checklist')->nullable();
            $table->decimal('difference_threshold', 12, 2)->default(0);
            $table->boolean('ask_bank_total')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_terminal_setups');
    }
};
