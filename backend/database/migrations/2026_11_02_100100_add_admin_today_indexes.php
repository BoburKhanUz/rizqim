<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P4-13: indexes behind GET /admin/today and its "Diqqat talab qiladi" sources (App\Services\Admin\Today):
 * - the live part of today's numbers reads orders and paid invoices by time range only (no store filter);
 * - each attention source looks up a few rows by status and age: pending payouts and top-ups, overdue invoices,
 *   failed fiscal receipts, terminals not seen lately (a partial index: only paired, not revoked devices).
 */
return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    private const DEVICES_INDEX = 'devices_admin_last_seen_at_index';

    /** @var array<string, array{string, list<string>}> */
    private const INDEXES = [
        'orders_admin_created_at_index' => ['orders', ['created_at']],
        'subscription_invoices_admin_paid_at_index' => ['subscription_invoices', ['paid_at']],
        'subscription_invoices_admin_status_due_date_index' => ['subscription_invoices', ['status', 'due_date']],
        'payout_requests_admin_status_created_at_index' => ['payout_requests', ['status', 'created_at']],
        'store_topup_requests_admin_status_created_at_index' => ['store_topup_requests', ['status', 'created_at']],
        'fiscal_receipts_admin_status_created_at_index' => ['fiscal_receipts', ['status', 'created_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => [$table, $columns]) {
            ConcurrentIndex::create($name, $table, $columns);
        }

        if (DB::getDriverName() === 'pgsql') {
            if (ConcurrentIndex::isInvalid(self::DEVICES_INDEX)) {
                DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::DEVICES_INDEX);
            }
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . self::DEVICES_INDEX . ' ON devices (last_seen_at) WHERE revoked_at IS NULL AND store_id IS NOT NULL');
        } else {
            Schema::table('devices', fn (Blueprint $table) => $table->index(['last_seen_at'], self::DEVICES_INDEX));
        }
    }

    public function down(): void
    {
        ConcurrentIndex::drop(self::DEVICES_INDEX, 'devices');
        foreach (array_reverse(self::INDEXES, true) as $name => [$table]) {
            ConcurrentIndex::drop($name, $table);
        }
    }
};
