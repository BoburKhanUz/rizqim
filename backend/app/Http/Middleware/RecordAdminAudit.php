<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * `admin.audit` (every /api/v1/admin route): the one place admin actions reach the harakatlar jurnali. A write
 * (POST, PUT, PATCH, DELETE) that succeeded is recorded as the route's name (or the action the controller set on
 * AdminAudit), with the member, the subject, before/after, the reason, the client's IP and user agent. Sign-in
 * attempts are recorded whatever the answer; reads only when they revealed personal data (admin.pii.view).
 * Secrets never reach the entry (AuditRedactor).
 */
class RecordAdminAudit
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = AdminAudit::for($request);

        $response = $next($request);

        $isWrite = !in_array($request->getMethod(), self::READ_METHODS, true);
        $succeeded = $response->getStatusCode() < 400;

        if ($isWrite && !$context->skip && ($succeeded || $context->always)) {
            $this->write($request, $context, $context->action ?? $this->routeAction($request), $response->getStatusCode(), $succeeded);
        }

        if ($context->revealed !== []) {
            $this->write($request, (new AdminAudit())->actor($this->actor($context))->meta([
                'revealed' => $context->revealed,
                'route' => $this->routeAction($request),
            ]), 'admin.pii.revealed', $response->getStatusCode(), true);
        }

        return $response;
    }

    private function write(Request $request, AdminAudit $context, string $action, int $status, bool $succeeded): void
    {
        $meta = $context->meta;
        if (!$succeeded) {
            $meta['status'] = $status;
        }
        $reason = $request->input('reason');
        if (is_string($reason) && $reason !== '' && !array_key_exists('reason', $meta)) {
            $meta['reason'] = mb_substr($reason, 0, 500);
        }

        $this->audit->record($action, $context->subject, $context->before, $context->after, $meta, [
            'actor' => $this->actor($context),
            'device' => null,
            'store_id' => $context->storeId,
            'branch_id' => null,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    private function actor(AdminAudit $context): ?User
    {
        if ($context->actorSet) {
            return $context->actor;
        }

        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function routeAction(Request $request): string
    {
        return (string) ($request->route()?->getName() ?? 'admin.' . strtolower($request->getMethod()) . ':' . $request->path());
    }
}
