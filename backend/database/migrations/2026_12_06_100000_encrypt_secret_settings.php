<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Review 17 M1: the control centre's secret settings (the Eskiz password, the Onlinegroup API key) are stored
 * encrypted with APP_KEY from now on (SystemSettings::write, read back by setting() and SystemSettings). Every
 * existing plain value is encrypted in place. Idempotent both ways: up() leaves a value that already decrypts alone,
 * down() one that does not. Empty values stay empty. The setting() cache of each key is forgotten (it may hold the
 * old plain text).
 *
 * The keys are those SystemSettings::EDITABLE marks `secret` today, listed here so a later change of that list does
 * not change what this migration did.
 */
return new class extends Migration
{
    private const KEYS = ['eskiz_password', 'onlinegroup_api_key'];

    public function up(): void
    {
        $this->rewrite(fn (string $value) => $this->decrypts($value) ? null : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->rewrite(function (string $value) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return null; // already plain
            }
        });
    }

    /**
     * @param  \Closure(string): ?string  $transform  the new value, or null to leave the row as it is
     */
    private function rewrite(\Closure $transform): void
    {
        $rows = DB::table('settings')->whereIn('key', self::KEYS)->whereNotNull('value')->where('value', '<>', '')->get(['id', 'value']);
        foreach ($rows as $row) {
            $new = $transform((string) $row->value);
            if ($new !== null) {
                DB::table('settings')->where('id', $row->id)->update(['value' => $new, 'updated_at' => now()]);
            }
        }

        foreach (self::KEYS as $key) {
            Cache::forget("setting_{$key}");
        }
    }

    private function decrypts(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
