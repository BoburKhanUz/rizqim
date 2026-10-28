<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;

/**
 * P4-23: every booking and reversal of an order reads its ledger rows by order (LedgerService: hasCommission,
 * hasSettlement, reverseDeliveredOrder*), and the monthly report joins reversals to orders. Built concurrently, the
 * ledgers stay writable during the deploy.
 */
return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    /** @var array<string, array{string, list<string>}> */
    private const INDEXES = [
        'store_transactions_order_type_index' => ['store_transactions', ['order_id', 'type']],
        'store_wallet_transactions_order_type_index' => ['store_wallet_transactions', ['order_id', 'type']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => [$table, $columns]) {
            ConcurrentIndex::create($name, $table, $columns);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES, true) as $name => [$table]) {
            ConcurrentIndex::drop($name, $table);
        }
    }
};
