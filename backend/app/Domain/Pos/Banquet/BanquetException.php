<?php

namespace App\Domain\Pos\Banquet;

use App\Domain\Pos\Booking\BookingException;

/**
 * A banquet rule refused the action (P6-51..P6-54): the message is for the manager or cashier (Uzbek), `reason` a
 * stable code (slot_taken, stale_version, event_frozen, invalid_transition…), `status` the HTTP status. Rendered and
 * left unreported like a booking refusal (BookingException).
 */
class BanquetException extends BookingException {}
