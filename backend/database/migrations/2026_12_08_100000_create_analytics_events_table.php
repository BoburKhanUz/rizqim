<?php

use App\Support\Partitions\PartitionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Wave P, step 2 (docs/ANALYTICS_PLAN.md A.3): the Mini App's raw analytics events (POST /api/v1/analytics/events,
     * App\Domain\Analytics\EventIngestor), created partitioned by month of received_at from the start (no
     * conversion), with a DEFAULT partition and the months of config partitions.tables.analytics_events (the online
     * window back, months_ahead forward) made by PartitionManager::ensure(); db:partitions:ensure keeps them coming and
     * db:partitions:archive drops the months past the retention (no archive copy).
     *
     * A new empty table: plain CREATE INDEX on the parent (cascades to every month, and ATTACH gives new months the
     * same indexes). No foreign keys (the M3 rule for partitioned journals; user_id, store_id, product_id and order_id
     * are plain numbers, checked by the ingestion where it matters). No row-level security: store_id is nullable and
     * no store reads this table (TenantRlsInventoryTest NOT_UNDER_RLS, MIXED). Touches no cache or Redis.
     *
     * Columns: see the plan. Nothing here can hold an IP address, a User-Agent, a Telegram id or free text: props is
     * an allowlisted jsonb (App\Domain\Analytics\AnalyticsEvent::props) of at most 512 bytes.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE analytics_events (
                id bigserial NOT NULL,
                received_at timestamp(0) without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
                occurred_at timestamp(0) without time zone NOT NULL,
                session_id uuid NOT NULL,
                event_uid uuid NOT NULL,
                user_id bigint NULL,
                event smallint NOT NULL,
                source smallint NOT NULL,
                platform smallint NOT NULL,
                app_version varchar(16) NULL,
                store_id bigint NULL,
                product_id bigint NULL,
                order_id bigint NULL,
                campaign_id integer NULL,
                props jsonb NULL,
                CONSTRAINT analytics_events_pkey PRIMARY KEY (id, received_at),
                CONSTRAINT analytics_events_props_size CHECK (props IS NULL OR octet_length(props::text) <= 512)
            ) PARTITION BY RANGE (received_at)
            SQL);
        DB::statement('CREATE INDEX analytics_events_received_at_brin ON analytics_events USING brin (received_at)');
        // The ingestion's replay check (session_id + event_uid over the last day) and the funnel's per-session reads
        DB::statement('CREATE INDEX analytics_events_session_id_index ON analytics_events (session_id)');

        app(PartitionManager::class)->ensure('analytics_events');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Raw events only (the retention drops them anyway): the parent with its months, any month left detached by
        // an interrupted archive run, and the catalogue rows of the dropped months (a later up() starts clean)
        app(PartitionManager::class)->dropTable('analytics_events');
    }
};
