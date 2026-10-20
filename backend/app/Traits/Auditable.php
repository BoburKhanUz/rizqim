<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            static::logAudit('created', $model);
        });

        static::updated(function ($model) {
            static::logAudit('updated', $model);
        });

        static::deleted(function ($model) {
            static::logAudit('deleted', $model);
        });
    }

    protected static function logAudit(string $action, $model)
    {
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            // Skip CLI/seeding commands unless it's a test or we have a logged-in user
            if (!Auth::check()) {
                return;
            }
        }

        $user = Auth::user();
        if ($user && $user->role === \App\Enums\UserRole::SUPER_ADMIN) {
            // Log the override action
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'superadmin_override',
                'model_type' => get_class($model),
                'model_id' => $model->id,
                // No secrets or clear phone numbers in the log (P1-12)
                'payload_json' => \App\Services\Audit\AuditRedactor::clean([
                    'event' => $action,
                    'changes' => $action === 'updated' ? $model->getChanges() : $model->toArray(),
                ]),
            ]);
        }
    }
}
