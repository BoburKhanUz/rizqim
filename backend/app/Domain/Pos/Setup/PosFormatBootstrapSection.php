<?php

namespace App\Domain\Pos\Setup;

use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * The `format` of GET /pos/bootstrap (P6-11, docs/POS_SPEC.md "Format profili"): the branch's profile, the till's mode
 * and first screen, the effective modules (profile + owner overrides − law locks − what the plan lacks) and the locks.
 * The till, the waiter phone, the KDS and the customer display show their screens and buttons from `modules` only; the
 * backend checks the same list on every gated request (`pos.module:<key>`).
 */
final class PosFormatBootstrapSection implements PosBootstrapContributor
{
    /** The section's shape (the bootstrap's API docs). */
    public const SECTION = 'array{profile: string, profile_label: string, mode: string, first_screen: string, modules: list<string>, locks: array{service_charge_zero: bool}}';

    public function __construct(
        private readonly PosModules $modules,
    ) {}

    /**
     * @return array{format: array{profile: string, profile_label: string, mode: string, first_screen: string, modules: list<string>, locks: array{service_charge_zero: bool}}}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        return ['format' => $this->modules->format($branch)];
    }
}
