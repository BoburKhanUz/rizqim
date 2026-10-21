<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-34: a refresh whose answer never reached the app (network drop) is retried with the token just spent. Within a
 * short grace window that retry rotates once more instead of revoking the family (TokenService::refresh):
 * replaced_by_id is the token issued for this one, grace_used_at marks the one retry it is allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('replaced_by_id')->nullable();
            $table->timestamp('grace_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropColumn(['replaced_by_id', 'grace_used_at']);
        });
    }
};
