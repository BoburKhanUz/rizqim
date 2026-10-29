<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Review P6 S#4: a provider kassa id belongs to one till. Ids are stored upper case and trimmed; among active
     * (unrevoked) devices one id is used once per store, and once anywhere among active smart kassas (partial unique
     * indexes). A duplicate already there keeps the oldest device's id; the later ones are cleared (logged) and their
     * owner enters them again.
     */
    public function up(): void
    {
        DB::table('devices')->whereNotNull('fiscal_kassa_id')->update(['fiscal_kassa_id' => DB::raw('UPPER(TRIM(fiscal_kassa_id))')]);
        DB::table('devices')->where('fiscal_kassa_id', '')->update(['fiscal_kassa_id' => null]);

        $rows = DB::table('devices')->whereNotNull('fiscal_kassa_id')->whereNull('revoked_at')->orderBy('id')
            ->get(['id', 'store_id', 'fiscal_kassa_id', 'smart_kassa']);
        $perStore = [];
        $smart = [];
        foreach ($rows as $row) {
            $storeKey = $row->store_id . '|' . $row->fiscal_kassa_id;
            $clash = isset($perStore[$storeKey]) || ((bool) $row->smart_kassa && isset($smart[$row->fiscal_kassa_id]));
            if ($clash) {
                DB::table('devices')->where('id', $row->id)->update(['fiscal_kassa_id' => null]);
                Log::warning('Duplicate fiscal_kassa_id cleared', ['device_id' => $row->id, 'store_id' => $row->store_id, 'fiscal_kassa_id' => $row->fiscal_kassa_id]);

                continue;
            }
            $perStore[$storeKey] = true;
            if ((bool) $row->smart_kassa) {
                $smart[$row->fiscal_kassa_id] = true;
            }
        }

        DB::statement('CREATE UNIQUE INDEX devices_store_fiscal_kassa_unique ON devices (store_id, fiscal_kassa_id) WHERE fiscal_kassa_id IS NOT NULL AND revoked_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX devices_smart_kassa_unique ON devices (fiscal_kassa_id) WHERE fiscal_kassa_id IS NOT NULL AND revoked_at IS NULL AND smart_kassa');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS devices_smart_kassa_unique');
        DB::statement('DROP INDEX IF EXISTS devices_store_fiscal_kassa_unique');
    }
};
