<?php

namespace App\Services\Admin;

use App\Enums\AdminPermission;
use App\Models\User;
use App\Support\PiiMask;

/**
 * P4-11: personal data in admin answers. A member with admin.pii.view sees the full phone or card number and the
 * reveal is recorded (admin.pii.revealed, one entry per request: RecordAdminAudit); everyone else sees it masked
 * (PiiMask). Every admin resource shows phones and cards through here.
 */
class AdminPii
{
    public function __construct(
        private readonly AdminPermissionService $permissions,
    ) {}

    public function canReveal(?User $viewer): bool
    {
        return $viewer !== null && $this->permissions->has($viewer, AdminPermission::PII_VIEW);
    }

    /**
     * @param  string  $type  the subject's type for the audit entry (user, store, order...)
     */
    public function phone(?User $viewer, ?string $phone, string $type, int|string|null $id): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return $phone;
        }

        if (!$this->canReveal($viewer)) {
            return PiiMask::phone($phone);
        }

        AdminAudit::for()->revealed($type, $id, 'phone');

        return PiiMask::formatPhone($phone);
    }

    /**
     * @param  AdminPermission|null  $orWith  a permission that also reveals this one card (a payout approver needs the
     *                                        number to transfer to); the reveal is audited the same way
     */
    public function card(?User $viewer, ?string $card, string $type, int|string|null $id, ?AdminPermission $orWith = null): ?string
    {
        if ($card === null || trim($card) === '') {
            return $card;
        }

        $allowed = $this->canReveal($viewer)
            || ($orWith !== null && $viewer !== null && $this->permissions->has($viewer, $orWith));
        if (!$allowed) {
            return PiiMask::card($card);
        }

        AdminAudit::for()->revealed($type, $id, 'card');

        return $card;
    }
}
