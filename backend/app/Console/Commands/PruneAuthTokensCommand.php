<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\RefreshToken;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Replaces `sanctum:prune-expired` in the schedule: that command deletes every token older than
 * SANCTUM_TOKEN_EXPIRATION, which would also unpair POS/KDS terminals, whose own tokens live longer
 * (AUTH_DEVICE_TOKEN_TTL_DAYS). Do not run sanctum:prune-expired by hand either.
 */
class PruneAuthTokensCommand extends Command
{
    protected $signature = 'auth:prune-tokens {--hours=24 : Keep expired tokens this many hours before deleting them}';

    protected $description = 'Delete expired access tokens (not live terminal tokens), refresh tokens and pairing codes';

    public function handle(): int
    {
        $hours = max(0, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        // Past their own expires_at: any token, terminals included
        $expired = PersonalAccessToken::where('expires_at', '<', $cutoff)->delete();

        // Past the global lifetime (SANCTUM_TOKEN_EXPIRATION): user tokens only, as sanctum:prune-expired did
        $aged = 0;
        if ($minutes = (int) config('sanctum.expiration')) {
            $aged = PersonalAccessToken::where('tokenable_type', '!=', (new Device())->getMorphClass())
                ->where('created_at', '<', now()->subMinutes($minutes + $hours * 60))
                ->delete();
        }

        // Kept until the family expires so a replayed token is still recognised as reuse
        $refresh = RefreshToken::where('expires_at', '<', $cutoff)->delete();
        $codes = DevicePairingCode::where('expires_at', '<', $cutoff)->delete();

        $this->info("Deleted {$expired} expired and {$aged} aged access tokens, {$refresh} refresh tokens, {$codes} pairing codes.");

        return self::SUCCESS;
    }
}
