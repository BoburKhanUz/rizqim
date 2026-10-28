<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31: a customer, courier or store staff account blocked from the Boshqaruv markazi (Mijozlar va yordam): no
     * sign-in, no token works (AccessServiceProvider, TokenService, GenerateAuthTokenAction), so no orders either. Who
     * blocked it and why stay with the account until it is unblocked; the audit log keeps the whole story. Platform
     * accounts are never blocked here (Jamoa disables them).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason', 500)->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blocked_by');
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
