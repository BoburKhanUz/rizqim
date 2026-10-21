<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-13: the branch's POS settings (POS_SPEC 10.3). `business_day_starts_at`: the local time the business day
     * starts (a sale at 02:00 after a Thursday evening belongs to Thursday); the default 05:00 can be moved earlier
     * (04:00) for a branch that opens early. `pos_settings`: mode, printing terminal, screen lock, queue numbers and the
     * alert thresholds (App\Domain\Pos\Setup\PosSettings); null reads as the defaults. Constant default and nullable
     * column: existing rows keep working without a rewrite.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->time('business_day_starts_at')->default('05:00:00');
            $table->jsonb('pos_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['business_day_starts_at', 'pos_settings']);
        });
    }
};
