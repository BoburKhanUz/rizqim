<?php

namespace App\Domain\Pos\Sync;

/**
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4): what the store's signature mode does with one offline operation
 * (SignaturePolicy::verdict()). Whatever it is, the operation is answered and recorded: never the batch refused.
 */
enum SignatureVerdict: string
{
    /** Applied as before (observe mode whatever the signature; enforce mode with a good one). */
    case APPLY = 'apply';
    /** Enforce, unsigned or badly signed, not money: not applied, rejected `signature_invalid`. */
    case REJECT = 'reject';
    /** Enforce, unsigned or badly signed money: recorded all the same, a conflict `payment_unverified`. */
    case PAYMENT_UNVERIFIED = 'payment_unverified';
    /** Enforce, a line missing before it (`chain_gap`): applied, then a conflict for the manager. */
    case APPLY_AS_CONFLICT = 'apply_as_conflict';
}
