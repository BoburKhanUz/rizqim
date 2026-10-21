<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-34: how a verified phone (users.phone_verified_at) was established: `sms` (the holder typed the code) or
 * `vouched` (a store owner/manager or the super admin wrote it as a staff login phone, StaffPhoneLoginService::vouch).
 * Only an SMS-proven phone links customer accounts or is protected from being overwritten in the employee form.
 *
 * Backfill: vouching ships in the same release as this column (P1-36 together with P1-34), so every
 * phone_verified_at written before it came from an SMS code (AccountLinkingService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_verified_via', 16)->nullable()->after('phone_verified_at');
        });

        DB::table('users')->whereNotNull('phone_verified_at')->update(['phone_verified_via' => 'sms']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_via');
        });
    }
};
