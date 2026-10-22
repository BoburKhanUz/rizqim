<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P3-26: one holiday list per branch. branches.pos_settings.holidays (P3-13) is the source of truth; the room tariff
 * calendar's own list (room_tariff_calendars.holidays, P3-12) is merged into it and cleared, and PUT
 * /owner/room-tariffs/calendar writes the branch's list from now on. down() copies the branch lists back to the
 * calendars (the merged dates stay in pos_settings: both lists counted before as well).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('room_tariff_calendars')->whereNotNull('holidays')->orderBy('id')->get(['id', 'branch_id', 'holidays'])
            ->each(function (object $row) {
                $calendar = json_decode((string) $row->holidays, true);
                if (is_array($calendar) && $calendar !== []) {
                    $branch = DB::table('branches')->where('id', $row->branch_id)->lockForUpdate()->first(['id', 'pos_settings']);
                    if ($branch !== null) {
                        $settings = json_decode((string) ($branch->pos_settings ?? 'null'), true);
                        $settings = is_array($settings) ? $settings : [];
                        $current = is_array($settings['holidays'] ?? null) ? $settings['holidays'] : [];
                        $merged = array_values(array_unique(array_filter([...$current, ...$calendar], 'is_string')));
                        sort($merged);
                        $settings['holidays'] = $merged;
                        DB::table('branches')->where('id', $branch->id)->update(['pos_settings' => json_encode($settings)]);
                    }
                }

                DB::table('room_tariff_calendars')->where('id', $row->id)->update(['holidays' => null]);
            });
    }

    public function down(): void
    {
        DB::table('room_tariff_calendars')->orderBy('id')->get(['id', 'branch_id'])
            ->each(function (object $row) {
                $settings = json_decode((string) (DB::table('branches')->where('id', $row->branch_id)->value('pos_settings') ?? 'null'), true);
                $holidays = is_array($settings) && is_array($settings['holidays'] ?? null) ? array_values($settings['holidays']) : [];
                DB::table('room_tariff_calendars')->where('id', $row->id)->update(['holidays' => json_encode($holidays)]);
            });
    }
};
