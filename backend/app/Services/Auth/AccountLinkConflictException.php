<?php

namespace App\Services\Auth;

/**
 * A verified phone that more than one customer account claims, or that belongs to another account the current one
 * cannot be safely merged into. Answered with 409; nothing is changed or deleted.
 */
class AccountLinkConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            "Bu telefon raqami boshqa hisobga bog'langan. Hisoblarni birlashtirish uchun qo'llab-quvvatlash xizmatiga murojaat qiling."
        );
    }
}
