<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-24: whose courier this is: the restaurant's own (`own`) or the platform's fleet (`fleet`). Only meaningful for
 * users with the courier role; dispatch by courier type comes in phase 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('courier_type', 16)->default('own');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('courier_type');
        });
    }
};
