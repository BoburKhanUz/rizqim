<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait HasPublicUuid
{
    use HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
