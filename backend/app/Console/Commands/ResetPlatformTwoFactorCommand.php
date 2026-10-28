<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Admin\PlatformTeamService;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;

/**
 * P4-11: a team member lost their phone and recovery codes (and no other superadmin can reset it from Tizim →
 * Jamoa): 2FA and recovery codes are removed and every session ends; they set 2FA up again at their next sign-in.
 * Audited (docs/OPERATIONS.md).
 */
class ResetPlatformTwoFactorCommand extends Command
{
    protected $signature = 'rizqim:admin:reset-2fa
        {email : The team member\'s email}
        {--reason= : Why (kept in the audit log)}
        {--force : Do not ask for confirmation}';

    protected $description = "Remove a RIZQIM team member's 2FA (lost phone); they set it up again at the next sign-in";

    public function handle(PlatformTeamService $team, AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $member = User::query()->whereNotNull('platform_role')->whereRaw('lower(email) = ?', [$email])->first();

        if ($member === null) {
            $this->error("Bu email bilan jamoa a'zosi topilmadi: {$email}");

            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason'));
        if (!$this->option('force') && !$this->confirm("{$member->name} ({$email}): 2FA o'chirilsinmi va barcha sessiyalari yopilsinmi?")) {
            $this->line('Bekor qilindi.');

            return self::FAILURE;
        }

        $before = $member->hasTwoFactorEnabled();
        $team->resetTwoFactor($member, null);

        $audit->record('admin.cli.2fa_reset', $member, ['two_factor_enabled' => $before], ['two_factor_enabled' => false], array_filter([
            'reason' => $reason !== '' ? mb_substr($reason, 0, 500) : null,
            'sessions_ended' => true,
        ], fn ($value) => $value !== null), ['actor' => null, 'device' => null, 'ip' => null]);

        $this->info("2FA o'chirildi. {$member->name} keyingi kirishda uni qayta o'rnatadi.");

        return self::SUCCESS;
    }
}
