<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Go-live review: a restaurant's own Click / Payme keys (stores.payment_settings.<provider>.secret_key) are encrypted
 * at rest with APP_KEY, as the platform's are (payment_providers.credentials). The Store model casts the column
 * `encrypted:array`, so the column becomes text (ciphertext is not JSON) and every existing value is encrypted in
 * place. Idempotent both ways: a value that already decrypts is left alone by up(), one that does not by down().
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->columnType() !== 'text') {
            DB::statement('ALTER TABLE stores ALTER COLUMN payment_settings TYPE text USING payment_settings::text');
        }

        $this->rewrite(fn (string $value) => $this->decrypts($value) ? null : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->rewrite(function (string $value) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return null; // already plain JSON
            }
        });

        if ($this->columnType() !== 'jsonb') {
            DB::statement('ALTER TABLE stores ALTER COLUMN payment_settings TYPE jsonb USING payment_settings::jsonb');
        }
    }

    /**
     * @param  \Closure(string): ?string  $transform  the new value, or null to leave the row as it is
     */
    private function rewrite(\Closure $transform): void
    {
        DB::table('stores')->whereNotNull('payment_settings')->select(['id', 'payment_settings'])->orderBy('id')
            ->chunkById(200, function ($stores) use ($transform) {
                foreach ($stores as $store) {
                    $new = $transform((string) $store->payment_settings);
                    if ($new !== null) {
                        DB::table('stores')->where('id', $store->id)->update(['payment_settings' => $new]);
                    }
                }
            });
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

    private function columnType(): ?string
    {
        $type = DB::scalar(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'stores' AND column_name = 'payment_settings'"
        );

        return $type === null ? null : (string) $type;
    }
};
