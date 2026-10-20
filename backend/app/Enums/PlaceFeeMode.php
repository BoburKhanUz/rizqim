<?php

namespace App\Enums;

/**
 * How a "joy narxi" (place fee) is charged: per guest, or once per table/room.
 */
enum PlaceFeeMode: string
{
    case PER_PERSON = 'per_person';
    case PER_TABLE = 'per_table';
}
