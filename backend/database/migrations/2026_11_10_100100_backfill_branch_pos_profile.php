<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * P6-11: every existing branch works as a choyxona (the phase 3 flow): branches.pos_settings.profile = "choyxona"
     * where no profile is set yet, with no module overrides. A branch whose pos_settings is still null gets just these
     * two keys (every other setting keeps reading its default).
     */
    public function up(): void
    {
        DB::table('branches')->select(['id', 'pos_settings'])->orderBy('id')->chunkById(500, function ($branches) {
            foreach ($branches as $branch) {
                $settings = is_string($branch->pos_settings) ? json_decode($branch->pos_settings, true) : null;
                $settings = is_array($settings) ? $settings : [];
                if (isset($settings['profile']) && is_string($settings['profile'])) {
                    continue;
                }

                $settings['profile'] = 'choyxona';
                $settings['modules'] = is_array($settings['modules'] ?? null) ? $settings['modules'] : (object) [];
                DB::table('branches')->where('id', $branch->id)->update(['pos_settings' => json_encode($settings)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('branches')->select(['id', 'pos_settings'])->whereNotNull('pos_settings')->orderBy('id')->chunkById(500, function ($branches) {
            foreach ($branches as $branch) {
                $settings = is_string($branch->pos_settings) ? json_decode($branch->pos_settings, true) : null;
                if (!is_array($settings)) {
                    continue;
                }
                unset($settings['profile'], $settings['modules']);
                DB::table('branches')->where('id', $branch->id)->update(['pos_settings' => $settings === [] ? null : json_encode($settings)]);
            }
        });
    }
};
