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
                // Encrypted at rest since 2026_12_01_100000 (the migration re-runs in tests and on a rollback)
                $encrypted = true;
                try {
                    $json = \Illuminate\Support\Facades\Crypt::decryptString($store->payment_settings);
                } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                    [$json, $encrypted] = [$store->payment_settings, false];
                }
                $settings = json_decode($json, true);
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
                    $json = json_encode($settings);
                    DB::table('stores')->where('id', $store->id)->update(['payment_settings' => $encrypted ? \Illuminate\Support\Facades\Crypt::encryptString($json) : $json]);
                }
            }
        });
    }

    public function down(): void
    {
        // Same meaning for every reader in either form: nothing to restore.
    }
};
