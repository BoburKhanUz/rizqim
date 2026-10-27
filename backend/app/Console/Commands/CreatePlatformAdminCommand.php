<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Services\Admin\AdminAuthException;
use App\Services\Admin\PlatformTeamService;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * P4-11: the first superadmin of the Boshqaruv markazi (docs/OPERATIONS.md): creates the member and prints a one-time
 * invite link (72 hours). Opening it, they set a password and the 2FA. Audited.
 */
class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'rizqim:admin:create
        {email : The new member\'s email (their sign-in)}
        {name : Their name as the team sees it}
        {--role=superadmin : superadmin, operator, support, finance, sales or viewer}';

    protected $description = 'Create a RIZQIM team member (superadmin by default) and print their one-time invite link';

    public function handle(PlatformTeamService $team, AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));
        $role = PlatformRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error("Noto'g'ri rol. Mumkin: " . implode(', ', PlatformRole::values()));

            return self::FAILURE;
        }

        if (Validator::make(['email' => $email, 'name' => $name], ['email' => 'required|email|max:255', 'name' => 'required|string|min:2|max:255'])->fails()) {
            $this->error("Email yoki ism noto'g'ri.");

            return self::FAILURE;
        }

        try {
            $result = $team->invite($name, $email, $role, null);
        } catch (AdminAuthException $e) {
            $this->error($e->getMessage() . ' Mavjud superadmin keyingi kirishda 2FA o\'rnatadi; parol unutilgan bo\'lsa, hisobni boshqa superadmin qayta taklif qiladi.');

            return self::FAILURE;
        }

        $audit->record('admin.cli.member_created', $result['member'], [], ['email' => $email, 'platform_role' => $role->value], [
            'invite_expires_at' => $result['expires_at']->toIso8601String(),
        ], ['actor' => null, 'device' => null, 'ip' => null]);

        $this->info("{$name} ({$email}) jamoaga {$role->label()} sifatida qo'shildi.");
        $this->line('Taklif havolasi (bir marta ko\'rsatiladi, ' . $result['expires_at']->diffInHours(now(), true) . ' soat amal qiladi):');
        $this->line(PlatformTeamService::inviteUrl($result['token']));

        return self::SUCCESS;
    }
}
