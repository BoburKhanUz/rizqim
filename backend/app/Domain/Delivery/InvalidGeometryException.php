<?php

namespace App\Domain\Delivery;

use InvalidArgumentException;

/**
 * A zone polygon that is not usable GeoJSON; the message is Uzbek and fit to show to the owner.
 */
class InvalidGeometryException extends InvalidArgumentException {}
