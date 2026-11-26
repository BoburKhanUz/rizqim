<?php

namespace App\Domain\Analytics;

use Illuminate\Support\Facades\DB;

/**
 * A privacy request (docs/OPERATIONS.md "Maxfiylik so'rovlari: statistikani o'chirish"): every stored Mini App event of
 * one user. Used by the user card's "Statistikasini o'chirish" (POST /admin/users/{id}/forget-analytics) and by
 * `php artisan analytics:forget-user`; each caller audits it.
 *
 * - One DELETE of the partitioned parent by user_id: every month and DEFAULT (not pruned by received_at, a user's rows
 *   can be in any month), each read by its analytics_events_user_id_index. Writes nothing else: no other table, no
 *   cache, no ETag (analytics_events is RevisionMap NONE).
 * - analytics_events has no row-level security (not a tenant table, TenantRlsInventoryTest MIXED): no tenant role and
 *   no TenantRls::asApp are needed.
 * - Events are never anonymous: the ingestion stores only a token's user (EventIngestor), so these are all of the
 *   person's rows. The daily aggregates of step 16 are anonymous counts and are not touched.
 * - Backups and point-in-time recovery keep the deleted rows until they age out (docs/OPERATIONS.md).
 */
final class AnalyticsErasure
{
    /** The user's stored events (the user card shows it) */
    public function count(int $userId): int
    {
        return DB::table('analytics_events')->where('user_id', $userId)->count();
    }

    /** Deletes every stored event of the user; returns how many rows went */
    public function forget(int $userId): int
    {
        return (int) DB::table('analytics_events')->where('user_id', $userId)->delete();
    }
}
