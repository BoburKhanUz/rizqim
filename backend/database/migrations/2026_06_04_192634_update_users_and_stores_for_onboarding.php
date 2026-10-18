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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('is_active');
            $table->text('moderation_note')->nullable()->after('status');
            $table->string('legal_name')->nullable()->after('name');
            $table->string('tax_number')->nullable()->after('legal_name');
            $table->string('bank_account')->nullable()->after('tax_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['status', 'moderation_note', 'legal_name', 'tax_number', 'bank_account']);
        });
    }
};
