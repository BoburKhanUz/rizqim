<?php

namespace App\Domain\Pos\Booking;

/**
 * AdvanceLedger refused to offset a deposit (none, not enough, already offset differently against the bill).
 */
class AdvanceLedgerException extends BookingException {}
