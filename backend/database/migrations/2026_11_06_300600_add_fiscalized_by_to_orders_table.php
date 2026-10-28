<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31 (docs/PHASE5_PLAN.md 3.1, 4.3, 7): who issued the order's fiscal receipt: venue_pos (the venue's own till),
     * payment_provider (Payme / Click registered it), aggregator, rizqim_marketplace (reserved). NULL = not decided yet
     * ("fiscal review needed"); every existing order stays NULL and nothing reads it before phase 5.
     * App\Domain\Integrations\FiscalizedBy.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fiscalized_by', 24)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fiscalized_by');
        });
    }
};
