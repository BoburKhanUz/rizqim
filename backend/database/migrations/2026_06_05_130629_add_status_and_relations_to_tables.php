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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete()->after('store_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete()->after('courier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn('manager_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
