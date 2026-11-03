<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-34 (B8): a packaging line the cashier took off one order (the guest brought their own bag), with the reason and
     * who did it. `rule_id` is the rule the line came from (no foreign key: the order keeps its snapshot when the rule is
     * deleted). Putting it back deletes the row; both are in the audit log.
     */
    public function up(): void
    {
        Schema::create('order_packaging_removals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('rule_id');
            $table->string('reason', 255);
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'rule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_packaging_removals');
    }
};
