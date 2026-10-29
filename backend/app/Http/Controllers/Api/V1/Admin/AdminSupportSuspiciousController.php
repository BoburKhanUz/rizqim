<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Customers\SuspiciousCases;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;

/**
 * P4-31: Mijozlar va yordam → Shubhali holatlar (admin.support.view, read-only). Nothing is decided automatically:
 * each row says in words why it is here and links to the user card.
 */
#[Group('Admin')]
class AdminSupportSuspiciousController extends AdminController
{
    public function __construct(
        private readonly SuspiciousCases $cases,
    ) {}

    /**
     * Suspicious cases
     *
     * Three `groups` (`kind`: cancellations, promo_sharing, refunds), each with its `label`, a `description` of the rule
     * and at most 50 `rows`: `user` (the account to open), `accounts` (promo_sharing: every account that shares the
     * phone or Telegram), `reason` (why, in words), `metric` (the count, or the refunded total as a decimal string),
     * `since` (the last event), `href` (/users/{id}). Thresholds: config/support.php.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{generated_at: string|null, groups: list<array{kind: string, label: string, description: string, rows: list<array{kind: string, user: array{id: int, name: string, role_label: string|null, blocked: bool}, accounts: list<array{id: int, name: string, role_label: string|null, blocked: bool}>, reason: string, metric: int|string, since: string|null, href: string, promo_code?: string, shared_by?: string}>}>}}')]
    public function index(): JsonResponse
    {
        return $this->success($this->cases->all());
    }
}
