<?php

namespace App\Domain\Pos\QrPayments;

/**
 * Thrown inside the payment's transaction when the QR is no longer pending (another confirmation, a cancel, the
 * expiry got there first): the payment rolls back.
 */
final class QrConfirmationLost extends \RuntimeException {}
