<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P4-15: "this happened at" facts reported by processes outside the backend (the backup service writes `backup`).
 *
 * @property string $key
 * @property \Illuminate\Support\Carbon $recorded_at
 * @property array<string, mixed>|null $meta
 */
class MonitoringMarker extends Model
{
    public const BACKUP = 'backup';

    /** M3: pgBackRest info + pg_stat_archiver, written every 5 minutes by the backup service (scripts/backup.sh). */
    public const PITR = 'pitr';

    /** M3: the data filesystem, pg_wal and the archive spool, written with `pitr`. */
    public const DISK = 'disk';

    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
