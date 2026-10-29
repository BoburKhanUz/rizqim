<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Review W3 C#3: one baseline status log row per store that has none (its current status, at its creation), so the
 * Ko'rsatkichlar history (StoreActivity) has a starting point for stores whose status never changed through a logged
 * path. Soft-deleted stores too: they count from creation until deletion. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'INSERT INTO store_status_logs (store_id, from_status, to_status, admin_id, comment, created_at) '
            . "SELECT s.id, NULL, s.status, NULL, 'system', COALESCE(s.created_at, CURRENT_TIMESTAMP) FROM stores s "
            . 'WHERE NOT EXISTS (SELECT 1 FROM store_status_logs l WHERE l.store_id = s.id)'
        );
    }

    public function down(): void
    {
        // A baseline row is told apart by having no previous status; the history it starts is kept
    }
};
