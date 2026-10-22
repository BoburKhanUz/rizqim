<?php

namespace App\Domain\Pos\Contracts;

/**
 * One-time manager approvals on a terminal (P3-18 owns POST /pos/approvals: a manager enters their PIN, the server
 * returns a short-lived token bound to one permission and one device). Any POS action that needs a permission the
 * signed-in staff member lacks accepts such a token instead.
 */
interface ManagerApprovals
{
    /**
     * Consumes the token and returns the approving manager's user id; throws when the token is unknown, expired,
     * already used, bound to another device, or not granting `permission`.
     */
    public function consume(string $token, string $permission, ?int $deviceId): int;
}
