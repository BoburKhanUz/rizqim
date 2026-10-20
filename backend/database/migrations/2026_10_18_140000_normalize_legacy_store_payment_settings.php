<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stores from the first creation wizard hold payment providers as bare booleans (`cash: true`). Every reader takes
     * that as {enabled: true}, but a form editing `cash.enabled` saw nothing and switched the method off on save.
     * Rewrite them as sections with the same value; rows already in that form are left alone, so it can run again.
     */
    public function up(): void
    {
        DB::table('stores')->whereNotNull('payment_settings')->orderBy('id')->chunkById(200, function ($stores) {
            foreach ($stores as $store) {
                $settings = json_decode($store->payment_settings, true);
                if (!is_array($settings)) {
                    continue;
                }

                $changed = false;
                foreach (['cash', 'click', 'payme', 'uzum', 'paynet'] as $provider) {
                    if (isset($settings[$provider]) && !is_array($settings[$provider])) {
                        $settings[$provider] = ['enabled' => (bool) $settings[$provider]];
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('stores')->where('id', $store->id)->update(['payment_settings' => json_encode($settings)]);
                }
            }
        });
    }

    public function down(): void
    {
        // Same meaning for every reader in either form: nothing to restore.
    }
};
