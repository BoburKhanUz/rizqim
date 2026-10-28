<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Admin\AdminAuthException;
use App\Services\Admin\PlatformTeamService;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;

/**
 * Review F2: a new one-time invite link (72 hours) for a team member who already has an account: an old super_admin
 * who never set 2FA up (their right password alone no longer enrolls: `setup_not_allowed`), or a forgotten password.
 * Opening it, they set a new password (every session of theirs ends) and, without 2FA, set it up within 24 hours.
 * `--without-2fa` lists the members it is for. Audited (docs/OPERATIONS.md §10).
 */
class InvitePlatformMemberCommand extends Command
{
    protected $signature = 'rizqim:admin:invite
        {email? : The team member\'s email}
        {--without-2fa : List the active members who have no 2FA (they need this command or a 2FA reset)}
        {--reason= : Why (kept in the audit log)}';

    protected $description = 'Print a new one-time invite link for an existing RIZQIM team member (re-enrol 2FA, forgotten password)';

    public function handle(PlatformTeamService $team, AuditLogger $audit): int
    {
        if ($this->option('without-2fa')) {
            return $this->listWithoutTwoFactor();
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        if ($email === '') {
            $this->error('Email kiriting yoki --without-2fa bilan ro\'yxatni oling.');

            return self::FAILURE;
        }

        $member = User::query()->whereNotNull('platform_role')->whereRaw('lower(email) = ?', [$email])->first();
        if ($member === null) {
            $this->error("Bu email bilan jamoa a'zosi topilmadi: {$email}. Yangi a'zo: rizqim:admin:create");

            return self::FAILURE;
        }

        try {
            $result = $team->inviteExisting($member);
        } catch (AdminAuthException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason'));
        $audit->record('admin.cli.member_reinvited', $member, [], [], array_filter([
            'invite_expires_at' => $result['expires_at']->toIso8601String(),
            'two_factor_enabled' => $member->hasTwoFactorEnabled(),
            'reason' => $reason !== '' ? mb_substr($reason, 0, 500) : null,
        ], fn ($value) => $value !== null), ['actor' => null, 'device' => null, 'ip' => null]);

        $this->info("{$member->name} ({$email}) uchun yangi taklif havolasi yaratildi; eskilari ishlamaydi.");
        $this->line($member->hasTwoFactorEnabled()
            ? "Havola yangi parol qo'yadi (2FA o'zgarmaydi), barcha sessiyalari yopiladi."
            : "Havola yangi parol qo'yadi va 2FA o'rnatishga olib boradi (24 soat ichida), barcha sessiyalari yopiladi.");
        $this->line('Taklif havolasi (bir marta ko\'rsatiladi, ' . $result['expires_at']->diffInHours(now(), true) . ' soat amal qiladi):');
        $this->line(PlatformTeamService::inviteUrl($result['token']));

        return self::SUCCESS;
    }

    private function listWithoutTwoFactor(): int
    {
        $members = User::query()->whereNotNull('platform_role')->whereNull('platform_disabled_at')->orderBy('id')->get()
            ->filter(fn (User $member) => !$member->hasTwoFactorEnabled());

        if ($members->isEmpty()) {
            $this->info("2FA siz faol a'zo yo'q.");

            return self::SUCCESS;
        }

        $this->table(['ID', 'Email', 'Rol', 'Holat'], $members->map(fn (User $member) => [
            $member->id,
            $member->email,
            $member->platform_role?->value,
            PlatformTeamService::status($member),
        ])->all());
        $this->line('Har biri uchun: php artisan rizqim:admin:invite <email> (yoki Tizim → Jamoa → "2FA ni tiklash").');

        return self::SUCCESS;
    }
}
