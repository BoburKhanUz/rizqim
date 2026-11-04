<?php

namespace App\Domain\Pos\Lan;

/**
 * P6 local bridge: what a terminal is on its branch's LAN (GET /pos/bootstrap `lan.role`): the hub (the main till runs
 * the bridge and holds the branch's secret) or a client (a waiter's phone, a KDS, a customer display, another till).
 */
enum LanRole: string
{
    case HUB = 'hub';
    case CLIENT = 'client';
}
