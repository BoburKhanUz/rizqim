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
 * invite link (72 hours). Opening it, they set a password and the 2FA. Audited. An existing member gets a new link from
 * `rizqim:admin:invite` instead.
 */
class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'rizqim:admin:create
        {email : The new member\'s email (their sign-in)}
        {name : Their name as the team sees it}
        {--role=superadmin : superadmin, operator, support, finance, sales or viewer; several comma-separated (operator,sales)}';

    protected $description = 'Create a RIZQIM team member (superadmin by default) and print their one-time invite link';

    public function handle(PlatformTeamService $team, AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));
        // Wave P: one or more roles
        $values = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('role'))), fn (string $value) => $value !== ''));
        $roles = array_map(fn (string $value) => PlatformRole::tryFrom($value), $values);

        if ($roles === [] || in_array(null, $roles, true)) {
            $this->error("Noto'g'ri rol. Mumkin: " . implode(', ', PlatformRole::values()));

            return self::FAILURE;
        }

        if (Validator::make(['email' => $email, 'name' => $name], ['email' => 'required|email|max:255', 'name' => 'required|string|min:2|max:255'])->fails()) {
            $this->error("Email yoki ism noto'g'ri.");

            return self::FAILURE;
        }

        try {
            $result = $team->invite($name, $email, $roles, null);
        } catch (AdminAuthException $e) {
            $this->error($e->getMessage() . ' Mavjud a\'zoga (2FA siz eski superadmin, unutilgan parol) yangi havola: php artisan rizqim:admin:invite ' . $email);

            return self::FAILURE;
        }

        $held = $result['member']->platformRoles();
        $audit->record('admin.cli.member_created', $result['member'], [], ['email' => $email, 'platform_roles' => array_map(fn (PlatformRole $role) => $role->value, $held)], [
            'invite_expires_at' => $result['expires_at']->toIso8601String(),
        ], ['actor' => null, 'device' => null, 'ip' => null]);

        $labels = implode(', ', array_map(fn (PlatformRole $role) => $role->label(), $held));
        $this->info("{$name} ({$email}) jamoaga {$labels} sifatida qo'shildi.");
        $this->line('Taklif havolasi (bir marta ko\'rsatiladi, ' . $result['expires_at']->diffInHours(now(), true) . ' soat amal qiladi):');
        $this->line(PlatformTeamService::inviteUrl($result['token']));

        return self::SUCCESS;
    }
}
