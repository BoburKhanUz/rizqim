<?php

namespace App\Domain\Pos\Sync;

/**
 * Wave M5 S3 (docs/scale/M5_DESIGN.md §4): what the server found about the signature of an offline operation
 * (`pos_sync_operations.sig_status`; null on rows received before signing existed).
 */
enum SyncSignatureStatus: string
{
    /** The MAC is the device key's over the bytes received, and the operation follows the one before it. */
    case OK = 'ok';
    /** Sent in the old unsigned shape (an app from before signing, a till without a key, or a signature removed). */
    case MISSING = 'missing';
    /** The MAC is not this device key's over these bytes: the journal line was changed, or the key is not the device's. */
    case BAD = 'bad';
    /** The MAC is right, but the operation does not follow the last one the server holds: lines are missing between. */
    case CHAIN_GAP = 'chain_gap';
}
