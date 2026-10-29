<?php

namespace Tests\Feature\PosMenu;

use Tests\Feature\PosHallFixtures;

/**
 * P6-15 helpers on top of the P3-12 hall fixtures (a branch with a hall, waiter, cashier and a small menu) plus the
 * module switches below.
 */
trait PosMenuFixtures
{
    use PosHallFixtures;
    use PosMenuModules;
}
