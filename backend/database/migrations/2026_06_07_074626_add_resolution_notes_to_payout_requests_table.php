<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('payout_requests', 'resolution_notes')) {
            Schema::table('payout_requests', function (Blueprint $table) {
                $table->text('resolution_notes')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('payout_requests', 'resolution_notes')) {
            Schema::table('payout_requests', function (Blueprint $table) {
                $table->dropColumn('resolution_notes');
            });
        }
    }
};
