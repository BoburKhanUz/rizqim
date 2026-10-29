<?php

namespace App\Domain\Pos\Waitlist;

use App\Domain\Pos\Booking\BookingException;

/**
 * A waitlist rule refused the action (same shape as a booking's: Uzbek message, stable `reason`, HTTP status).
 */
class WaitlistException extends BookingException {}
