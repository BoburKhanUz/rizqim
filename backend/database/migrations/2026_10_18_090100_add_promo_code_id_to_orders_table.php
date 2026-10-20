<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'promo_code_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            });
        }

        $this->createIndex('orders', 'orders_promo_code_id_user_id_index', ['promo_code_id', 'user_id']);
        $this->createIndex('order_status_histories', 'order_status_histories_order_id_status_index', ['order_id', 'status']);
    }

    public function down(): void
    {
        $this->dropIndex('order_status_histories', 'order_status_histories_order_id_status_index');
        $this->dropIndex('orders', 'orders_promo_code_id_user_id_index');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
        });
    }

    private function createIndex(string $table, string $name, array $columns): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Built without blocking writes on these live tables
            DB::statement(sprintf('CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (%s)', $name, $table, implode(', ', $columns)));

            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    private function dropIndex(string $table, string $name): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");

            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
    }
};
