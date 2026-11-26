<?php

namespace App\Console\Commands;

use App\Domain\Analytics\AnalyticsErasure;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * A privacy request handled by an operator (docs/OPERATIONS.md "Maxfiylik so'rovlari: statistikani o'chirish"): deletes
 * every stored Mini App event of one user (AnalyticsErasure::forget, the same deletion as the user card's
 * "Statistikasini o'chirish") and prints how many rows went. Audited (cli_analytics_forgotten, with the count and the
 * optional reason). Idempotent: run again, it deletes 0.
 */
class ForgetUserAnalyticsCommand extends Command
{
    protected $signature = 'analytics:forget-user
        {user : Foydalanuvchi ID raqami (users.id)}
        {--reason= : Sabab (masalan, so\'rov raqami): jurnalga yoziladi}';

    protected $description = "Bitta foydalanuvchining barcha statistika yozuvlarini (analytics_events) o'chiradi";

    public function handle(AnalyticsErasure $erasure): int
    {
        $id = (string) $this->argument('user');
        if (preg_match('/^[1-9][0-9]{0,17}$/', $id) !== 1) {
            $this->error("Foydalanuvchi ID raqami butun son bo'lishi kerak.");

            return self::FAILURE;
        }
        $user = User::query()->find((int) $id);
        if ($user === null) {
            $this->error("Foydalanuvchi #{$id} topilmadi.");

            return self::FAILURE;
        }

        $deleted = $erasure->forget((int) $user->id);
        $reason = trim((string) $this->option('reason'));
        $payload = ['analytics_events_deleted' => $deleted] + ($reason !== '' ? ['reason' => mb_substr($reason, 0, 500)] : []);

        AuditLog::create([
            'user_id' => null,
            'action' => 'cli_analytics_forgotten',
            'model_type' => User::class,
            'model_id' => $user->id,
            'payload_json' => $payload,
        ]);
        Log::notice('analytics:forget-user executed', $payload + ['user_id' => $user->id]);

        $this->info("User #{$user->id}: {$deleted} ta statistika yozuvi o'chirildi.");

        return self::SUCCESS;
    }
}
